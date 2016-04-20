<?php

include_once $_SERVER["DOCUMENT_ROOT"]  . '/app/code/Gigya/GigyaM2/sdk/GSSDK.php';
/**
 * Class GigyaCMS
 */
class GigyaCMS {

    private $api_key;
    private $api_secret;
    private $api_domain;
    private $user_key;
    private $user_secret;
    private $useUserKey;
    private $debug = false;

    /**
     * Logging instance
     * @var Gigya\GigyaM2\Logger\Logger
     */
    protected $_logger;

	/**
	 * Constructs a GigyaApi object.
	 */
	public function __construct(
        $apiKey, $secret, $apiDomain, $userSecret = NULL, $userKey = NULL, $useUserKey = false, $debug = false, $logger
    ) {
		$this->api_key    = $apiKey;
		$this->api_secret = $secret;
        $this->api_domain = $apiDomain;
        $this->user_key = $userKey;
        $this->user_secret = $userSecret;
        $this->use_user_key = $useUserKey;
        $this->debug = $debug;
        $this->_logger = $logger;
	}

	/**
	 * Helper function that handles Gigya API calls.
	 *
	 * @param mixed $method
	 *   The Gigya API method.
	 * @param mixed $params
	 *   The method parameters.
	 *
	 * @return array
	 *   The Gigya response.
	 */
	public function call( $method, $params, $trys = 0, $retrys = 0) {

		// Initialize new request.
        if ($this->use_user_key) {
            $request   = new GSRequest( $this->api_key, $this->user_secret, $method, null, false, $this->user_key );
        } else {
            $request   = new GSRequest( $this->api_key, $this->api_secret, $method );
        }
		$user_info = NULL;
		if ( ! empty( $params ) ) {
			foreach ( $params as $param => $val ) {
				$request->setParam( $param, $val );
			}

			$user_info = in_array( 'getUserInfo', $params );
		}

		// To be define on CMS code (or not).

		// Set the request path.
		$domain = !empty( $this->api_domain ) ? $this->api_domain : 'us1.gigya.com';
		$request->setAPIDomain( $domain );

		// Make the request.
		ini_set('arg_separator.output', '&');
        if ($this->debug) {
            $this->_gigya_debug_log($request);
        }
		$response = $request->send();
        if ($this->debug) {
            $this->_gigya_debug_log($response->getLog());
        }
		ini_restore ( 'arg_separator.output' );

		// Check for errors
		$err_code = $response->getErrorCode();
		if ( $err_code != 0 ) {
			if ( function_exists( '_gigya_error_log' ) ) {
				$log = explode( "\r\n", $response->getLog() );
				$this->_gigya_error_log( $log );
			}
            if ($retrys < $trys) {
                $this->call($method, $params, 1);
            }
            return $err_code;
		}

		return $this->jsonToArray( $response->getResponseText() );
	}

	/**
	 * Convert JSON response to a PHP array.
	 *
	 * @param $data
	 *   The JSON data.
	 * @param $data
	 *
	 * @return array
	 *   The converted array from the JSON.
	 */
	public static function jsonToArray( $data ) {
		return json_decode( $data, TRUE );
	}

	/**
	 * Check if application key is used or client key , and validate user accordingly
	 * @param $validation_params
	 * @return bool
	 */
	public function validateUserSignature($validation_params) {
		if ($this->use_user_key) {
			$app_key_validation_params = $this->getApplicationSignatureExchange($validation_params);
			$valid = $this->CMSValidateUserSignature($app_key_validation_params);
		} else {
			$valid = $this->CMSValidateUserSignature($validation_params);
		}
		return $valid;
	}

	/**
	 * Use the default signature returned from Gigya to get updated signature for application key
	 * @param $validation_params
	 * @return array
	 */
	public function getApplicationSignatureExchange($validation_params) {
		$validation_params["userKey"] = $this->user_key;
		$app_key_validation_array = $this->call( 'accounts.exchangeUIDSignature', $validation_params );
		return $app_key_validation_array;
	}

