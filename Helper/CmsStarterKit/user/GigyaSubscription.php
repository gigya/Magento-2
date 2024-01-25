<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\user;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class GigyaSubscription extends GigyaJsonObject
{
    /**
     * @var boolean
     */
    private bool $isSubscribed;

    /**
     * @var array
     */
    private array $tags;

    /**
     * @var string
     */
    private string $lastUpdatedSubscriptionState;

    /**
     * @var GigyaSubscriptionDoubleOptIn
     */
    private GigyaSubscriptionDoubleOptIn $doubleOptIn;

    /**
     * @return boolean
     */
    public function getIsSubscribed(): bool
    {
        return $this->isSubscribed;
    }

    /**
     * @param boolean $isSubscribed
     */
    public function setIsSubscribed($isSubscribed): void
    {
        $this->isSubscribed = $isSubscribed;
    }

    /**
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string|array $tags
     */
    public function setTags($tags): void
    {
        if (is_string($tags)) {
            $tags = json_decode($tags);
        }
        $this->tags = $tags;
    }

    /**
     * @return string
     */
    public function getLastUpdatedSubscriptionState(): string
    {
        return $this->lastUpdatedSubscriptionState;
    }

    /**
     * @param string $lastUpdatedSubscriptionState
     */
    public function setLastUpdatedSubscriptionState($lastUpdatedSubscriptionState): void
    {
        $this->lastUpdatedSubscriptionState = $lastUpdatedSubscriptionState;
    }

    /**
     * @return GigyaSubscriptionDoubleOptIn
     */
    public function getDoubleOptIn(): GigyaSubscriptionDoubleOptIn
    {
        return $this->doubleOptIn;
    }

    /**
     * @param GigyaSubscriptionDoubleOptIn|array $doubleOptIn
     */
    public function setDoubleOptIn($doubleOptIn): void
    {
        if (is_array($doubleOptIn)) {
            $doubleOptInObject = new GigyaSubscriptionDoubleOptIn(null);

            /** @var array $doubleOptIn */
            foreach ($doubleOptIn as $key => $value) {
                $methodName   = 'set' . ucfirst($key);
                $methodParams = $value;
                $doubleOptInObject->$methodName($methodParams);
            }
        } else {
            $doubleOptInObject = $doubleOptIn;
        }

        $this->doubleOptIn = $doubleOptInObject;
    }

    /**
     * @return array|null
     */
    public function getDoubleOptInAsArray(): ?array
    {
        $result = null;

        if ($this->getDoubleOptIn()) {
            $result = $this->getDoubleOptIn()->asArray();
        }

        return $result;
    }

    /**
     * @return array
     */
    public function asArray(): array
    {
        return [
            'isSubscribed'                 => $this->getIsSubscribed(),
            'tags'                         => $this->getTags(),
            'lastUpdatedSubscriptionState' => $this->getLastUpdatedSubscriptionState(),
            'doubleOptIn'                  => $this->getDoubleOptInAsArray(),
        ];
    }
}
