<?php

namespace Gigya\GigyaIM\Model;

use Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping\CmsUpdater;
use Magento\Customer\Model\Data\Customer;

/**
 * AbstractMagentoFieldsUpdater
 *
 * @author      vlemaire <info@x2i.fr>
 */
abstract class AbstractMagentoFieldsUpdater extends CmsUpdater {

    /**
     * @var Customer
     */
    protected $magentoUser;

    /**
     * @return Customer
     */
    public function getMagentoUser() {

        return $this->magentoUser;
    }

    /**
     * @param Customer $magentoUser
     */
    public function setMagentoUser($magentoUser) {

        $this->magentoUser = $magentoUser;
    }
}