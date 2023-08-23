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
use Nanobots\MigrationTool\Model\Import\TableImportAbstract;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class Tax extends TableImportAbstract
{
    public const TABLES = [
        'tax_class',
        'tax_calculation_rule',
        'tax_calculation',
        'tax_calculation_rate',
        'tax_calculation_rate_title'
    ];

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns) : array
    {
        return $m1Entity;
    }

    /**
     * @return $this
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function importData() : self
    {
        foreach (self::TABLES as $table) {
            $this->syncData($table, $table);
        }

        return $this;
    }
}
