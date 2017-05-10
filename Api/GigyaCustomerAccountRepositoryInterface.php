<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Api;

use Gigya\GigyaIM\Api\Data\GigyaCustomerAccountInterface;

/**
 * GigyaCustomerAccountRepositoryInterface
 *
 * Repository for getting and saving data from / to Gigya's service.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
interface GigyaCustomerAccountRepositoryInterface
{
    /**
     * Sends the user's Magento account data to the Gigya service.
     *
     * @param GigyaCustomerAccountInterface $gigyaCustomerAccount
     */
    function save(GigyaCustomerAccountInterface $gigyaCustomerAccount);
}