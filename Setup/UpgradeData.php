<?php

namespace Gigya\GigyaIM\Setup;

use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Attribute as CustomerAttributeResourceModel;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Zend_Db_ExprFactory;

/**
 * UpgradeData
 *
 * It's displayed and editable on back office customer page detail.
 *
 * For testing field mapping from M2 to Gigya.
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var CustomerSetupFactory
     */
    protected $customerSetupFactory;

    /**
     * @var AttributeSetFactory
     */
    protected $attributeSetFactory;

	/**
	 * @var CustomerAttributeResourceModel
	 */
	protected $customerAttributeResourceModel;

    /**
     * @var Zend_Db_ExprFactory
     */
	protected $zendDbExprFactory;

	/**
	 * @param CustomerSetupFactory $customerSetupFactory
	 * @param AttributeSetFactory $attributeSetFactory
	 * @param CustomerAttributeResourceModel $customerAttributeResourceModel
     * @param Zend_Db_ExprFactory $zendDbExprFactory
	 */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory,
		CustomerAttributeResourceModel $customerAttributeResourceModel,
        Zend_Db_ExprFactory $zendDbExprFactory
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->customerAttributeResourceModel = $customerAttributeResourceModel;
        $this->zendDbExprFactory = $zendDbExprFactory;
    }

    /**
     * Upgrades data for a module
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
	 *
     * @return void
	 *
	 * @throws LocalizedException
	 * @throws \Exception
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '5.0.2') < 0) {

            /** @var CustomerSetup $customerSetup */
            $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

            $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');
            $attributeSetId = $customerEntity->getDefaultAttributeSetId();

            /** @var $attributeSet AttributeSet */
            $attributeSet = $this->attributeSetFactory->create();
            $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

            $customerSetup->addAttribute(Customer::ENTITY, 'gigya_username', [
                'type' => 'varchar',
                'label' => 'Gigya Username',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 1010,
                'position' => 1010,
                'system' => 0,
            ]);

            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'gigya_username')
                ->addData([
                    'attribute_set_id' => $attributeSetId,
                    'attribute_group_id' => $attributeGroupId,
                    'used_in_forms' => ['adminhtml_customer'],
                ]);

			$this->customerAttributeResourceModel->save($attribute);
        }

        if (version_compare($context->getVersion(), '5.0.5') < 0) {

            /** @var CustomerSetup $customerSetup */
            $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

            $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');
            $attributeSetId = $customerEntity->getDefaultAttributeSetId();

            /** @var $attributeSet AttributeSet */
            $attributeSet = $this->attributeSetFactory->create();
            $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

            $customerSetup->addAttribute(Customer::ENTITY, 'gigya_account_enriched', [
                'type' => 'int',
                'required' => false,
                'visible' => false,
                'user_defined' => true,
                'system' => 0,
            ]);

            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'gigya_account_enriched')
                ->addData([
                    'attribute_set_id' => $attributeSetId,
                    'attribute_group_id' => $attributeGroupId,
                    'used_in_forms' => [],
                ]);

			$this->customerAttributeResourceModel->save($attribute);
        }

        if (version_compare($context->getVersion(), '5.0.7') < 0) {

            /** @var CustomerSetup $customerSetup */
            $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

            $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');
            $attributeSetId = $customerEntity->getDefaultAttributeSetId();

            /** @var $attributeSet AttributeSet */
            $attributeSet = $this->attributeSetFactory->create();
            $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

            $customerSetup->addAttribute(Customer::ENTITY, 'gigya_subscribe', [
                'type' => 'int',
                'label' => 'Subscribe',
                'input' => 'select',
                'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'global' => 'Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 1020,
                'position' => 1020,
                'system' => 0,
            ]);

            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'gigya_subscribe')
                ->addData([
                    'attribute_set_id' => $attributeSetId,
                    'attribute_group_id' => $attributeGroupId,
                    'used_in_forms' => ['adminhtml_customer'],
                ]);

			$this->customerAttributeResourceModel->save($attribute);
        }

        if (version_compare($context->getVersion(), '5.5.0') < 0) {
            $connection = $setup->getConnection();
            $gigyaSettingsTable = $connection->getTableName('gigya_settings');
            $coreConfigDataTable = $connection->getTableName('core_config_data');
            $pathExpr = $this->zendDbExprFactory->create(['expression' => "'gigya_section/general/app_secret'"]);

            $select = $connection->select()
                ->from($gigyaSettingsTable, array($pathExpr, 'app_secret'))
                ->where('id = ?', 1);

            $insertSql = $connection->insertFromSelect($select, $coreConfigDataTable, ['path', 'value']);
            $connection->query($insertSql);

            $connection->dropTable($gigyaSettingsTable);
        }

        $setup->endSetup();
    }
}