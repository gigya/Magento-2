<?php

namespace Gigya\GigyaIM\Model;

use \Magento\Framework\Session\SessionManager;

class Session extends SessionManager
{
    public function setLoginToken($loginToken)
    {
        $this->storage->setData('login_token', $loginToken);
        return $this;
    }

    public function getLoginToken()
    {
        if ($this->storage->getData('login_token')) {
            return $this->storage->getData('login_token');
        }
        return null;
    }
}