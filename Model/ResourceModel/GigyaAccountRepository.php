<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\ResourceModel;

use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;

/**
 * GigyaAccountRepository
 *
 * @inheritdoc
 *
 * This is a base that will evolve soon while new features are developed.
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
    public function __construct(
        GigyaAccountServiceInterface $gigyaAccountService
    )
    {
        $this->gigyaAccountService = $gigyaAccountService;
    }

    /**
     * @inheritdoc
     */
    function save(GigyaUser $gigyaAccount)
    {
        // CATODO : for now we synchronize on update only, not for a new user
        if ($gigyaAccount->getUid()) {

            $this->gigyaAccountService->update($gigyaAccount);
        }
    }
}