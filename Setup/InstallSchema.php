<?php

namespace Gigya\GigyaIM\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
	/**
	 * @param SchemaSetupInterface   $setup
	 * @param ModuleContextInterface $context
	 *
	 * @throws \Zend_Db_Exception
	 */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        $installer->endSetup();
    }
}
