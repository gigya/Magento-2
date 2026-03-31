<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\user;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class GigyaSubscriptionContainer extends GigyaJsonObject
{
    /**
     * @var GigyaSubscription|null
     */
    private ?GigyaSubscription $email = null;

    /**
     * @return GigyaSubscription|null
     */
    public function getEmail(): ?GigyaSubscription
    {
        return $this->email;
    }

    /**
     * @param GigyaSubscription $email
     */
    public function setEmail($email): void
    {
        $this->email = $email;
    }

    /**
     * @return array|null
     */
    public function getSubscriptionAsArray(): ?array
    {
        $result = null;

        if ($this->getEmail()) {
            $result = $this->getEmail()->asArray();
        }

        return $result;
    }
}