	/**
	 * Validate signature
	 * @param $validation_params
	 * @return bool
	 */
	public function CMSValidateUserSignature($validation_params) {
		$valid = SigUtils::validateUserSignature(
				$validation_params["UID"],
				$validation_params["signatureTimestamp"],
				$this->use_user_key ? $this->user_secret : $this->api_secret,
				$validation_params["UIDSignature"]
				);
		return $valid;
	}

	/**
	 * Check validation of the data center.
	 */
	public function apiValidate( $api_key, $api_secret, $api_domain ) {

		$request = new GSRequest( $api_key, $api_secret, 'socialize.shortenURL' );

		$request->setAPIDomain( $api_domain );
		$request->setParam( 'url', 'http://gigya.com' );

		$res = $request->send();

		return json_decode( $res->getResponseText() );
	}

	/**
	 * Get user info from Gigya
	 *
	 * @param $guid
	 *
	 * @return array || false
	 *   the user info from Gigya.
	 */
	public function getUserInfo( $guid ) {
		static $user_info = NULL;
		if ( $user_info === NULL ) {
			if ( ! empty( $guid ) ) {
				$params = array(
						'uid' => $guid,
				);

				return $this->call( 'getUserInfo', $params );
			}
		}

		return FALSE;
	}

	/**
	 * Attach the Gigya object to the user object.
	 *
	 * @param stdClass $account
	 *   The user object we need to attache to.
	 */
	public static function load( &$account ) {
		// Attache to user if the user is logged in.
		$account->gigya = ( isset( $account->uid ) ? new GigyaUser( $account->uid ) : NULL );
	}

	/**
	 * Social logout.
	 */
	public function userLogout( $guid ) {
		if ( ! empty( $guid ) ) {
			$params = array(
					'uid' => $guid,
			);

			return $this->call( 'socialize.logout', $params );
		}

		return FALSE;
	}

	/**
	 * Fetches information about the user friends.
	 *
	 * @param       $guid
	 * @param array $params .
	 *                      an associative array of params to pass to Gigya
	 *
	 * @see http://developers.gigya.com/020_Client_API/020_Methods/socialize.getFriends
	 * @return array
	 *      the response from gigya.
	 */
	public function getFriends( $guid, $params = array() ) {
		if ( ! empty( $guid ) ) {
			$params += array(
					'uid' => $guid,
			);

			return $this->call( 'logout', $params );
		}

		return FALSE;
	}

	/**
	 * Fetches information about the user capabilities.
	 *
	 * @param $guid
	 *
	 * @return array
	 *   the response from gigya if we successfuly get the data from gigya or empty array if not.
	 */
	public function getCapabilities( $guid ) {
		if ( $bio = $this->getUserInfo( $guid ) ) {
			$capabilities = explode( ', ', $bio['capabilities'] );
			array_walk( $capabilities, array( $this, 'trimValue' ) );
			return $capabilities;
		}

		return array();
	}

	/**
	 * Callback for array_walk.
	 * Helper function for trimming.
	 */
	private function trimValue( &$value ) {
		$value = trim( $value );
	}

