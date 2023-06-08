<?php

namespace Gigya\GigyaIM\Observer\Session;

use Gigya\GigyaIM\Model\Session\Extend as ExtendModel;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;

class Extend implements ObserverInterface
{
    /**
     * @var ExtendModel
     */
    protected ExtendModel $sessionExtendModel;

    /** @var GigyaConfig */
    protected GigyaConfig $config;

    /**
     * @param ExtendModel $sessionExtendModel
     * @param GigyaConfig $config
     */
    public function __construct(ExtendModel $sessionExtendModel, GigyaConfig $config)
    {
        $this->sessionExtendModel = $sessionExtendModel;
        $this->config = $config;
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws InputException
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     */
    public function execute(Observer $observer): void
    {
        if ($this->config->isGigyaEnabled()) {
            /* @var $request RequestInterface */
            $request = $observer->getEvent()->getRequest();
            if ($request->isAjax()) {
                $this->sessionExtendModel->extendSession();
            }
        }
    }
}
