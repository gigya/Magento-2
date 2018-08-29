<?php

namespace Gigya\GigyaIM\Model\Config\Backend;

use Magento\Framework\App\Config\ValueFactory;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

class DeletionFrequency extends \Magento\Framework\App\Config\Value
{
	const CRON_STRING_PATH = 'crontab/default/jobs/gigya_user_deletion_job/schedule/cron_delete';
	const CRON_MODEL_PATH = 'crontab/default/jobs/gigya_user_deletion_job/run/model';

	/** @var ValueFactory */
	protected $_configValueFactory;

	/** @var string */
	protected $_runModelPath;

	/**
	 * @param \Magento\Framework\Model\Context $context
	 * @param \Magento\Framework\Registry $registry
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
	 * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
	 * @param \Magento\Framework\App\Config\ValueFactory $configValueFactory
	 * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
	 * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
	 * @param string $runModelPath
	 * @param array $data
	 */
	public function __construct(
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\App\Config\ScopeConfigInterface $config,
		\Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
		ValueFactory $configValueFactory,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		string $runModelPath = '',
		array $data = []
	) {
		$this->_runModelPath = $runModelPath;
		$this->_configValueFactory = $configValueFactory;

		parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);

	}

	/**
	 * @return \Magento\Framework\App\Config\Value
	 *
	 * @throws \Exception
	 */
	public function afterSave()
	{
		$time = ['*', '*'];
		$frequency = $this->getData('groups/deletion_general/fields/deletion_job_frequency/value');

		$cronExprArray = [
			intval($time[1]), // Minute
			intval($time[0]), // Hour
			$frequency == \Magento\Cron\Model\Config\Source\Frequency::CRON_MONTHLY ? '1' : '*', // Day of the Month
			'*', // Month of the Year
			$frequency == \Magento\Cron\Model\Config\Source\Frequency::CRON_WEEKLY ? '1' : '*', // Day of the Week
		];

		$cronExprString = implode(' ', $cronExprArray);

		try {
			$this->_configValueFactory->create()
				->load(self::CRON_STRING_PATH, 'path')
				->setValue($cronExprString)
				->setPath(self::CRON_STRING_PATH)
				->save();

			$this->_configValueFactory->create()
				->load(self::CRON_MODEL_PATH, 'path')
				->setValue($this->_runModelPath)
				->setPath(self::CRON_MODEL_PATH)
				->save();
		} catch (\Exception $e) {
			throw new \Exception(__('We can\'t save the cron expression.'));
		}

		return parent::afterSave();
	}
}