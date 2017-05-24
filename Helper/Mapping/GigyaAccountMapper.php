<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Helper\Mapping;

use Gigya\CmsStarterKit\user\GigyaProfile;
use Gigya\CmsStarterKit\user\GigyaUser;
use Magento\Customer\Model\Customer;

/**
 * GigyaAccountMapper
 *
 * Facility for mapping a Magento Customer entity to or from a Gigya data structure.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class GigyaAccountMapper
{
     /**
      * Enrich or create an instance of GigyaUser with the provided Magento Customer entity.
      *
      * The instance will be fed with at least those Gigya's required data :
      *
      * . uid : null if it's a new account, or not synchronized yet with Gigya
      * . profile.email : the email set on the Magento account
      * . profile.firstName
      * . profile.lastName
      *
      * And with a magento_entity_id for the Magento Gigya module purposes.
      *
      * @param Customer $magentoCustomer The source. Shall not be null.
      * @param GigyaUser $gigyaAccount The target. If null it will be created.
      * @return GigyaUser
      */
    public function enrichGigyaAccount($magentoCustomer, $gigyaAccount = null)
    {
        /** @var GigyaUser $result */
        $result = is_null($gigyaAccount) ? new GigyaUser(null) : $gigyaAccount;

        if ($result->getProfile() == null) {
            $result->setProfile(new GigyaProfile(null));
        }

        if (empty($result->getLoginIDs())) {
            $result->setLoginIDs([]);
        }

        if (empty($result->getLoginIDs()['emails'])) {
            $result->setLoginIDs(
                array_merge(
                    $result->getLoginIDs(),
                    [ 'emails' => [] ]
                )
            );
        }

        $profileEmail = $magentoCustomer->getEmail();
        if (!in_array($profileEmail, $result->getLoginIDs()['emails'])) {
            $result->setLoginIDs(
                array_merge(
                    $result->getLoginIDs(),
                    [
                        'emails' => array_merge(
                            $result->getLoginIDs()['emails'],
                            [ $profileEmail ]
                        )
                    ]
                )
            );
        }

        $result->setUid($magentoCustomer->getGigyaUid());
        $result->getProfile()->setEmail($profileEmail);
        $result->getProfile()->setFirstName($magentoCustomer->getFirstname());
        $result->getProfile()->setLastName($magentoCustomer->getLastname());

        $result->setMagentoEntityId($magentoCustomer->getEntityId());

        return $result;
    }
}