<?php
/**
 * Gigya JavaScript Path Helper
 */
namespace Gigya\GigyaIM\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class GigyaScriptHelper extends AbstractHelper
{
    /**
     * @var GigyaMageHelper
     */
    public GigyaMageHelper $gigyaMageHelper;

    /**
     * @param Context $context
     * @param GigyaMageHelper $gigyaMageHelper
     */
    public function __construct(
        Context $context,
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
    public function getGigyaScriptDomain(): bool|string
    {
        $domain = $this->gigyaMageHelper->getApiDomain();

        $parsed_domain = explode('.', $domain);
        if (array_pop($parsed_domain) . array_pop($parsed_domain) == 'gigya.com') {
            return false;
        }

        return $domain;
    }
}
