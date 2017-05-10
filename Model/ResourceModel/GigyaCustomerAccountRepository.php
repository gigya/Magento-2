<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\ResourceModel;

use Gigya\GigyaIM\Api\Data\GigyaCustomerAccountInterface;
use Gigya\GigyaIM\Api\GigyaCustomerAccountRepositoryInterface;
use Gigya\GigyaIM\Api\GigyaCustomerAccountServiceInterface;

/**
 * GigyaCustomerAccountRepository
 *
 * @inheritdoc
 *
 * This is a base that will evolve soon while new features are developed.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class GigyaCustomerAccountRepository implements GigyaCustomerAccountRepositoryInterface
{
    /** @var  GigyaCustomerAccountServiceInterface */
    protected $gigyaCustomerAccountService;

    /**
     * GigyaCustomerAccountRepository constructor.
     *
     * @param GigyaCustomerAccountServiceInterface $gigyaCustomerAccountService
     */
    public function __construct(
        GigyaCustomerAccountServiceInterface $gigyaCustomerAccountService
    )
    {
        $this->gigyaCustomerAccountService = $gigyaCustomerAccountService;
    }

    /**
     * @inheritdoc
     */
    function save(GigyaCustomerAccountInterface $gigyaCustomerAccount)
    {
        // CATODO : for now we synchronize on update only, not for a new user
        if ($gigyaCustomerAccount->getUid()) {

            $this->gigyaCustomerAccountService->update($gigyaCustomerAccount);
        }
    }
}