<?php

/*
 * Create gigya_settings DB table. 
 */

namespace Gigya\GigyaIM\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        // Create gigya_settings table
        $table = $installer->getConnection()->newTable(
            $installer->getTable('gigya_settings')
        )
            ->addColumn(
                'id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true
                ],
                'Setting ID'
            )
            ->addColumn(
                'app_secret',
                Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'Application Secret'
            )
            ->addColumn(
                'is_active',
                Table::TYPE_SMALLINT,
                null,
                array (
                    'nullable' => false,'default' => '1',
                ),
                'Is Active'
            )
            ->setComment('Gigya Settings');
        $installer->getConnection()->createTable($table);
        $installer->endSetup();
    }
}
