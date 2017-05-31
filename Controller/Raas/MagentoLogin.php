<?php

namespace Gigya\GigyaIM\Controller\Raas;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;

class MagentoLogin extends AbstractLogin
{

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        if($this->formKeyValidator->validate($this->getRequest()))
        {
            $formKey = $this->getRequest()->getParam('form_key');
            $guid = $this->getRequest()->getParam('guid');
            $guidSignature = $this->getRequest()->getParam('guidsig');
            $signatureTimestamp = $this->getRequest()->getParam('sigtime');
            $key = $this->getRequest()->getParam('key');

            $sid = $this->cookieManager->getCookie('PHPSESSID');
            $baseUrl = $this->_url->getBaseUrl();
            $baseUrl = preg_replace('/^http(s)?\:\/\/(.*)\/$/', '$2', $baseUrl);
            $salt = 1234;
            $validateKey = base64_encode($sid.$baseUrl.$guid.$salt);
            if($key == $validateKey)
            {

                $valid_gigya_user = $this->gigyaMageHelper->validateAndFetchRaasUser(
                    $guid,$guidSignature,$signatureTimestamp
                );
                $result = $this->doLogin($valid_gigya_user);
                $resultData = $this->extractDataFromDataObject($result);
                die($resultData);

            }
        }

        return $this->resultFactory
            ->create(ResultFactory::TYPE_JSON)
            ->setData(['logged_in' => 0]);
    }
}