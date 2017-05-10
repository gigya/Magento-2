<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Helper\Mapping;

use \Gigya\GigyaIM\Api\Data\GigyaCustomerAccountInterface;
use \Gigya\GigyaIM\Api\Data\GigyaCustomerAccountInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * GigyaCustomerAccountMapper
 *
 * Facility for mapping a Magento Customer entity to or from a Gigya data structure.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class GigyaCustomerAccountMapper
{
    /** @var GigyaCustomerAccountInterfaceFactory  */
    private $gigyaCustomerAccountInterfaceFactory;

    /**
     * GigyaCustomerAccountMapper constructor.
     *
     * @param GigyaCustomerAccountInterfaceFactory $gigyaCustomerAccountInterfaceFactory
     */
    public function __construct (
        GigyaCustomerAccountInterfaceFactory $gigyaCustomerAccountInterfaceFactory
    ) {
        $this->gigyaCustomerAccountInterfaceFactory = $gigyaCustomerAccountInterfaceFactory;
    }

    /**
     * Enrich or create an instance of GigyaCustomerAccountInterface with the provided Magento Customer entity.
     *
     * The instance will be fed with at least those Gigya's required data :
     * . uid : null if it's a new account, or not synchronized yet with Gigya
     * . loginEmail : the email set on the Magento account
     *
     * @param CustomerInterface $magentoCustomerInterface The source. Shall not be null.
     * @param GigyaCustomerAccountInterface $gigyaAccountInterface The target. If null it will be created.
     * @return GigyaCustomerAccountInterface
     */
    public function enrichGigyaCustomerAccountInstance($magentoCustomerInterface, $gigyaAccountInterface = null)
    {
        /** @var GigyaCustomerAccountInterface $result */
        $result = is_null($gigyaAccountInterface) ? $this->gigyaCustomerAccountInterfaceFactory->create() : $gigyaAccountInterface;

        $result->setUid($magentoCustomerInterface->getCustomAttribute('gigya_uid')->getValue());
        $result->setLoginEmail($magentoCustomerInterface->getEmail());

        return $result;
    }
}