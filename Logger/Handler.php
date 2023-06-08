<?php

namespace Gigya\GigyaIM\Logger;

use Magento\Framework\Logger\Handler\Base;
use Gigya\GigyaIM\Logger\Logger;

class Handler extends Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/gigya.log';
}