	/**
	 *  Check if the user has a specific capability.
	 *
	 * @param $guid
	 * @param $capability
	 *    the capability we checking.
	 *
	 * @return boolean
	 *    TRUE if the user has the capability FALSE if not.
	 */
	public function hasCapability( $guid, $capability ) {
		$capabilities = $this->getCapabilities( $guid );
		if ( array_search( $capability, $capabilities ) === FALSE ) {
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Logs user in to Gigya's service and optionally registers them.
	 *
	 * @param string  $uid
	 *   The CMS User ID.
	 * @param boolean $is_new_user
	 *   Tell Gigya if we add a new user.
	 *
	 * @param null    $user_info
	 *
	 * @see      gigya_user_login()
	 *
	 * @return bool|null|string True if the notify login request succeeded or the error message from Gigya
	 */
	function notifyLogin( $uid, $is_new_user = FALSE, $user_info = NULL ) {

		$params['siteUID'] = $uid;

		// Set a new user flag if true.
		if ( ! empty( $is_new_user ) ) {
			$params['newUser'] = TRUE;
		}

		// Add user info.
		if ( ! empty( $user_info ) ) {
			$params['userInfo'] = json_encode( $user_info );
		}

		// Request.
		$response = $this->call( 'socialize.notifyLogin', $params );

		//Set  Gigya cookie.
		try {
			setcookie( $response["cookieName"], $response["cookieValue"], 0, $response["cookiePath"], $response["cookieDomain"] );
		} catch ( Exception $e ) {
			error_log( sprintf( 'error string gigya cookie' ) );
			error_log( sprintf( 'error message : @error', array( '@error' => $e->getMessage() ) ) );
		}

		return TRUE;
	}


	/**
	 * Informs Gigya that this user has completed site registration
	 *
	 * @param        $guid
	 * @param string $uid
	 *   The CMS User ID.
	 *
	 * @return array|bool
	 */
	public function notifyRegistration( $guid, $uid ) {
		if ( ! empty( $guid ) && ! empty( $uid ) ) {
			$params = array(
					'uid'     => $guid,
					'siteUID' => $uid,
			);

			return $this->call( 'socialize.notifyRegistration', $params );
		}

		return FALSE;
	}

	/**
	 * Delete user from Gigya's DB
	 *
	 * @param string $uid
	 *   The CMS User ID.
	 *
	 * @return bool
	 */
	public function deleteUser( $uid ) {
		if ( ! empty( $uid ) ) {
			$params = array(
					'uid' => $uid,
			);

			$this->call( 'socialize.deleteAccount', $params );

			return TRUE;
		}
	}

    public function testApiconfig() {
        $request = new GSRequest($this->api_key, $this->api_secret, 'shortenURL');
        $request->setAPIDomain($this->api_domain);
        $request->setParam('url', 'http://gigya.com');
        $response = $request->send();
        $error = $response->getErrorCode();
        if ($error != 0) {
            return $error;
        }
        return false;
    }


/////////////////////////////////
//            RaaS             //
/////////////////////////////////

	public function isRaaS() {
		$res = $this->call( 'accounts.getSchema', array() );
		if ( $res === 403036 ) {
			return false;
		}

		return true;
	}

	/**
	 * @param $guid
	 *
	 * @return mixed
	 */
	public function getAccount( $guid ) {

		$req_params = array(
				'UID'     => $guid,
				'include' => 'profile, data, loginIDs'
		);

		// Because we can only trust the UID parameter from the origin object,
		// We'll ask Gigya's API for account-info straight from the server.
		return $this->call( 'accounts.getAccountInfo', $req_params, 1 );

	}

	/**
	 * RaaS logout.
	 */
	public function accountLogout( $account ) {

		// Get info about the primary account.
		$query = "select UID from accounts where loginIDs.emails = '{$account->data->user_email}'";

		// Get the UID from Email.
		$res = $this->call( 'accounts.search', array( 'query' => $query ) );

		// Logout the user.
		$this->call( 'accounts.logout', array( 'UID' => $res['results'][0]['UID'] ) );

	}

	/**
	 * @param $account
	 */
	public function deleteAccount( $account ) {

		// Get info about the primary account.
		$query = "select UID from accounts where loginIDs.emails = '{$account->data->user_email}'";

		// Get the UID from Email.
		$res = $this->call( 'accounts.search', array( 'query' => $query ) );

		// Delete the user.
		$this->call( 'accounts.deleteAccount', array( 'UID' => $res['results'][0]['UID'] ) );

	}

	/**
	 * @param $guid
	 */
	public function deleteAccountByGUID( $guid ) {

		// Delete the user.
		$this->call( 'accounts.deleteAccount', array( 'UID' => $guid ) );

	}

    public function disableAccountByGUID($guid){
        // Disable Account
        $params = array(
            'UID' => $guid,
            'isActive' => false
        );
        $this->call("accounts.setAccountInfo", $params);
    }

	/**
	 * @param $account
	 * Gigya's RaaS account as we get from:
	 *
	 * @See getAccount
	 *
	 * @return array
	 */
	public function getProviders( $account ) {

		// Get info about the primary account.
		$query = "select loginProvider from accounts where loginIDs.emails = '{$account['profile']['email']}'";

		$search_res = $this->call( 'accounts.search', array( 'query' => $query ) );

		// Returns the primary provider, and the secondary (current).
		return array(
				'primary'   => $search_res['results'][0]['loginProvider'],
				'secondary' => $account['loginProvider']
		);
	}

	/**
	 * Checks if this email is the primary user email
	 *
	 * @param $gigya_emails
	 * @param $wp_email The email from WP DB.
	 *
	 * @internal param \The $userInfo user info from accounts.getUserInfo api call
	 * @return bool
	 */
	public static function isPrimaryUser( $gigya_emails, $wp_email ) {

		if ( in_array( $wp_email, $gigya_emails ) ) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Helper function to convert & validate JSON.
	 *
	 * @param $json
	 *
	 * @return array|mixed|string
	 */
	public static function parseJSON( $json ) {

		// decode the JSON data
		$result = json_decode( $json, true );

		$err = json_last_error();
		if ( $err != JSON_ERROR_NONE ) {

			// switch and check possible JSON errors
			switch ( json_last_error() ) {
				case JSON_ERROR_DEPTH:
					$msg = 'Maximum stack depth exceeded.';
					break;
				case JSON_ERROR_STATE_MISMATCH:
					$msg = 'Underflow or the modes mismatch.';
					break;
				case JSON_ERROR_CTRL_CHAR:
					$msg = 'Unexpected control character found.';
					break;
				case JSON_ERROR_SYNTAX:
					$msg = 'Syntax error, malformed JSON.';
					break;
				case JSON_ERROR_UTF8:
					$msg = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
					break;
				default:
					$msg = 'Unknown JSON error occurred.';
					break;
			}

			return $msg;
		}

		// Everything is OK.Return obj.
		return $result;
	}

	/**
	 * (Deprecated. use JSON and @see parseJSON())
	 * Helper function to convert a text field key|value to an array.
	 *
	 * @param string $values
	 *
	 * @return array
	 */
	public static function advancedValuesParser( $values ) {

		if ( ! empty( $values ) ) {
			$lines  = array();
			$values = explode( "\n", $values );

			// Clean up values.
			$values = array_map( 'trim', $values );
			$values = array_filter( $values, 'strlen' );

			foreach ( $values as $value ) {
				preg_match( '/(.*)\|(.*)/', $value, $matches );
				$lines[$matches[1]] = $matches[2];
			}

			return $lines;
		}

		return false;
	}

    /**
     * @param int $uid the user gigya uid
     * @param array $counters the counter to increment.
     * @return array
     */
    public function incrementCounter($uid, $counters) {
        $params = array(
            'UID' => $uid,
            'counters' => json_encode($counters)
        );
        return $this->call('accounts.incrementCounters', $params);
    }

    public function isCounters() {
        $res = $this->call( 'accounts.getRegisteredCounters', array() );
        if ( $res === 403036 ) {
            return false;
        }

        return true;
    }

    public function isGm() {
        $res = $this->call( 'gm.getGlobalConfig', array() );
        if ( $res === 403036 ) {
            return false;
        }

        return true;
    }

	public static function isSpider() {
		// Add as many spiders you want in this array
		$spiders = array( 'Googlebot', 'Yammybot', 'Openbot', 'Yahoo', 'Slurp', 'msnbot', 'ia_archiver', 'Lycos', 'Scooter', 'AltaVista', 'Teoma', 'Gigabot', 'Googlebot-Mobile' );

		// Loop through each spider and check if it appears in
		// the User Agent
		foreach ( $spiders as $spider ) {
			if ( strpos( $_SERVER['HTTP_USER_AGENT'], $spider ) !== false ) {
				return TRUE;
			}
		}
		return FALSE;
	}

    public function _gigya_error_log($log){
        foreach ($log as $error) {
            $this->_logger->info('Gigya: ' . $error);
        }
    }

    public function _gigya_debug_log($log) {
        if (is_array($log) || is_object($log)) {
            $toLog = print_r($log, true);
        } else {
            $toLog = $log;
        }
        $this->_logger->info($toLog);
    }

    /**
     * @param mixed $api_domain
     */
    public function setApiDomain($api_domain)
    {
        $this->api_domain = $api_domain;
    }

    /**
     * @return mixed
     */
    public function getApiDomain()
    {
        return $this->api_domain;
    }

    /**
     * @param mixed $api_key
     */
    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->api_key;
    }

    /**
     * @param mixed $api_secret
     */
    public function setApiSecret($api_secret)
    {
        $this->api_secret = $api_secret;
    }

    /**
     * @return mixed
     */
    public function getApiSecret()
    {
        return $this->api_secret;
    }

    /**
     * @param mixed $user_key
     */
    public function setUserKey($user_key)
    {
        $this->user_key = $user_key;
    }

    /**
     * @return mixed
     */
    public function getUserKey()
    {
        return $this->user_key;
    }

    /**
     * @param mixed $user_secret
     */
    public function setUserSecret($user_secret)
    {
        $this->user_secret = $user_secret;
    }

    /**
     * @return mixed
     */
    public function getUserSecret()
    {
        return $this->user_secret;
    }

    /**
     * @param boolean $use_user_key
     */
    public function setUseUserKey($use_user_key)
    {
        $this->use_user_key = $use_user_key;
    }

    /**
     * @return boolean
     */
    public function getUseUserKey()
    {
        return $this->use_user_key;
    }

	public function getCommentsCategoryInfo($catID)
	{
		$params = array(
			"categoryID" => $catID,
			"includeConfigSections" => "highlightSettings"
		);
		$catInfo = $this->call('comments.getCategoryInfo', $params);

		return $catInfo;
	}

	/*
	 * contact gigya to add verified purchaser badge to comment (Magento)
	 *
	 * @param string $categoryID
	 * @param string $streamID
	 * @param string $commentID
	 *
	 * @return bool $badge_added [statusCode,errorCode,statusReason,callId]
	 */
	public function addCommentCategoryHighlight( $categoryID, $streamID, $commentID )
	{
		$params = array(
			"categoryID" => $categoryID,
			"streamID" => $streamID,
			"commentID" => $commentID,
			"addHighlightGroups" => '["Verified-Purchaser"]' // this exact format only works. no error returned if this is wrong (5.2.2)
		);
		$response = $this->call('comments.updateComment', $params);
		return $response;
	}

    ////////////////////////
	// encryption handling:
    ////////////////////////
    
    static public function decrypt($str, $key = null)
    {
        if (null == $key) {
            $key = getenv("GIGYAM2_KEK");
        }
        if (!empty($key)) {
            $iv_size       = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
            $strDec        = base64_decode($str);
            $iv            = substr($strDec, 0, $iv_size);
            $text_only     = substr($strDec, $iv_size);
            $plaintext_dec = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key,
                $text_only, MCRYPT_MODE_CBC, $iv);
//            return substr($plaintext_dec, 0, strpos($plaintext_dec, "\0"));
            return $plaintext_dec;
        }
        return $str;
    }
    
    static public function enc($str, $key = null)
    {
        if (null == $key) {
            $key = getenv("GIGYAM2_KEK");
        }
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
        $iv      = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $crypt   = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $str, MCRYPT_MODE_CBC, $iv);
        return trim(base64_encode($iv . $crypt));
    }
    
    static public function genKeyFromString($str = null) {
        if (null == $str) {
            $str = openssl_random_pseudo_bytes(32);
        }
        $salt = mcrypt_create_iv(16, MCRYPT_DEV_URANDOM);
        $key = hash_pbkdf2("sha256", $str, $salt, 1000, 32);
        return $key;
    }

}