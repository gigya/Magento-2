<?php

namespace Gigya\GigyaIM\Controller\Raas\Account;

class CreatePost extends \Magento\Customer\Controller\Account\CreatePost
{
    public function execute()
    {
        $config = $this->_objectManager->create('Gigya\GigyaIM\Model\Config');

        if ($config->isGigyaEnabled()) {
            /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();

            $url = $this->urlModel->getUrl('*/*/create', ['_secure' => true]);
            $resultRedirect->setUrl($this->_redirect->error($url));

            return $resultRedirect;
        } else {
            return parent::execute();
        }
    }
}
