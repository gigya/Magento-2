<?php
/*
 * Module CRUD developer interface with gigya_settings table
 */

namespace Gigya\GigyaIM\Model;

use \Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;

class Settings extends AbstractModel implements SettingsInterface, IdentityInterface
{
    const CACHE_TAG = 'Gigya_GigyaIM_settings';

    protected function _construct()
    {
        $this->_init('Gigya\GigyaIM\Model\ResourceModel\Settings');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
