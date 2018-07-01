<?php

namespace Gigya\GigyaIM\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\ResourceModel\Type\Db\ConnectionFactoryInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;

/**
 * Class ConnectionFactory
 *
 * @package Gigya\GigyaIM\Model\ResourceModel
 *
 * @author akhayrullin <info@x2i.fr>
 */
class ConnectionFactory
{
    /** @var ConnectionFactoryInterface */
    protected $connectionFactory;

    /** @var DeploymentConfig */
    protected $deploymentConfig;

    /**
     * @param ConnectionFactoryInterface $connectionFactory
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        ConnectionFactoryInterface $connectionFactory,
        DeploymentConfig $deploymentConfig
    )
    {
        $this->connectionFactory = $connectionFactory;
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * Creates a new connection object with the same configuration as the default one.
     * New connections are used to inject a row in the database if a transaction fails.
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    public function getNewConnection()
    {
        $connectionName = ResourceConnection::DEFAULT_CONNECTION;

        $connectionConfig = $this->deploymentConfig->get(
            ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTIONS . '/' . $connectionName
        );
        if ($connectionConfig) {
            return $this->connectionFactory->create($connectionConfig);
        } else {
            throw new \DomainException('Connection "' . $connectionName . '" is not defined');
        }
    }
}