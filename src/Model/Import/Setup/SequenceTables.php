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

namespace Nanobots\MigrationTool\Model\Import\Setup;

use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Helper\Connection as ConnectionHelper;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;

class SequenceTables
{
    /** @var array  */
    public const SEQUENCE_ENTITY_TYPES = [
        'order',
        'invoice',
        'creditmemo',
        'shipment',
    ];

    /** @var int  */
    public const MAX_VALUE = 4294967295;

    /** @var int  */
    public const WARNING_VALUE = 4294966295;
    /**
     * @var ConnectionHelper
     */
    private $connectionHelper;

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
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     */
    public function recreatedSequenceTables()
    {
        $this->connectionHelper->getM2Connection()->query('SET FOREIGN_KEY_CHECKS=0');
        $this->connectionHelper->getM2Connection()->truncateTable('sales_sequence_profile');
        $this->connectionHelper->getM2Connection()->truncateTable('sales_sequence_meta');

        foreach ($this->getStores() as $storeId) {
            foreach (self::SEQUENCE_ENTITY_TYPES as $entityType) {
                /** drop table */
                $this->connectionHelper->getM2Connection()->query(
                    'drop table if exists `' . sprintf('sequence_%s_%d', $entityType, $storeId) . '`'
                );

                /** create table */
                $this->connectionHelper->getM2Connection()->insert(
                    'sales_sequence_meta',
                    [
                        'entity_type' => $entityType,
                        'store_id' => $storeId,
                        'sequence_table' => sprintf('sequence_%s_%d', $entityType, $storeId)
                    ]
                );

                $lastEntityId = $this->connectionHelper->getM2Connection()->lastInsertId('sales_sequence_meta');

                $newTable = $this->connectionHelper->getM2Connection()->newTable(sprintf('sequence_%s_%d', $entityType, $storeId));
                $newTable->addColumn(
                    'sequence_value',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
                    'Id'
                );
                $this->connectionHelper->getM2Connection()->createTable($newTable);

                /** fill up profile tables */
                $this->connectionHelper->getM2Connection()->insert(
                    'sales_sequence_profile',
                    [
                        'meta_id' => $lastEntityId,
                        'prefix' => $storeId,
                        'suffix' => null,
                        'start_value' => 1,
                        'step' => 1,
                        'max_value' => self::MAX_VALUE,
                        'warning_value' => self::WARNING_VALUE,
                        'is_active' => 1
                    ]
                );
            }
        }

        $this->connectionHelper->getM2Connection()->query('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @return array
     */
    protected function getStores(): array
    {
        return $this->connectionHelper->getM2Connection()->fetchCol(
            $this->connectionHelper->getM2Connection()->select()->from(
                $this->connectionHelper->getM2Connection()->getTableName('store'),
                ['store_id']
            )
        );
    }
}
