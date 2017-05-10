<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\ResourceModel;

use Gigya\GigyaIM\Api\Data\GigyaCustomerAccountInterface;
use Gigya\GigyaIM\Api\GigyaCustomerAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaMageHelper;

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
    /** @var  GigyaMageHelper */
    protected $gigyaMageHelper;

    /**
     * GigyaCustomerAccountRepository constructor.
     *
     * @param GigyaMageHelper $gigyaMageHelper
     */
    public function __construct(
        GigyaMageHelper $gigyaMageHelper
    )
    {
        $this->gigyaMageHelper = $gigyaMageHelper;
    }

    /**
     * @inheritdoc
     */
    function save(GigyaCustomerAccountInterface $gigyaCustomerAccount)
    {
        // CATODO : for now we synchronize on update only, not for a new user
        if ($gigyaCustomerAccount->getUid()) {

            // CATODO : other fields to map to Gigya
            $this->gigyaMageHelper->getGigyaApiHelper()->updateGigyaAccount(
                $gigyaCustomerAccount->getUid(),
                [
                    'email' => $gigyaCustomerAccount->getLoginEmail()
                ],
                [
                    'loginIDs' => [
                        $gigyaCustomerAccount->getLoginEmail()
                    ]
                ]
            );
        }
    }
}