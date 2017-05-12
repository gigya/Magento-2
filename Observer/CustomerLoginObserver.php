<?php

/**
 * Clever-Age
 * Date: 11/05/17
 * Time: 10:05
 */
namespace Gigya\GigyaIM\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Session;

class CustomerLoginObserver implements ObserverInterface
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * CustomerLoginObserver constructor.
     * @param Session $customerSession
     */
    public function __construct(
        Session $customerSession
    )
    {
        $this->session = $customerSession;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try{
            $customerData = $observer->getData('customer')->getDataModel();

            $uid = $this->session->getGigyaRawData()->getUID();
            $email = $this->session->getGigyaLoggedInEmail();

            $customerData->setCustomAttribute('gigya_uid',$uid);
            $customerData->setEmail($email);

        } catch(\Exception $e) {
            //throw new \Exception(" ");
        }
    }
}
