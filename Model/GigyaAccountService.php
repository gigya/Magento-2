<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model;


use Gigya\CmsStarterKit\sdk\GSApiException;
use Gigya\CmsStarterKit\user\GigyaProfile;
use Gigya\CmsStarterKit\user\GigyaUser;
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

    /** @var array Gigya user attributes that shall not be updated (Gigya service validation rule) */
    private $gigyaCoreForbiddenAttributes = [ // CATODO : should be in config.xml
        'UID',
        'UIDSignature',
        'signatureTimestamp',
        'emails',
        'identities',
        // not forbidden by Gigya : for special mapping purpose
        'profile',
        'loginIDs',
        'nestedValue',
        // not forbidden by Gigya : Magento internal entity id
        'customerEntityId'
    ];

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

            $this->gigyaMageHelper->updateGigyaAccount(
                $gigyaApiData['uid'],
                $gigyaApiData['profile'],
                $gigyaApiData['data']
            );
            $this->logger->debug(
                'Successful call to Gigya service api',
                $gigyaApiData
            );
            $this->eventManager->dispatch(self::EVENT_UPDATE_GIGYA_SUCCESS, [
                    'customer_entity_id' => $gigyaAccount->getCustomerEntityId()
                ]
            );
        } catch(GSApiException $e) {
            $message = $e->getLongMessage();
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
                    'gigya_data' => $gigyaApiData,
                    'message' => $message
                ]
            );
            throw $e;
        }
    }

    function get($uid)
    {
        return $this->gigyaMageHelper->getGigyaAccountDataFromUid($uid);
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
     * Facility to build the core data correctly formatted for the service call.
     *
     * @param GigyaUser $gigyaAccount
     * @return array
     */
    protected function getGigyaApiCoreData(GigyaUser $gigyaAccount)
    {
        $result = [ 'loginIDs' => $gigyaAccount->getLoginIDs()['emails'] ];

        foreach ($this->gigyaCoreAttributes as $gigyaCoreAttribute) {
            if (!in_array($gigyaCoreAttribute, $this->gigyaCoreForbiddenAttributes)) {
                $value = call_user_func(array($gigyaAccount, 'get' . $gigyaCoreAttribute));
                if (!is_null($value)) {
                    $result[$gigyaCoreAttribute] = $value;
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
            'data' => $this->getGigyaApiCoreData($gigyaAccount)
        ];
    }
}