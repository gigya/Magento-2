<?php
/**
 * update customer fields with mapped fields from Gigya.
 * See Magento prepared methods at: app/code/Magento/Customer/Model/Data/Customer.php
 * helpful magento guide for creating custom fields:
 * https://maxyek.wordpress.com/2015/10/22/building-magento-2-extension-customergrid/comment-page-1/
 *
 * For mapping existing Magento custom fields to gigya fields:
 * use: $customer->setCustomAttribute($attributeCode, $attributeValue);
 * or: $customer->setCustomAttributes(array());
 * located at: /lib/internal/Magento/Framework/Api/AbstractExtensibleObject
 */

namespace Gigya\GigyaIM\Model;

use Gigya\CmsStarterKit\fieldMapping;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Data\Customer;

class M2CustomerFieldsUpdater extends fieldMapping\CmsUpdater
{

    protected $customerRepository;

    public $_logger;

    public function __construct(
        \Gigya\CmsStarterKit\User\GigyaUser $gigyaAccount,
        $mappingFilePath,
        CustomerRepositoryInterface $customerRepository
    )
    {
        parent::__construct($gigyaAccount, $mappingFilePath);

        $this->customerRepository = $customerRepository;
    }

    public function callCmsHook() {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\Event\ManagerInterface $manager */
        $manager = $om->get('Magento\Framework\Event\ManagerInterface');
        $gigya_user = array("gigya_user" => $this->getGigyaUser());
        $manager->dispatch("gigya_pre_field_mapping",$gigya_user);
    }

    public function setGigyaLogger($logger) {
        $this->_logger = $logger;
    }

    /**
     * @param Magento/Customer $account
     */
    public function setAccountValues(&$account) {
        foreach ($this->getGigyaMapping() as $gigyaName => $confs) {
            /** @var \Gigya\CmsStarterKit\fieldMapping\ConfItem $conf */
            $value = parent::getValueFromGigyaAccount($gigyaName); // e.g: loginProvider = facebook
            // if no value found, log and skip field
            if (is_null($value)) {
                $this->_logger->info( __FUNCTION__ . ": Value for {$gigyaName} not found in gigya user object. check your field mapping configuration");
                continue;
            }
            foreach ($confs as $conf) {
                $mageKey = $conf->getCmsName();     // e.g: mageKey = prefix
                $value   = $this->castValue($value, $conf);

                if (gettype($value) == "boolean") {
                    $value = $this->transformGigyaToMagentoBoolean($value);
                }

                if (substr($mageKey, 0, 6) === "custom") {
                    $account->setCustomAttribute(substr($mageKey, 7), $value);
                } else {
                    $account->setData($mageKey, $value);
                }
            }
        }
    }

    /**
     * Transform Gigya boolean to Magento boolean - '0'/'1' values
     * @param bool $gigya_bool
     * @return string $magento_bool
     */
    protected function transformGigyaToMagentoBoolean($gigya_bool) {
        if ($gigya_bool == true) {
            $magento_bool = '1';
        } else {
            $magento_bool = '0';
        }
        return $magento_bool;
    }

    /**
     * @param Customer $cmsAccount
     * @param null $cmsAccountSaver
     */
    public function saveCmsAccount(&$cmsAccount, $cmsAccountSaver = null) {

        $this->customerRepository->save($cmsAccount);
    }
}