<?php

namespace Gigya\GigyaIM\Controller\Raas;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class State extends Action
{
    /**
     * @var Session
     */
    protected Session $customerSession;

    /**
     * @param Context $context
     * @param Session $customerSession
     */
    public function __construct(
        Context $context,
        Session $customerSession
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
    }

    /**
     * Dispatch request
     *
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        return $this->resultFactory
            ->create(ResultFactory::TYPE_JSON)
            ->setData(['logged_in' => $this->customerSession->isLoggedIn() ? 1 : 0]);
    }
}
