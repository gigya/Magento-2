<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Gigya\GigyaIM\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class SystemConfigEdit implements ObserverInterface
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var ConfigFactory
     */
    protected $configFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $config;

    /**
     * @var \Magento\Store\Model\StoreRepository
     */
    protected $storeRepository;

    /**
     * @var GigyaMageHelper
     */
    protected $gigyaMageHelper;

    public function __construct(
        ManagerInterface $messageManager,
        ConfigFactory $configFactory,
        ScopeConfigInterface $scopeConfigInterface,
        \Magento\Config\Model\ResourceModel\Config $config,
        \Magento\Store\Model\StoreRepository $storeRepository,
        GigyaMageHelper $gigyaMageHelper
    ) {
        $this->messageManager = $messageManager;
        $this->configFactory = $configFactory;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->config = $config;
        $this->storeRepository = $storeRepository;
        $this->gigyaMageHelper = $gigyaMageHelper;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $observer->getEvent()->getData('request');
        $section = $request->getParam('section');

        if (in_array($section, ['gigya_section', 'gigya_advanced'])) {
            $enablePath = 'gigya_section/general/enable_gigya';
            $store = intval($request->getParam('store'));

            if ($store > 0) {
                $website = $this->storeRepository->getById($store)->getWebsiteId();
            } else {
                $website = intval($request->getParam('website'));
            }

            if ($website > 0) {
                $scopeType = ScopeInterface::SCOPE_WEBSITES;
                $scopeCode = $website;
            } else {
                $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
                $scopeCode = null;
                $website = null;
            }

            $isEnable = $this->scopeConfigInterface->isSetFlag($enablePath, $scopeType, $scopeCode);

            if ($isEnable) {
                try {
                    $erros = null;
                    $this->gigyaMageHelper->setGigyaSettings($scopeType, $scopeCode);
                    $gigyaApiHelper = $this->gigyaMageHelper->getGigyaApiHelper();

                    if ($gigyaApiHelper === false) {
                        $error = __("Bad settings. Functionality disabled.");
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

                    $error = __("Failed to connect to Gigya API. Functionality disabled. Error: %1.", $e->getMessage());
                }

                if (empty($error) == false) {
                    $this->messageManager->addErrorMessage($error);

                    $this->config->deleteConfig($enablePath, $scopeType, $scopeCode);

                    $configData = [
                        'section' => 'gigya_section',
                        'website' => $website,
                        'store' => null,
                        'groups' => [
                            'general' => [
                                'fields' => [
                                    'enable_gigya' => [
                                        'value' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ];
                    $configModel = $this->configFactory->create(['data' => $configData]);
                    $configModel->save();
                }
            }
        }
    }
}