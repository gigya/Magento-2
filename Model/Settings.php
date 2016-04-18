<?php
/*
 * Module CRUD developer interface with gigya_settings table
 */

namespace Gigya\GigyaM2\Model;

class Settings extends \Magento\Framework\Model\AbstractModel implements SettingsInterface, \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'gigya_gigyam2_settings';

    protected function _construct()
    {
        $this->_init('Gigya\GigyaM2\Model\ResourceModel\Settings');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}