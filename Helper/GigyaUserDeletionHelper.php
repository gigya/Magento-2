<?php

namespace Gigya\GigyaIM\Helper;

use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\FilterBuilder;

class GigyaUserDeletionHelper extends AbstractHelper
{
	/** @var SearchCriteriaInterface  */
	protected $searchCriteria;

	/** @var FilterGroup */
	protected $filterGroup;

	/** @var FilterBuilder */
	protected $filterBuilder;

	/** @var CustomerRepository */
	protected $customerRepository;

	/**
	 * GigyaUserDeletionHelper constructor.
	 *
	 * @param Context $context
	 * @param CustomerRepository $customerRepository
	 * @param SearchCriteriaInterface $criteria
	 * @param FilterGroup $filterGroup
	 * @param FilterBuilder $filterBuilder
	 */
	public function __construct(
		Context $context,
		CustomerRepository $customerRepository,
		SearchCriteriaInterface $criteria,
		FilterGroup $filterGroup,
		FilterBuilder $filterBuilder
	)
	{
		parent::__construct($context);

		$this->customerRepository = $customerRepository;
		$this->searchCriteria = $criteria;
		$this->filterGroup = $filterGroup;
		$this->filterBuilder = $filterBuilder;
	}

	/**
	 * @param $attributeCode
	 * @param $value
	 *
	 * @return \Magento\Customer\Api\Data\CustomerInterface[]
	 *
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function getCustomersByAttributeValue($attributeCode, $value)
	{
		$this->filterGroup->setFilters(
			[
				$this->filterBuilder
					->setField($attributeCode)
					->setConditionType('eq')
					->setValue($value)
					->create()
			]
		);

		$this->searchCriteria->setFilterGroups([$this->filterGroup]);
		$customersList = $this->customerRepository->getList($this->searchCriteria);
		$customers = $customersList->getItems();

		return $customers;
	}
}