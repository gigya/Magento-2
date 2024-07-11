<?php

namespace Gigya\GigyaIM\Model\ResourceModel;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;

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
    protected GigyaAccountServiceInterface $gigyaAccountService;

    /**
     * GigyaAccountRepository constructor.
     *
     * @param GigyaAccountServiceInterface $gigyaAccountService
     */
    public function __construct(GigyaAccountServiceInterface $gigyaAccountService)
    {
        $this->gigyaAccountService = $gigyaAccountService;
    }

    /**
     * @inheritdoc
     */
    public function update(GigyaUser $gigyaAccount): void
    {
        if ($gigyaAccount->getUid()) {
            $this->gigyaAccountService->update($gigyaAccount);
        }
    }

    /**
     * @inheritdoc
     */
    public function get(string $uid): GigyaUser
    {
        return $this->gigyaAccountService->get($uid);
    }
}
