<?php

namespace Gigya\GigyaIM\Api;

use Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;

/**
 * Interface GigyaAccountServiceInterface
 *
 * Proxy to the Gigya service for all operations concerning the Gigya's customer accounts.
 *
 * @package Gigya\GigyaIM\Api
 */
interface GigyaAccountServiceInterface
{
    /**
     * The error codes are defined in the module gigya/cms-starter-kit and on http://developers.gigya.com/display/GD/Response+Codes+and+Errors+REST
     */

    const ERR_CODE_BAD_CONFIGURATION = 400002;
    const ERR_CODE_MISSING_CERTIFICATE = 400003;
    const ERR_CODE_NOT_JOINABLE = 500000;
    const ERR_CODE_TIMEOUT = 504002;
    const ERR_CODE_INTERNAL_SERVER_ERROR = 500001;

    // The email used as the Magento account login id is not available on Gigya side because it's set on another Gigya account.
    const ERR_CODE_LOGIN_ID_ALREADY_EXISTS = 403043;

    /**
     * Update or create a Gigya customer account.
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

    /**
     * Update the Gigya service with the latest successfully updated version of an account.
     *
     * This method shall never send an exception.
     *
     * @param $uid string
     * @return GigyaUser null if the rollback failed or if the given uid has not been updated so far (thus nothing to roll back)
     */
    function rollback($uid);
}