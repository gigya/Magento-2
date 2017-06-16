<?php
/**
 * Gigya IM Helper
 */
namespace Gigya\GigyaIM\Test\Helper;

use Gigya\CmsStarterKit\sdk\GSApiException;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Model\Session;
use \Magento\Framework\App\Helper\Context;
use \Gigya\GigyaIM\Logger\Logger;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;

class TestGigyaMageHelper extends GigyaMageHelper
{
    /**
     * @inheritdoc
     *
     * Will throw a GSApiException if config key gigya_section/test/gigya_test_gigya_update_error is true
     *
     * @throws GSApiException
     */
    public function updateGigyaAccount($uid, $profile = array(), $data = array())
    {
        if ($this->scopeConfig->getValue('gigya_section/test/gigya_test_gigya_update_error') == true) {
            throw new GSApiException(
                "For testing : force error on Gigya API update call",
                400009,
                "For testing : Gigya validation error");
        }

        parent::updateGigyaAccount($uid, $profile, $data);
    }
}
