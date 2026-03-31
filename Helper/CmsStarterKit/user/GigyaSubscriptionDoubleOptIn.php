<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\user;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class GigyaSubscriptionDoubleOptIn extends GigyaJsonObject
{
    /**
     * @var string|null
     */
    private ?string $emailSentTime = null;

    /**
     * @var string|null
     */
    private ?string $confirmTime = null;

    /**
     * @var string|null
     */
    private ?string $status = null;

    /**
     * @return string|null
     */
    public function getEmailSentTime(): ?string
    {
        return $this->emailSentTime;
    }

    /**
     * @param string $emailSentTime
     */
    public function setEmailSentTime($emailSentTime): void
    {
        $this->emailSentTime = $emailSentTime;
    }

    /**
     * @return string|null
     */
    public function getConfirmTime(): ?string
    {
        return $this->confirmTime;
    }

    /**
     * @param string $confirmTime
     */
    public function setConfirmTime($confirmTime): void
    {
        $this->confirmTime = $confirmTime;
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status): void
    {
        $this->status = $status;
    }

    /**
     * @return array
     */
    public function asArray(): array
    {
        return [
            'emailSentTime' => $this->getEmailSentTime(),
            'confirmTime'   => $this->getConfirmTime(),
            'status'        => $this->getStatus(),
        ];
    }
}
