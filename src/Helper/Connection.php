<?php
/**
 * Copyright © Q-Solutions Studio: eCommerce Nanobots. All rights reserved.
 *
 * @category    Nanobots
 * @package     Nanobots_MigrationTool
 * @author      Jakub Winkler <jwinkler@qsolutionsstudio.com
 * @author      Sebastian Strojwas <sebastian@qsolutionsstudio.com>
 * @author      Wojtek Wnuk <wojtek@qsolutionsstudio.com>
 * @author      Lukasz Owczarczuk <lukasz@qsolutionsstudio.com>
 */

namespace Nanobots\MigrationTool\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper as MagentoAbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResourceConnection\ConnectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Type\Db\ConnectionFactoryInterface;
use Magento\Store\Model\ScopeInterface;
use PDO;
use PDOException;
use Zend_Db_Adapter_Exception;
use Zend_Db_Statement_Exception;

class Connection extends MagentoAbstractHelper
{
    /** @var string  */
    public const XPATH_CONFIG_IMPORT_HOST = 'migration/database/host';

    /** @var string  */
    public const XPATH_CONFIG_IMPORT_USER = 'migration/database/user';

    /** @var string  */
    public const XPATH_CONFIG_IMPORT_PASS = 'migration/database/password';

    /** @var string  */
    public const XPATH_CONFIG_IMPORT_BASE = 'migration/database/database';

    /** @var string  */
    public const XPATH_CONFIG_IMPORT_ENABLED = 'migration/database/enabled';


    /** @var string  */
    public const XPATH_CONFIG_IMPORT_PREFIX = 'migration/database/prefix';

    /** @var int  */
    public const ENTITY_CUSTOMER = 1;

    /** @var int  */
    public const ENTITY_CUSTOMER_ADDRESS = 2;

    /** @var int  */
    public const ENTITY_CATALOG_CATEGORY = 3;

    /** @var int  */
    public const ENTITY_CATALOG_PRODUCT = 4;

    /** @var int  */
    public const ENTITY_SALES_ORDER = 5;

    /** @var int  */
    public const ENTITY_SALES_INVOICE = 6;

    /** @var int  */
    public const ENTITY_SALES_CREDITMEMO = 7;

    /** @var int  */
    public const ENTITY_SHIPMENT = 8;

    /** @var int  */
    public const ENTITY_RMA_ITEM = 9;

    /** @var bool  */
    public const ALWAYS_IMPORT_STOCKS = true;

    /** @var ResourceConnection  */
    protected $resource;

    /** @var AdapterInterface  */
    protected $connection;

    /** @var Mysql | AdapterInterface  */
    protected $m1Connection;

    /** @var ConnectionFactoryInterface  */
    protected $connectionFactory;

    /** @var ScopeConfigInterface */
    private $_scopeConfig;

    /**
     * AbstractHelper constructor.
     * @param ResourceConnection $resource
     * @param Context $context
     * @param ConnectionFactory $connectionFactory
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ResourceConnection $resource,
        Context $context,
        ConnectionFactory $connectionFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resource = $resource;
        $this->connection = $this->resource->getConnection();
        $this->connectionFactory = $connectionFactory;
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    /**
     * @param $path
     * @return mixed
     */
    public function getConfig($path)
    {
        return $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORES);
    }

    /**
     * @return bool
     * @throws LocalizedException
     * @throws Zend_Db_Statement_Exception
     */
    public function initializeMagento1Connection(): bool
    {
        $accessibleDbs = [];

        try {
            $this->m1Connection = $this->connectionFactory->create(
                [
                    'host' => $this->getConfig(self::XPATH_CONFIG_IMPORT_HOST),
                    'dbname' => $this->getConfig(self::XPATH_CONFIG_IMPORT_BASE),
                    'username' => $this->getConfig(self::XPATH_CONFIG_IMPORT_USER),
                    'password' => $this->getConfig(self::XPATH_CONFIG_IMPORT_PASS),
                    'active' => $this->getConfig(self::XPATH_CONFIG_IMPORT_ENABLED)
                ]
            );
            $accessibleDbs = $this->m1Connection->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (Zend_Db_Adapter_Exception $e) {
            return false;
        } catch (PDOException $e) {
            return false;
        } catch (\Exception $exception) {
            // do nothing
        }

        if (in_array($this->getConfig(self::XPATH_CONFIG_IMPORT_BASE), $accessibleDbs, true)) {
            return true && $this->m1Connection->isConnected();
        }

        return false;
    }

    /**
     * @return AdapterInterface | Mysql
     */
    public function getM1connection()
    {
        return $this->m1Connection;
    }

    /**
     * @return AdapterInterface | Mysql
     */
    public function getM2Connection()
    {
        return $this->connection;
    }
}
