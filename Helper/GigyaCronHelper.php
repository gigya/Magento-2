<?php

namespace Gigya\GigyaIM\Helper;

use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class GigyaCronHelper extends AbstractHelper
{
    /** @var SearchCriteriaInterface  */
    protected $searchCriteria;

    /** @var FilterGroup */
    protected $filterGroup;

    /** @var FilterBuilder */
    protected $filterBuilder;

    /** @var CustomerRepository */
    protected $customerRepository;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var GigyaLogger */
    protected $logger;

    /**
     * GigyaCronHelper constructor.
     *
     * @param Context                 $context
     * @param CustomerRepository      $customerRepository
     * @param SearchCriteriaInterface $criteria
     * @param FilterGroup             $filterGroup
     * @param FilterBuilder           $filterBuilder
     * @param StoreManagerInterface   $storeManager
     * @param GigyaLogger             $logger
     */
    public function __construct(
        Context $context,
        CustomerRepository $customerRepository,
        SearchCriteriaInterface $criteria,
        FilterGroup $filterGroup,
        FilterBuilder $filterBuilder,
        StoreManagerInterface $storeManager,
        GigyaLogger $logger
    ) {
        parent::__construct($context);

        $this->customerRepository = $customerRepository;
        $this->searchCriteria = $criteria;
        $this->filterGroup = $filterGroup;
        $this->filterBuilder = $filterBuilder;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @param $attributeCode
     * @param $value
     *
     * @return CustomerInterface[]
     *
     * @throws LocalizedException
     */
    public function getCustomersByAttributeValue($attributeCode, $value)
    {
        $this->filterGroup->setFilters(
            [
                $this->filterBuilder
                    ->setField($attributeCode)
                    ->setConditionType('eq')
                    ->setValue($value)
                    ->create()
            ]
        );

        $this->searchCriteria->setFilterGroups([$this->filterGroup]);
        $customersList = $this->customerRepository->getList($this->searchCriteria);
        $customers = $customersList->getItems();

        return $customers;
    }

    /**
     * @param $attributeCode
     * @param $value
     *
     * @return CustomerInterface
     *
     * @throws LocalizedException
     */
    public function getFirstCustomerByAttributeValue($attributeCode, $value)
    {
        $customers = $this->getCustomersByAttributeValue($attributeCode, $value);
        if (count($customers) > 0) {
            return $customers[0];
        }

        return null;
    }

    /**
     * Parses a configuration setting and return an array of email addresses. An empty array is returned if the field is blank.
     * The function does not perform any validation on the correctness of the email addresses, but will return false if more than one valid delimiter is present in the emails string.
     * Input example:
     * 		string: "a@a.com, b@b.com"
     *
     * @param string	$configSetting
     * @param array		$validDelimiters
     *
     * @return array|false
     */
    public function getEmailsFromConfig($configSetting, $validDelimiters = [',', ';'])
    {
        /* Get config */
        $emails = str_replace(' ', '', (string)$this->scopeConfig->getValue($configSetting));

        /* If config was empty or non-existent */
        if (empty($emails)) {
            return [];
        }

        /* If there is more than one email, delimited by one of the valid delimiters given */
        $email_array = [];
        $foundDelimiterCount = 0;
        foreach ($validDelimiters as $delimiter) {
            if (strpos($emails, $delimiter) !== false) {
                $foundDelimiterCount++;
                if ($foundDelimiterCount > 1) {
                    return false;
                }

                $email_array = explode($delimiter, $emails);
            }
        }

        /* If no delimiters found, return an array containing the entire input (assumed: it is a single email address). Otherwise return found array */
        if (empty($email_array)) {
            return [$emails];
        }
        return $email_array;
    }

    /**
     * @param string $job_type
     * @param string $job_status
     * @param string|array $email_to
     * @param int|null $processed_items
     * @param int $failed_items
     * @param string $custom_email_body
     *
     * @return boolean
     * @throws NoSuchEntityException
     */
    public function sendEmail($job_type, $job_status, $email_to, $processed_items = null, $failed_items = 0, $custom_email_body = '')
    {
        if (empty($email_to)) {
            return false;
        }

        /* Generic email sender init */
        $email_sender = new \Zend_Mail();

        /* Set email body */
        $email_body = $custom_email_body;
        if (empty($custom_email_body)) {
            $email_body = '';
            if ($job_status == 'succeeded' or $job_status == 'completed with errors') {
                $email_body = 'Job ' . $job_status . ' on ' . gmdate("F n, Y H:i:s") . ' (UTC).';
                if ($processed_items !== null) {
                    $email_body .= ' ' . $processed_items . ' ' . (($processed_items > 1) ? 'items' : 'item') . ' successfully processed, ' . $failed_items . ' failed.';
                }
            } elseif ($job_status == 'failed') {
                $email_body = 'Job failed. No items were processed. Please consult the Gigya log ([Magento 2 dir]/var/gigya.log) for more info.';
            }
        }

        try {
            $email_subject = 'Gigya job of type ' . $job_type . ' ' . $job_status . ' on website ' . $this->storeManager->getStore()->getBaseUrl();
            $email_from = $email_to[0];

            $email_sender->setSubject($email_subject);
            $email_sender->setBodyText($email_body);
            $email_sender->setFrom($email_from, $job_type . ' cron');
            $email_sender->addTo($email_to);
            $email_sender->send();

            $this->logger->info($job_type . ' cron: mail sent to: ' . implode(', ', $email_to) . ' with status ' . $job_status);
        } catch (\Zend_Mail_Exception $e) {
            $this->logger->warning($job_type . ' cron: unable to send email: ' . $e->getMessage());
            return false;
        }

        return true;
    }
}