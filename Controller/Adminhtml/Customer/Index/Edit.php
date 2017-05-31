<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Controller\Adminhtml\Customer\Index;

use Gigya\GigyaIM\Exception\GigyaFieldMappingException;

/**
 * Edit
 *
 * When a customer page details is to be displayed on backend we try to enrich the Customer entity with the Gigya account data.
 * If an exception occurs during the field mapping we shall not be able to
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class Edit extends \Magento\Customer\Controller\Adminhtml\Index\Edit
{
    public function execute()
    {
        try {
            return parent::execute();
        } catch (GigyaFieldMappingException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/*/index');
            return $resultRedirect;
        }
    }
}