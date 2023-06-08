<?php

namespace Gigya\GigyaIM\Observer\Session;

use Exception;
use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Logger\Logger;
use Gigya\GigyaIM\Model\Config;

class Logout implements ObserverInterface
{
    /**
     * @var GigyaMageHelper
     */
    protected GigyaMageHelper $mageHelper;

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * Logout constructor.
     * @param GigyaMageHelper $mageHelper
     * @param Logger $logger
     * @param Config $config
     */
    public function __construct(GigyaMageHelper $mageHelper, Logger $logger, Config $config)
    {
        $this->mageHelper = $mageHelper;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer): void
    {
        if ($this->config->isGigyaEnabled() == false) {
            return;
        }

        $customer = $observer->getEvent()->getData('customer');

        if ($customer instanceof Customer === false) {
            return;
        }

        $gigyaUid = $customer->getData('gigya_uid');

        if (empty($gigyaUid)) {
            return;
        }

        $gigyaApiHelper = $this->mageHelper->getGigyaApiHelper();

        if ($gigyaApiHelper instanceof GigyaApiHelper === false) {
            return;
        }

        try {
            $gigyaApiHelper->sendApiCall('accounts.logout', ['UID' => $gigyaUid]);
        } catch (Exception $e) {
            $this->logger->error("Failed to logout customer $gigyaUid: " . $e->getMessage());
        }
    }
}
