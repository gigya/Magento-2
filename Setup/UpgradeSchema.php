<?php

namespace Gigya\GigyaIM\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * UpgradeSchema
 *
 * Create table gigya_sync_retry for automatic update retry on synchronizing error Gigya vs Magento
 *
 * @author      vlemaire <info@x2i.fr>
 */
class UpgradeSchema implements \Magento\Framework\Setup\UpgradeSchemaInterface
{
    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws \Zend_Db_Exception
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '5.0.1') < 0) {

            // BEGIN : Create table gigya_sync_retry
            $table = $installer->getConnection()->newTable(
                $installer->getTable('gigya_sync_retry')
            )->addColumn(
                'customer_entity_id',
                Table::TYPE_INTEGER,
                null,
                [ 'identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true ],
                'Customer Entity Id'
            )->addColumn(
                'direction',
                Table::TYPE_TEXT,
                5, // CMS2G, G2CMS, BOTH
                [ 'nullable' => false ],
                'Synchronize direction : CMS2G, G2CMS, BOTH'
            )->addColumn(
                'data',
                Table::TYPE_TEXT,
                null,
                [ 'nullable' => false ],
                'Data that failed to synchronize'
            )->addColumn(
                'message',
                Table::TYPE_TEXT,
                null,
                [ 'nullable' => false ],
                'The error message that compromised the synchronizing'
            )->addColumn(
                'retry_count',
                Table::TYPE_INTEGER,
                null,
                [ 'nullable' => false, 'default' => 0 ],
                'The attempt count to re synchronize the data'
            )->addColumn(
                'date',
                \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                null,
                [ 'nullable' => true ],
                'Date time of the last attempt to re synchronize the data'
            )->addForeignKey(
                $installer->getFkName(
                    $installer->getTable('gigya_sync_retry'),
                    'customer_entity_id',
                    $installer->getTable('customer_entity'),
                    'entity_id'
                ),
                'customer_entity_id',
                $installer->getTable('customer_entity'),
                'entity_id',
                AdapterInterface::FK_ACTION_CASCADE
            );

            $installer->getConnection()->createTable($table);
            // END : Create table gigya_sync_retry
        } // END : if version < 5.0.1

        if (version_compare($context->getVersion(), '5.0.3') < 0) {

            // BEGIN : Add column 'gigya_uid' to table 'gigya_sync_retry'
            $setup->getConnection()->addColumn(
                $setup->getTable('gigya_sync_retry'),
                'gigya_uid',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => false,
                    'after' => 'customer_entity_id',
                    'comment' => 'The Gigya UID of this customer account'
                ]
            );
            // END : Add column 'gigya_uid' to table 'gigya_sync_retry'
        } // END : if version < 5.0.3

        if (version_compare($context->getVersion(), '5.0.4') < 0) {

            // BEGIN : Add column 'customer_entity_email' to table 'gigya_sync_retry'
            $setup->getConnection()->addColumn(
                $setup->getTable('gigya_sync_retry'),
                'customer_entity_email',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => false,
                    'after' => 'customer_entity_id',
                    'comment' => 'The email set on this Magento customer account'
                ]
            );
            // END : Add column 'customer_entity_email' to table 'gigya_sync_retry'
        } // END : if version < 5.0.4

        if (version_compare($context->getVersion(), '5.0.6') < 0) {

            // BEGIN : Rename column 'gigya_sync_retry.direction' to 'origin'
            $setup->getConnection()->changeColumn(
                $setup->getTable('gigya_sync_retry'),
                'direction',
                'origin',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 5, // CMS, GIGYA
                    'nullable' => false,
                    'comment' => 'The failure origin that produced this retry entry'
                ]
            );
            // END : Rename column 'gigya_sync_retry.direction' to 'origin'
        } // END : if version < 5.0.6

		/* Gigya user deletion */
		if (version_compare($context->getVersion(), '5.1.0') < 0) {
			$table = $installer->getConnection()->newTable(
				$installer->getTable('gigya_user_deletion')
			)->addColumn(
				'filename',
				Table::TYPE_TEXT,
				255,
				[ 'nullable' => false, 'primary' => true ],
				'File name processed'
			)->addColumn(
				'time_processed',
				Table::TYPE_INTEGER,
				null,
				[ 'nullable' => false ],
				'Timestamp of the cron run when this file was successfully processed'
			);

			$installer->getConnection()->createTable($table);
			// END : Create table gigya_user_deletion
		} // END : if version < 5.1.0

        $installer->endSetup();
    }
}
