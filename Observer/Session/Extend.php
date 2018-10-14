<?php

namespace Gigya\GigyaIM\Observer\Session;

use Gigya\GigyaIM\Model\Session\Extend as ExtendModel;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Gigya\GigyaIM\Model\Config as GigyaConfig;

class Extend implements ObserverInterface
{
	/**
	 * @var ExtendModel
	 */
	protected $sessionExtendModel;

	/** @var GigyaConfig */
	protected $config;

	/**
	 * @param ExtendModel $sessionExtendModel
	 * @param GigyaConfig $config
	 */
	public function __construct(ExtendModel $sessionExtendModel, GigyaConfig $config)
	{
		$this->sessionExtendModel = $sessionExtendModel;
		$this->config = $config;
	}

	/**
	 * @param Observer $observer
	 * @return void
	 */
	public function execute(Observer $observer)
	{
		if ($this->config->isGigyaEnabled())
		{
			/* @var $request \Magento\Framework\App\RequestInterface */
			$request = $observer->getEvent()->getRequest();
			if ($request->isAjax()) {
				$this->sessionExtendModel->extendSession();
			}
		}
	}
}