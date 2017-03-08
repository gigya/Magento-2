<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Gigya\GigyaIM\Model\Config;

/**
 * Customer sharing config model
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class ValidateApikey extends \Magento\Framework\App\Config\Value
{

    /**
     * @var \Magento\Customer\Model\ResourceModel\Customer
     */
    protected $_customerResource;

    /** @var  \Magento\Store\Model\StoreManagerInterface */
    protected $_storeManager;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\ResourceModel\Customer $customerResource
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\ResourceModel\Customer $customerResource,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_storeManager = $storeManager;
        $this->_customerResource = $customerResource;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Check for email duplicates before saving customers sharing options
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        //@codingStandardsIgnoreStart
        throw new \Magento\Framework\Exception\LocalizedException(
            __(
                'We can\'t share customer accounts globally when the accounts share identical email addresses on more than one website.'
            )
        );
        //@codingStandardsIgnoreEnd
//        if ($value == self::SHARE_GLOBAL) {
//            if ($this->_customerResource->findEmailDuplicates()) {
//                //@codingStandardsIgnoreStart
//                throw new \Magento\Framework\Exception\LocalizedException(
//                    __(
//                        'We can\'t share customer accounts globally when the accounts share identical email addresses on more than one website.'
//                    )
//                );
//                //@codingStandardsIgnoreEnd
//            }
//        }
        return $this;
    }

}
