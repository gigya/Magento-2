<?php

namespace Gigya\GigyaIM\Controller\Raas\Account;

use Magento\Customer\Controller\Account\CreatePost as CreatePostParent;
use Magento\Framework\Controller\Result\Redirect;

class CreatePost extends CreatePostParent
{
    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $config = $this->_objectManager->create('Gigya\GigyaIM\Model\Config');

        if ($config->isGigyaEnabled()) {
            $resultRedirect = $this->resultRedirectFactory->create();

            $url = $this->urlModel->getUrl('*/*/create', ['_secure' => true]);
            $resultRedirect->setUrl($this->_redirect->error($url));

            return $resultRedirect;
        } else {
            return parent::execute();
        }
    }
}
