<?php

namespace Gigya\GigyaIM\Logger;

use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Logger extends \Monolog\Logger
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Logger constructor.
     *
     * @param string               $name
     * @param ScopeConfigInterface $scopeConfig
     * @param array                $handlers
     * @param array                $processors
     */
    public function __construct(
        string $name,
        ScopeConfigInterface $scopeConfig,
        array $handlers = [],
        array $processors = []
    ) {
        $this->scopeConfig = $scopeConfig;

        parent::__construct($name, $handlers, $processors);
    }

    /**
     * Adds a log record.
     *
     * @param int    $level   The logging level
     * @param string $message The log message
     * @param array  $context The log context
     *
     * @return bool Whether the record has been processed
     */
    public function addRecord(int $level, string $message, array $context = []): bool
    {
        $debugMode = $this->scopeConfig->getValue(GigyaConfig::XML_PATH_DEBUG_MODE, 'website');

        if ($level != self::CRITICAL) {
            if ($debugMode == 0) {
                return false;
            }

            if ($debugMode == 1 && $level == self::DEBUG) {
                return false;
            }
        }

        return parent::addRecord($level, $message, $context);
    }
}
