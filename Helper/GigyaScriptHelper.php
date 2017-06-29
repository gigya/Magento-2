<?php
/**
 * Gigya JavaScript Path Helper
 */
namespace Gigya\GigyaIM\Helper;

use Gigya\GigyaIM\Model\Config\Source\Domain;
use Magento\Framework\App\Helper\AbstractHelper;

class GigyaScriptHelper extends AbstractHelper
{
    /**
     * @var GigyaMageHelper
     */
    protected $gigyaMageHelper;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param GigyaMageHelper $gigyaMageHelper
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        GigyaMageHelper $gigyaMageHelper
    ) {
        parent::__construct($context);
        $this->gigyaMageHelper = $gigyaMageHelper;
    }

    /**
     * Get the domain name to use when retrieving the JS file, if it is different from gigya.com.
     *
     * @return bool|string
     */
    public function getGigyaScriptDomain()
    {
        $domain = $this->gigyaMageHelper->getApiDomain();
        switch ($domain) {
            case Domain::DC_CN:
                return 'cn1.gigya-api.cn';
            default:
                return false;
        }
    }
}