<?php
namespace Gigya\GigyaIM\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * System / Cache Management / Cache type "Custom Cache Tag"
 */
class FieldMapping extends TagScope
{
    /**
     * Cache type code unique among all cache types
     */
    const string TYPE_IDENTIFIER = 'gigyaim_fieldmapping_cache';

    /**
     * Cache tag used to distinguish the cache type from all other cache
     */
    const string CACHE_TAG = 'GIGYAIM_FIELDMAPPING_TAG';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
