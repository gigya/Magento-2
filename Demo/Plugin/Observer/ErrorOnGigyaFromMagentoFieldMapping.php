<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Demo\Plugin\Observer;

use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Observer\GigyaFromMagentoFieldMapping;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ErrorOnGigyaFromMagentoFieldMapping
{
    /** @var ScopeConfigInterface */
    protected $config;

    public function  __construct(
        ScopeConfigInterface $config
    ) {
        $this->config = $config;
    }

    /**
     * Will throw a GigyaFieldMappingException if config key gigya_section/test/gigya_test_fieldmapping_cms2g_error is true
     *
     * @param GigyaFromMagentoFieldMapping $subject
     * @param $observer \Magento\Framework\Event\Observer
     * @throws GigyaFieldMappingException
     */
    public function beforeExecute(
        GigyaFromMagentoFieldMapping $subject,
        \Magento\Framework\Event\Observer $observer
    )
    {
        if ($this->config->getValue('gigya_section/test/gigya_test_fieldmapping_cms2g_error') == true) {
            throw new GigyaFieldMappingException();
        }
    }
}