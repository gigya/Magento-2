<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Demo\Customer\Model;

use Magento\Customer\Model\Backend\Customer;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * ErrorOnMagentoCustomerSave
 *
 * For testing : will throw an exception after beforeSave if configuration key gigya_section/test/gigya_test_m2_update_error is set to true
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class ErrorOnMagentoCustomerSave
{
    /** @var ScopeConfigInterface  */
    protected $config;

    public function __construct(
        ScopeConfigInterface $config
    ) {
            $this->config = $config;
        }

     /**
     * @inheritdoc
     *
     * @throws \Exception If the configuration key gigya_section/test/gigya_test_m2_update_error is set to true
     */
    public function afterBeforeSave(Customer $subject)
    {
        if ($this->config->getValue('gigya_section/test/gigya_test_m2_update_error') == true) {
            throw new \Exception("For testing : error on Magento customer save.");
        }
    }
}