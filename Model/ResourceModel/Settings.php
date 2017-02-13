<?php
/**
 * Created by PhpStorm.
 * User: guy.av
 * Date: 18/04/2016
 * Time: 17:17
 */
namespace Gigya\GigyaIM\Model\ResourceModel;

class Settings extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        // name of the databse table, id column
        $this->_init('gigya_settings', 'id');
    }
}
