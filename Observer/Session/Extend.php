<?php

namespace Gigya\GigyaIM\Observer\Session;

use Gigya\GigyaIM\Model\Session\Extend as ExtendModel;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class Extend implements ObserverInterface
{

    /**
     * @var ExtendModel
     */

    protected $sessionExtendModel;

    public function __construct(
        ExtendModel $sessionExtendModel
    )
    {
        $this->sessionExtendModel = $sessionExtendModel;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /* @var $request \Magento\Framework\App\RequestInterface */
        $request = $observer->getEvent()->getRequest();
        if($request->isAjax())
        {
            $this->sessionExtendModel->extendSession();
        }
    }
}