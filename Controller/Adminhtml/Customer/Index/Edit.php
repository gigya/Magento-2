<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Controller\Adminhtml\Customer\Index;

use Gigya\GigyaIM\Exception\GigyaFieldMappingException;

/**
 * Edit
 *
 * When a customer is saved from backend page : if an exception is thrown during field mapping an error is displayed and the update is canceled.
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