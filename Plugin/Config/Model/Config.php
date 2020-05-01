<?php

namespace Gigya\GigyaIM\Plugin\Config\Model;

use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreRepository;
use Magento\Store\Model\ScopeInterface;

class Config
{
    /**
     * @var GigyaMageHelper
     */
    protected $gigyaMageHelper;

    /**
     * @var StoreRepository
     */
    protected $storeRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var GigyaConfig
     */
    protected $gigyaConfig;

    /**
     * Config constructor.
     * @param GigyaMageHelper $gigyaMageHelper
     * @param StoreRepository $storeRepository
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param GigyaConfig $gigyaConfig
     */
    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        StoreRepository $storeRepository,
        ScopeConfigInterface $scopeConfigInterface,
        GigyaConfig $gigyaConfig
    ) {
        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->storeRepository = $storeRepository;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->gigyaConfig = $gigyaConfig;
    }

    /**
     * @param \Magento\Config\Model\Config $subject
     * @throws LocalizedException
     * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\sdk\GSException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function beforeSave(\Magento\Config\Model\Config $subject)
    {
        $section = $subject->getData('section');

        if ($section == 'gigya_section') {
            list($scopeType, $scopeCode) = $this->getScope($subject);
            $settings = $this->extractSettings($subject, $scopeType, $scopeCode);

            if ($settings['enable_gigya']) {
                try {
                    $this->validateSettings($settings, $scopeType, $scopeCode);

                    $this->gigyaMageHelper->setGigyaSettings($scopeType, $scopeCode, $settings);
                    $gigyaApiHelper = $this->gigyaMageHelper->getGigyaApiHelper();

                    if ($gigyaApiHelper === false) {
                        throw new LocalizedException(__("Bad settings. Unable to save."));
                    } else {
                        $this->gigyaMageHelper->getGigyaApiHelper()->sendApiCall(
                            "accounts.getSchema",
                            ["filter" => 'full']
                        );
                    }
                } catch (\Gigya\GigyaIM\Helper\CmsStarterKit\sdk\GSApiException $e) {
                    $this->gigyaMageHelper->gigyaLog(
                        "Error while trying to save gigya settings. " . $e->getErrorCode() .
                        " " . $e->getMessage() . " " . $e->getCallId()
                    );

                    throw new LocalizedException(
                        __("Failed to connect to Gigya API. Unable to save settings. Error: %1.", $e->getMessage())
                    );
                }
            }
        }
    }

    /**
     * @param \Magento\Config\Model\Config $subject
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getScope(\Magento\Config\Model\Config $subject)
    {
        $store = intval($subject->getData('store'));
        $website = $store > 0 ? $this->storeRepository->getById($store)->getWebsiteId() :
            intval($subject->getData('website'));

        if ($website > 0) {
            $scopeType = ScopeInterface::SCOPE_WEBSITES;
            $scopeCode = $website;
        } else {
            $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $scopeCode = null;
            $website = null;
        }

        return [$scopeType, $scopeCode];
    }

    /**
     * @param \Magento\Config\Model\Config $subject
     * @return mixed
     */
    public function extractSettings(\Magento\Config\Model\Config $subject, $scopeType, $scopeCode)
    {
        $currentSettings = $this->gigyaConfig->getGigyaGeneralConfig($scopeType, $scopeCode);
        $groups = $subject->getData('groups');

        foreach ($groups['general']['fields'] as $key => $value) {
            if (isset($value['inherit']) && $value['inherit'] == 1) {
                $settings[$key] = $currentSettings[$key];
            } elseif (isset($value['value'])) {
                $settings[$key] = $value['value'];
            }
        }

        if ($settings['app_secret'] == '******') {
            unset($settings['app_secret']);
        } else {
            $settings['app_secret_decrypted'] = true;
        }

        return $settings;
    }

    /**
     * @param $settings
     * @param $scopeType
     * @param $scopeCode
     * @return bool
     * @throws LocalizedException
     */
    public function validateSettings($settings, $scopeType, $scopeCode)
    {
        $currentSettings = $this->gigyaConfig->getGigyaGeneralConfig($scopeType, $scopeCode);

        if ($currentSettings['encryption_key_type'] != $settings['encryption_key_type'] &&
            (isset($settings['app_secret']) == false || empty($settings['app_secret']))) {
            throw new LocalizedException(
                __('It is necessary to re-enter application secret when modifying encryption key type')
            );
        }

        if ($settings['encryption_key_type'] == 'key_file' && isset($currentSettings['key_file_location']) &&
            $currentSettings['key_file_location'] != $settings['key_file_location'] &&
            (isset($settings['app_secret']) == false || empty($settings['app_secret']))) {
            throw new LocalizedException(
                __('It is necessary to re-enter application secret when modifying key file')
            );
        }

        return true;
    }
}