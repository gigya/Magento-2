<?php
namespace Gigya\GigyaM2\Setup;

use Magento\Framework\Module\Setup\Migration;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Customer\Model\Customer;

class InstallData implements InstallDataInterface
{
    /**
     * Customer setup factory
     *
     * @var \Magento\Customer\Setup\CustomerSetupFactory
     */
    private $customerSetupFactory;
    /**
     * Init
     *
     * @param \Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(\Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory)
    {
        $this->customerSetupFactory = $customerSetupFactory;
    }
    /**
     * Installs DB schema for a module
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {

        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        $setup->startSetup();

        $customerSetup->addAttribute(
            Customer::ENTITY,
            "gigya_uid",
            array(
                "type"     => "varchar",
                "backend"  => "",
                "label"    => "Gigya UID",
                "input"    => "text",
                "source"   => "",
                "visible"  => true,
                "required" => false,
                "default" => "",
                "frontend" => "",
                "unique"     => false,
                "note"       => ""
            ));

        $gigyaUidAttribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'gigya_uid');
        $gigyaUidAttribute->setData(
            'used_in_forms',
            ['adminhtml_customer', 'customer_account_edit']
        );

        $gigyaUidAttribute->save();

        $setup->endSetup();
    }
}