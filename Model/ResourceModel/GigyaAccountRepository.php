<?php

namespace Gigya\GigyaIM\Model\ResourceModel;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;

/**
 * GigyaAccountRepository
 *
 * @inheritdoc
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class GigyaAccountRepository implements GigyaAccountRepositoryInterface
{
	/** @var  GigyaAccountServiceInterface */
	protected $gigyaAccountService;

	/**
	 * GigyaAccountRepository constructor.
	 *
	 * @param GigyaAccountServiceInterface $gigyaAccountService
	 */
	public function __construct(GigyaAccountServiceInterface $gigyaAccountService) {
		$this->gigyaAccountService = $gigyaAccountService;
	}

	/**
	 * @inheritdoc
	 */
	function update($gigyaAccount) {
		if ($gigyaAccount->getUid())
		{
			$this->gigyaAccountService->update($gigyaAccount);
		}
	}

	/**
	 * @inheritdoc
	 */
	function get($uid) {
		return $this->gigyaAccountService->get($uid);
	}
}