<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model;


use Gigya\CmsStarterKit\sdk\GSApiException;
use Gigya\CmsStarterKit\user\GigyaProfile;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\CmsStarterKit\user\GigyaUserFactory;
use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use \Magento\Framework\Event\ManagerInterface as EventManager;
use \Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * GigyaAccountService
 *
 * @inheritdoc
 *
 * CATODO :
 * review the mapping from GigyaUser to array for calling updateGigyaAccount
 * the best would be that a method self::update with a Gigya account data as a flatten array could be exposed
 * because as it's done for now we may not be able to update complex data, and diverge if the Gigya models change.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class GigyaAccountService implements GigyaAccountServiceInterface {

    /**
     * Event dispatched when the Gigya data have correctly been sent to the Gigya remote service.
     */
    const EVENT_UPDATE_GIGYA_SUCCESS = 'gigya_success_sync_to_gigya';

    /**
     * Event dispatched when the Gigya data could not be sent to the Gigya remote service or when this service replies with an error (validation or other functionnal error)
     */
    const EVENT_UPDATE_GIGYA_FAILURE = 'gigya_failed_sync_to_gigya';

    /** @var  GigyaMageHelper */
    protected $gigyaMageHelper;

    /** @var EventManager */
    protected $eventManager;

    /** @var  GigyaLogger */
    protected $logger;

    /** @var array All GigyaUser first level attributes but the profile */
    private $gigyaCoreAttributes = [];

    /** @var array All Gigya profile attributes */
    private $gigyaProfileAttributes = [];

    /** @var array Gigya profile attributes that shall not be updated (Gigya service validation rule) */
    private $gigyaProfileForbiddenAttributes = [ // CATODO : should be in config.xml
        // Dynamic fields
        'likes',
        'favorites',
        'skills',
        'education',
        'phones',
        'works',
        'publications'
    ];

    /**
     * Stores the latest GigyaUser instances get from the Gigya service.
     * Used for rollback needs.
     *
     * @var array of GigyaUser
     */
    private static $loadedGigyaUsers = [];

    /**
     * Stores the latest GigyaUser instances get from the Gigya service, and that have been updated to the Gigya service.
     * Used for rollback needs.
     *
     * @var array of GgigyaUser
     */
    private static $loadedAndUpdatedOriginalGigyaUsers = [];

    /**
     * GigyaAccountService constructor.
     *
     * @param GigyaMageHelper $gigyaMageHelper
     * @param EventManager $eventManager
     * @param GigyaLogger $logger
     */
    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        EventManager $eventManager,
        GigyaLogger $logger
    )
    {
        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->eventManager = $eventManager;
        $this->logger = $logger;

        $gigyaCoreMethods = get_class_methods(GigyaUser::class);
        foreach($gigyaCoreMethods as $gigyaCoreMethod) {
            if (strpos($gigyaCoreMethod, 'get') === 0) {
                $this->gigyaCoreAttributes[] = lcfirst(substr($gigyaCoreMethod, 3));
            }
        }

        $gigyaProfileMethods = get_class_methods(GigyaProfile::class);
        foreach($gigyaProfileMethods as $gigyaProfileMethod) {
            if (strpos($gigyaProfileMethod, 'get') === 0) {
                $this->gigyaProfileAttributes[] = lcfirst(substr($gigyaProfileMethod, 3));
            }
        }
    }

    /**
     * @inheritdoc
     *
     * Dispatch an event :
     * self::EVENT_UPDATE_GIGYA_SUCCESS
     * or self::EVENT_UPDATE_GIGYA_FAILURE
     */
    public function update($gigyaAccount)
    {
        $gigyaApiData = $this->buildEventData($gigyaAccount);

        try {

            /** @var string $uid */
            $uid = $gigyaApiData['uid'];

            // 1. Get current Gigya account data : they would be used if we have to perform a rollback
            // Those data could already have been successfully loaded in self::get, that's why we check their existence.
            if (!array_key_exists($uid, self::$loadedGigyaUsers)) {
                self::$loadedGigyaUsers[$uid] = $this->gigyaMageHelper->getGigyaAccountDataFromUid($uid);
            }

            // 2. Update the Gigya account
            $this->gigyaMageHelper->updateGigyaAccount(
                $uid,
                $gigyaApiData['profile'],
                $gigyaApiData['data']
            );

            // 3. If we reach this line that means the Gigya account has been successfully updated.
            // We store the previous data (got in 1.) if a rollback is needed (rollback would occur if Magento Customer save fails)
            self::$loadedAndUpdatedOriginalGigyaUsers[$uid] = self::$loadedGigyaUsers[$uid];
            unset(self::$loadedGigyaUsers[$uid]);

            $this->logger->debug(
                'Successful call to Gigya service api',
                $gigyaApiData
            );
            $this->eventManager->dispatch(self::EVENT_UPDATE_GIGYA_SUCCESS, [
                    'customer_entity_id' => $gigyaAccount->getCustomerEntityId()
                ]
            );
        } catch(GSApiException $e) {
            $message = $e->getMessage();
            $this->logger->error(
                'Failure encountered on call to Gigya service api',
                [
                    'gigya_data' => $gigyaApiData,
                    'exception' => [
                        'code' => $e->getCode(),
                        'message' => $message
                    ]
                ]
            );
            $this->eventManager->dispatch(self::EVENT_UPDATE_GIGYA_FAILURE, [
                    'customer_entity_id' => $gigyaAccount->getCustomerEntityId(),
                    'customer_entity_email' => $gigyaAccount->getCustomerEntityEmail(),
                    'gigya_data' => $gigyaApiData,
                    'message' => $message
                ]
            );
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    function get($uid)
    {
        unset(self::$loadedGigyaUsers[$uid]);

        $gigyaAccountData = $this->gigyaMageHelper->getGigyaAccountDataFromUid($uid);
        // For creating a new instance : the returned instance must not point to the instance that will be stored for an eventual rollback.
        $result = unserialize(serialize($gigyaAccountData));

        self::$loadedGigyaUsers[$uid] = $gigyaAccountData;

        return $result;
    }

    /**
     * @inheritdoc
     *
     * The Gigya data for this UID that will be sent for rollback to the Gigya service are the latest loaded with self::get and already successfully updated to Gigya with self::update.
     */
    function rollback($uid)
    {
        $gigyaUser = (array_key_exists($uid, self::$loadedAndUpdatedOriginalGigyaUsers)) ? self::$loadedAndUpdatedOriginalGigyaUsers[$uid] : null;
        if ($gigyaUser != null) {

            $this->update($gigyaUser);
        }
    }

    /**
     * Facility to build the profile data correctly formatted for the service call.
     *
     * @param GigyaUser $gigyaAccount
     * @return array
     */
    protected function getGigyaApiProfile(GigyaUser $gigyaAccount)
    {
        $profile = $gigyaAccount->getProfile();

        $result = [];

        foreach ($this->gigyaProfileAttributes as $gigyaProfileAttribute) {
            if (!in_array($gigyaProfileAttribute, $this->gigyaProfileForbiddenAttributes)) {
                $value = call_user_func(array($profile, 'get' . $gigyaProfileAttribute));
                if (!is_null($value)) {
                    $result[$gigyaProfileAttribute] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Builds the whole data correctly formatted for the service call.
     *
     * @param GigyaUser $gigyaAccount
     * @return array With entries uid, profile, data
     */
    protected function buildEventData(GigyaUser $gigyaAccount)
    {
        return [
            'uid' => $gigyaAccount->getUid(),
            'profile' => $this->getGigyaApiProfile($gigyaAccount),
            'data' => $gigyaAccount->getData()
        ];
    }
}
