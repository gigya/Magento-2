<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer\Backend;

use Gigya\GigyaIM\Api\GigyaCustomerAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\Mapping\GigyaCustomerAccountMapper;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;

/**
 * SyncAccountObserver
 *
 * Back office observer that will synchronize Magento to Gigya account when it's created or saved, if necessary.
 *
 * The Gigya service will be called, if needed to update the Gigya data with the provided Magento Customer.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 * The check whether or not the Gigya's service should be notified with updated data is based on current full action name.
 *
 */
class SyncAccountObserver implements ObserverInterface
{
    /** @var  $gigyaCustomerAccountMapper */
    protected $gigyaCustomerAccountMapper;

    /** @var  GigyaCustomerAccountRepositoryInterface */
    protected $gigyaCustomerAccountRepository;

    public function __construct(
        GigyaCustomerAccountMapper $gigyaCustomerAccountMapper,
        GigyaCustomerAccountRepositoryInterface $gigyaCustomerAccountRepository
    )
    {
        $this->gigyaCustomerAccountMapper = $gigyaCustomerAccountMapper;
        $this->gigyaCustomerAccountRepository = $gigyaCustomerAccountRepository;
    }

    public function execute(EventObserver $observer)
    {
        $eventData = $observer->getEvent()->getData();

        $gigyaCustomerAccount = $this->gigyaCustomerAccountMapper->enrichGigyaCustomerAccountInstance($eventData['customer_data_object']);

        $this->gigyaCustomerAccountRepository->save($gigyaCustomerAccount);

        // 'customer_data_object' => $savedCustomer, 'orig_customer_data_object' => $customer])
    }
}