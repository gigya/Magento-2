<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\user;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class GigyaUser extends GigyaJsonObject
{
    /**
     * @var string
     */
    private string $UID;

    /**
     * @var string
     */
    private string $UIDSignature;

    /**
     * @var boolean
     */
    private bool $isSiteUser;

    /**
     * @var boolean
     */
    private bool $isTempUser;

    /**
     * @var boolean
     */
    private bool $isSiteUID;

    /**
     * @var string
     */
    private string $loginProvider;

    /**
     * @var string
     */
    private string $loginProviderUID;

    /**
     * @var string
     */
    private string $oldestDataAge;

    /**
     * @var int
     */
    private int $oldestDataUpdatedTimestamp;

    /**
     * @var int
     */
    private int $signatureTimestamp;

    /**
     * @var string
     */
    private string $statusCode;

    /**
     * @var string
     */
    private string $statusReason;

    /**
     * @var int
     */
    private int $lastUpdatedTimestamp;

    /**
     * @var string
     */
    private string $socialProviders;

    /**
     * @var array
     */
    private array $providers;

    /**
     * @var string
     */
    private string $isActive;

    /**
     * @var array
     */
    private array $loginIDs;

    /**
     * @var GigyaProfile
     */
    private GigyaProfile $profile;

    /**
     * @var string
     */
    private string $time;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var array
     */
    private array $subscriptions;

    /**
     * @var boolean
     */
    private bool $isVerified;

    /**
     * @return string
     */
    public function getUID(): string
    {
        return $this->UID;
    }

    /**
     * @param string $UID
     */
    public function setUID($UID): void
    {
        $this->UID = $UID;
    }

    /**
     * @return string
     */
    public function getUIDSignature(): string
    {
        return $this->UIDSignature;
    }

    /**
     * @param string $UIDSignature
     */
    public function setUIDSignature($UIDSignature): void
    {
        $this->UIDSignature = $UIDSignature;
    }

    /**
     * @return boolean
     */
    public function isIsSiteUser(): bool
    {
        return $this->isSiteUser;
    }

    /**
     * @param boolean $isSiteUser
     */
    public function setIsSiteUser($isSiteUser): void
    {
        $this->isSiteUser = $isSiteUser;
    }

    /**
     * @return boolean
     */
    public function isIsTempUser(): bool
    {
        return $this->isTempUser;
    }

    /**
     * @param boolean $isTempUser
     */
    public function setIsTempUser($isTempUser): void
    {
        $this->isTempUser = $isTempUser;
    }

    /**
     * @return boolean
     */
    public function isIsSiteUID(): bool
    {
        return $this->isSiteUID;
    }

    /**
     * @param boolean $isSiteUID
     */
    public function setIsSiteUID($isSiteUID): void
    {
        $this->isSiteUID = $isSiteUID;
    }

    /**
     * @return string
     */
    public function getLoginProvider(): string
    {
        return $this->loginProvider;
    }

    /**
     * @param string $loginProvider
     */
    public function setLoginProvider($loginProvider): void
    {
        $this->loginProvider = $loginProvider;
    }

    /**
     * @return string
     */
    public function getLoginProviderUID(): string
    {
        return $this->loginProviderUID;
    }

    /**
     * @param string $loginProviderUID
     */
    public function setLoginProviderUID($loginProviderUID): void
    {
        $this->loginProviderUID = $loginProviderUID;
    }

    /**
     * @return string
     */
    public function getOldestDataAge(): string
    {
        return $this->oldestDataAge;
    }

    /**
     * @param string $oldestDataAge
     */
    public function setOldestDataAge($oldestDataAge): void
    {
        $this->oldestDataAge = $oldestDataAge;
    }

    /**
     * @return int
     */
    public function getOldestDataUpdatedTimestamp(): int
    {
        return $this->oldestDataUpdatedTimestamp;
    }

    /**
     * @param int $oldestDataUpdatedTimestamp
     */
    public function setOldestDataUpdatedTimestamp($oldestDataUpdatedTimestamp): void
    {
        $this->oldestDataUpdatedTimestamp = $oldestDataUpdatedTimestamp;
    }

    /**
     * @return int
     */
    public function getSignatureTimestamp(): int
    {
        return $this->signatureTimestamp;
    }

    /**
     * @param int $signatureTimestamp
     */
    public function setSignatureTimestamp($signatureTimestamp): void
    {
        $this->signatureTimestamp = $signatureTimestamp;
    }

    /**
     * @return string
     */
    public function getStatusCode(): string
    {
        return $this->statusCode;
    }

    /**
     * @param string $statusCode
     */
    public function setStatusCode($statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return string
     */
    public function getStatusReason(): string
    {
        return $this->statusReason;
    }

    /**
     * @param string $statusReason
     */
    public function setStatusReason($statusReason): void
    {
        $this->statusReason = $statusReason;
    }

    /**
     * @return int
     */
    public function getLastUpdatedTimestamp(): int
    {
        return $this->lastUpdatedTimestamp;
    }

    /**
     * @param int $lastUpdatedTimestamp
     */
    public function setLastUpdatedTimestamp($lastUpdatedTimestamp): void
    {
        $this->lastUpdatedTimestamp = $lastUpdatedTimestamp;
    }

    /**
     * @return string
     */
    public function getSocialProviders(): string
    {
        return $this->socialProviders;
    }

    /**
     * @param string $socialProviders
     */
    public function setSocialProviders($socialProviders): void
    {
        $this->socialProviders = $socialProviders;
    }

    /**
     * @return array
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * @param array $providers
     */
    public function setProviders($providers): void
    {
        $this->providers = $providers;
    }

    /**
     * @return string
     */
    public function getIsActive(): string
    {
        return $this->isActive;
    }

    /**
     * @param string $isActive
     */
    public function setIsActive($isActive): void
    {
        $this->isActive = $isActive;
    }

    /**
     * @return array
     */
    public function getLoginIDs(): array
    {
        return $this->loginIDs;
    }

    /**
     * @param array $loginIDs
     */
    public function setLoginIDs($loginIDs): void
    {
        $this->loginIDs = $loginIDs;
    }

    /**
     * @return GigyaProfile
     */
    public function getProfile(): GigyaProfile
    {
        return $this->profile;
    }

    /**
     * @param array|GigyaProfile $profile
     */
    public function setProfile($profile): void
    {
        if (is_array($profile)) {
            $profile = GigyaUserFactory::createGigyaProfileFromArray($profile);
        }
        $this->profile = $profile;
    }

    /**
     * @return string
     */
    public function getTime(): string
    {
        return $this->time;
    }

    /**
     * @param string $time
     */
    public function setTime($time): void
    {
        $this->time = $time;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData($data): void
    {
        if (is_array($this->data)) {
            $this->data = array_merge($this->data, $data);
        } else {
            $this->data = $data;
        }
    }

    /**
     * @return string emailLoginId / null
     */
    public function getGigyaLoginId(): string
    {
        $loginIds = $this->getLoginIDs();
        if (!empty($loginIds['emails'][0])) {
            $emailLoginId = $loginIds['emails'][0];
        } else {
            $emailLoginId = $this->getProfile()->getEmail();
        }

        return $emailLoginId;
    }

    /**
     * @param $path : . (dot) separated string
     *
     * @return GigyaUser|string
     */
    public function getNestedValue($path): GigyaUser|string|null|static
    {
        $keys    = explode('.', $path);
        $accData = $this;
        foreach ($keys as $key) {
            if (is_object($accData)) {
                $accData = $accData->__get('get' . ucfirst($key));
            } elseif (is_array($accData) and isset($accData[$key])) {
                $accData = $accData[$key];
            } elseif (is_null($accData) || !isset($accData[$key])) { // there is no such key
                return null;
            }
        }
        if (is_array($accData) or is_object($accData)) {
            $accData = json_encode($accData, JSON_UNESCAPED_SLASHES);
        }

        return $accData;
    }

    /**
     * @return array
     */
    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }

    /**
     * @param array $subscriptions array of subscription
     *
     * @see GigyaSubscriptionContainer
     */
    public function setSubscriptions($subscriptions): void
    {
        $this->subscriptions = $subscriptions;
    }

    /**
     * @param int               $id           subscription ID
     * @param GigyaSubscription $subscription subscription data (isSubscribed,
     *                                        tags, lastUpdatedSubscriptionState, doubleOptIn)
     *
     * @see GigyaSubscription
     */
    public function addSubscription($id, $subscription): void
    {
        $subscriptionContainer = new GigyaSubscriptionContainer(null);
        $subscriptionContainer->setEmail($subscription);

        $this->subscriptions[$id] = $subscriptionContainer;
    }

    /**
     * @param int $id subscription ID
     *
     * @return GigyaSubscription|null
     */
    public function getSubscriptionById($id): ?GigyaSubscription
    {
        $result = null;

        if (count($this->getSubscriptions())) {
            $subscriptions = $this->getSubscriptions();

            if (array_key_exists($id, $subscriptions)) {
                $result = $subscriptions[$id]->getEmail();
            }
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function getIsVerified(): bool
    {
        return $this->isVerified;
    }

    /**
     * @param bool $isVerified
     */
    public function setIsVerified($isVerified): void
    {
        $this->isVerified = $isVerified;
    }

    public function __toString()
    {
        return json_encode(get_object_vars($this));
    }
}
