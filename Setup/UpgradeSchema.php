<?php
namespace Gigya\GigyaM2\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        // Create gigya_settings table
        $table = $installer->getConnection()->newTable(
            $installer->getTable('gigya_settings')
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true
            ],
            'ID'
        )->addColumn(
                'api_key',
                Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'API Key'
        )->addColumn(
            'api_domain',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false, 'default' => ''],
            'API Domain'
        )->addColumn(
            'app_key',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false, 'default' => ''],
            'Application Key'
        )->addColumn(
            'app_secret',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false, 'default' => ''],
            'Application Secret'
        )->addForeignKey(
            $installer->getFkName('gigya_settings', 'store_id', 'store', 'store_id'),
            'store_id',
            $installer->getTable('store'),
            'store_id',
            \Magento\Framework\DB\Ddl\Table::ACTION_SET_NULL
        )->addForeignKey(
            $installer->getFkName('gigya_settings', 'website_id', 'store_website', 'website_id'),
            'website_id',
            $installer->getTable('store_website'),
            'website_id',
            \Magento\Framework\DB\Ddl\Table::ACTION_SET_NULL
        )
        ->setComment('Gigya Settings');

        $installer->getConnection()->createTable($table);
        $installer->endSetup();
    }
}