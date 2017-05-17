<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Api;

use Gigya\CmsStarterKit\user\GigyaUser;

/**
 * GigyaAccountRepositoryInterface
 *
 * Repository for getting and saving data from / to Gigya's service.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
interface GigyaAccountRepositoryInterface
{
    /**
     * Sends the user's Magento account data to the Gigya service.
     *
     * @param GigyaUser $gigyaAccount
     */
    function save(GigyaUser $gigyaAccount);
}