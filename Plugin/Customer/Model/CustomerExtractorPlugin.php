<?php

namespace Gigya\GigyaIM\Plugin\Customer\Model;

use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\RequestInterface;
use Gigya\GigyaIM\Model\Config as GigyaConfig;

/**
 * CustomerExtractorPlugin
 *
 * Synchronize Magento and Gigya entity accounts on their required fields (no save is made to Magento neither to Gigya : herer we work on objects only)
 *
 * The extractor goal is to instanciate a Magento\Customer\Api\Data\CustomerInterface with form parameters.
 * Depending on the form code (cf. methods), we check if we shall enrich this instance with the current Gigya account data,
 * or in contrary update the current Gigya account data with this instance.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class CustomerExtractorPlugin
{
    /** @var  Session */
    protected $session;

    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /**
     * @var GigyaConfig
     */
    protected $config;

    public function __construct(
        Session $session,
        GigyaSyncHelper $gigyaSyncHelper,
        GigyaConfig $config
    ) {
        $this->session = $session;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->config = $config;
    }

    /**
     * Based on the form code : check if the instance created by the extractor must be enriched with the current Gigya account data.
     *
     * So far it's the case when we are going to create a new Magento account : it shall be updated with the Gigya data (for uid and email)
     *
     * @param $formCode
     * @return bool
     */
    protected function shallUpdateMagentoCustomerDataWithSessionGigyaAccount($formCode)
    {
        return $formCode == 'customer_account_create';
    }

    /**
     * Based on the form code : check if the current Gigya account data must be enriched with the instance created by the extractor.
     *
     * So far it's the case when we are going to update an existing Magento account : the current Gigya account data that lays on session must be synchronized.
     *
     * @param $formCode
     * @return bool
     */
    protected function shallUpdateSessionGigyaAccountWithMagentoCustomerData($formCode)
    {
        return $formCode == 'customer_account_edit';
    }

    /**
     * Will synchronize data between instances of Gigya account entity and Magento account entity.
     *
     * @see GigyaSyncHelper::updateMagentoCustomerDataWithSessionGigyaAccount()
     *
     * @see \Magento\Customer\Model\CustomerExtractor::extract()
     *
     * @param \Magento\Customer\Model\CustomerExtractor $subject
     * @param callable $proceed
     * @param string $formCode
     * @param RequestInterface $request
     * @param array $attributeValues
     * @return CustomerInterface
     */
    public function aroundExtract(
        $subject,
        $proceed,
        $formCode,
        RequestInterface $request,
        array $attributeValues = []
    ) {
        /** @var CustomerInterface $result */
        $result = $proceed($formCode, $request, $attributeValues);

        if ($this->config->isGigyaEnabled() == false) {
            return $result;
        }

        if ($this->shallUpdateMagentoCustomerDataWithSessionGigyaAccount($formCode)) {
            /** @var GigyaUser $gigyaAccountData */
            $gigyaAccountData = $this->session->getGigyaAccountData();
            /** @var string $gigyaAccountLoggingEmail */
            $gigyaAccountLoggingEmail = $this->session->getGigyaAccountLoggingEmail();

            $this->gigyaSyncHelper->updateMagentoCustomerDataWithSessionGigyaAccount($result, $gigyaAccountData, $gigyaAccountLoggingEmail);
        }

        if ($this->shallUpdateSessionGigyaAccountWithMagentoCustomerData($formCode)) {
            $this->session->setGigyaAccountLoggingEmail($result->getEmail());
        }

        return $result;
    }
}