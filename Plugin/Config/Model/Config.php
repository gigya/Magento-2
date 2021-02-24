<?php

namespace Gigya\GigyaIM\Plugin\Config\Model;

use Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
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
     * @var GigyaLogger
     */
    protected $logger;

    /**
     * Config constructor.
     * @param GigyaMageHelper $gigyaMageHelper
     * @param StoreRepository $storeRepository
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param GigyaConfig $gigyaConfig
     * @param GigyaLogger $logger
     */
    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        StoreRepository $storeRepository,
        ScopeConfigInterface $scopeConfigInterface,
        GigyaConfig $gigyaConfig,
        GigyaLogger $logger
    ) {
        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->storeRepository = $storeRepository;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->gigyaConfig = $gigyaConfig;
        $this->logger = $logger;
    }

	/**
	 * @param \Magento\Config\Model\Config $subject
	 *
	 * @throws LocalizedException
	 * @throws \Gigya\PHP\GSException
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
	 * @throws \Exception
	 */
    public function beforeSave(\Magento\Config\Model\Config $subject)
    {
        $section = $subject->getData('section');

        if ($section == 'gigya_section') {
            $scope = $this->getScope($subject);
			$scopeType = $scope[0];
			$scopeCode = $scope[1];
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
                            'socialize.getProvidersConfig',
                            []
                        );
                    }
                } catch (GSApiException $e) {
                    $this->logger->critical(
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
	 * @param string                       $scopeType
	 * @param string                       $scopeCode
	 *
	 * @return mixed
	 */
	public function extractSettings(\Magento\Config\Model\Config $subject, $scopeType, $scopeCode)
    {
        $currentSettings = $this->gigyaConfig->getGigyaGeneralConfig($scopeType, $scopeCode);
        $settings = [];
        $groups = $subject->getData('groups');

        foreach ($groups['general']['fields'] as $key => $value) {
            if (isset($value['inherit']) && $value['inherit'] == 1) {
                $settings[$key] = $currentSettings[$key];
            } elseif (isset($value['value'])) {
                $settings[$key] = $value['value'];
            }
        }

		if ($settings['authentication_mode'] == 'user_rsa') {
			$settings['rsa_private_key_decrypted'] = true;
			unset($settings['app_secret']); /* Remove secret key if RSA auth method has been chosen */
		} else {
			if ($settings['app_secret'] == '******') {
				unset($settings['app_secret']);
			} else {
				$settings['app_secret_decrypted'] = true;
			}
			unset($settings['rsa_private_key']); /* Remove RSA private key if user/secret method has been chosen */
		}

        return $settings;
    }

    /**
     * @param $settings
     * @param string $scopeType
     * @param string $scopeCode
     * @return bool
     * @throws LocalizedException
     */
    public function validateSettings($settings, $scopeType, $scopeCode)
    {
        $currentSettings = $this->gigyaConfig->getGigyaGeneralConfig($scopeType, $scopeCode);

        if (empty($currentSettings)) {
            return true;
        }

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

        if ($settings['domain'] == \Gigya\GigyaIM\Model\Config\Source\Domain::OTHER &&
            empty($settings['data_center_host']) === true) {
            throw new LocalizedException(
                __('It is necessary to provide a data center host')
            );
        }

        return true;
    }
}