<?php

namespace Gigya\GigyaIM\Controller\Raas\Account;

use Magento\Framework\Controller\Result\Redirect;

class CreatePost extends \Magento\Customer\Controller\Account\CreatePost
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
