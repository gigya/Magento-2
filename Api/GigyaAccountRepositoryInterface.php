<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Api;

use Gigya\CmsStarterKit\sdk\GSApiException;
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
     * Update an existing Gigya customer account.
     *
     * @param GigyaUser $gigyaAccount Shall have a uid not null.
     * @throws GSApiException If error encountered on service call or functional error returned by service. Check error code to identify the case.
     */
    function update($gigyaAccount);

    /**
     * Get a Gigya customer account.
     *
     * @param string $uid
     * @return GigyaUser
     */
    function get($uid);
}