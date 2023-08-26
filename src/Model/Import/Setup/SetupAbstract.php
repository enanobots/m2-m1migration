<?php
/**
 * Created by Q-Solutions Studio
 *
 * @category    Nanobots
 * @package     Nanobots_MigrationTool
 * @author      Jakub Winkler <jwinkler@qsolutionsstudio.com>
 */

namespace Nanobots\MigrationTool\Model\Import\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Helper\Connection as ConnectionHelper;
use Zend_Db_Statement_Exception;

/**
 * Class SetupAbstract
 * @package Nanobots\MigrationTool\Model\Import\Setup
 */
abstract class SetupAbstract
{
    /** @var ConnectionHelper  */
    protected ConnectionHelper $connectionHelper;

    /**
     * @return string
     */
    abstract public function getM1TableName();

    /**
     * @return string
     */
    abstract public function getM2TableName();

    /**
     * @return array
     */
    abstract public function getM2TableColumns();

    /**
     * @return string
     */
    abstract public function getIncrementFields();

    /**
     * @return mixed
     */
    abstract public function afterImportData();

    /**
     * Websites constructor.
     * @param ConnectionHelper $connection
     */
    public function __construct(
        ConnectionHelper $connection
    ) {
        $this->connectionHelper = $connection;
    }

    /**
     * @return AdapterInterface
     * @throws LocalizedException
     * @throws Zend_Db_Statement_Exception
     */
    public function getM1Connection()
    {
        $this->connectionHelper->initializeMagento1Connection();
        return $this->connectionHelper->getM1connection();
    }

    /**
     * @return AdapterInterface
     */
    public function getM2Connection(): AdapterInterface
    {
        return $this->connectionHelper->getM2Connection();
    }

    /**
     * @return string
     */
    public function getM1FullTableName()
    {
        return $this->connectionHelper->getConfig($this->connectionHelper::XPATH_CONFIG_IMPORT_PREFIX) . $this->getM2TableName();
    }

    /**
     * @throws LocalizedException
     * @throws Zend_Db_Statement_Exception
     */
    public function insertConfig()
    {
        $this->getM2Connection()->query('SET FOREIGN_KEY_CHECKS=0');
        $this->getM2Connection()->query('delete from `' . $this->getM2TableName() . '` where `' . $this->getIncrementFields() . '` > 0');
        $this->getM2Connection()->query('ALTER TABLE ' . $this->getM2TableName() . ' AUTO_INCREMENT = 1');
        $this->getM2Connection()->query('SET FOREIGN_KEY_CHECKS=1');

        $m1Entries = $this->getM1Connection()->fetchAll(
            'select ' . implode(',', $this->getM2TableColumns()) . '
            from `' . $this->getM1FullTableName() . '`
                where `' . $this->getIncrementFields() . '` > 0
                and `' . $this->getIncrementFields() . '`'
        );

        foreach ($m1Entries as $m1Entry) {
            $this->getM2Connection()->insert(
                $this->getM2TableName(),
                $m1Entry
            );
        }

        $this->afterImportData();
    }
}
