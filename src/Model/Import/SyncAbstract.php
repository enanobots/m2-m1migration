<?php
/**
 * Created by Q-Solutions Studio
 *
 * @category    Nanobots
 * @package     Nanobots_MigrationTool
 * @author      Wojciech M. Wnuk <wojtek@qsolutionsstudio.com>
 */

namespace Nanobots\MigrationTool\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Customer;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

abstract class SyncAbstract extends TableImportAbstract
{
    protected const ENTITIES = [
        1 => Customer::ENTITY,
        2 => Customer::ENTITY . '_address', // address entity model is missing the constant
        3 => Category::ENTITY,
        4 => Product::ENTITY
    ];

    protected bool $truncate = false;

    protected bool $matchMissingColumns = true;

    protected string $entityName = '';

    /**
     * Tables in format ['m1Table' => 'm2Table']
     *
     * @return array
     */
    abstract protected function getTablesToSync() : array;

    /**
     * @return int|null
     */
    abstract protected function getEntityTypeId() : ?int;

    /**
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function sync() : void
    {
        foreach ($this->getTablesToSync() as $m1Table => $m2Table) {
            $this->syncData($m1Table, $m2Table);
        }
    }

    /**
     * @param $m1Table
     * @param $idFrom
     * @param $idTo
     * @param $primaryKey
     * @param null $cond
     * @return array
     */
    protected function getM1Rows($m1Table, $idFrom, $idTo, $primaryKey, $cond = null): array
    {
        if ($this->matchMissingColumns && count($this->missingColumns) && !is_null($this->getEntityTypeId())) {
            $m1connection = $this->connectionHelper->getM1connection();
            $attributesSql = $m1connection->select()->from(
                'eav_attribute',
                ['attribute_code', 'attribute_id', 'backend_type']
            )->where('entity_type_id = ? AND attribute_code IN(' . implode(',', array_map(function ($column) {
                    return "'$column'";
                }, $this->missingColumns)) . ')', $this->getEntityTypeId());

            $attributesData = $m1connection->fetchAll($attributesSql);
            $attributesMap = [];

            foreach ($attributesData as $attributesDatum) {
                $attributesMap[$attributesDatum['attribute_code']] = $attributesDatum;
            }

            $columns = [];
            $joins = [];

            foreach ($this->missingColumns as $i => $missingColumn) {
                if (empty($attributesMap[$missingColumn])) {
                    unset($this->missingColumns[$i]);
                    continue;
                }
                $columns[] = "`$missingColumn`.`value` AS `$missingColumn`";
                $joins[] = "LEFT JOIN (`{$m1Table}_{$attributesMap[$missingColumn]['backend_type']}` `$missingColumn`)\n
                ON `$missingColumn`.`entity_id` = `$m1Table`.entity_id AND `$missingColumn`.attribute_id = {$attributesMap[$missingColumn]['attribute_id']}";
            }

            if ($idFrom) {
                $select = "SELECT `$m1Table`.*, " . implode(', ', $columns) . " FROM `$m1Table`\n" . implode("\n", $joins) .
                    sprintf(
                        ' WHERE %s.%s >= %s AND %s.%s < %s',
                        $m1Table,
                        $primaryKey,
                        $idFrom,
                        $m1Table,
                        $primaryKey,
                        $idTo
                    );
            } else {
                $select = "SELECT `$m1Table`.*, " . implode(', ', $columns) . " FROM `$m1Table`\n" . implode("\n", $joins) .
                    sprintf(
                        ' WHERE %s.%s < %s',
                        $m1Table,
                        $primaryKey,
                        $idTo
                    );
            }

            return $m1connection->fetchAll($select);
        }

        return parent::getM1Rows($m1Table, $idFrom, $idTo, $primaryKey, $cond);
    }

    /**
     * @throws Zend_Db_Exception
     */
    public function updateStatus(): void
    {
        $this->connectionHelper
            ->getM2Connection()
            ->insertOnDuplicate(
                'nanobots_migrations_status',
                ['entity_name' => $this->getEntityName()],
                ['entity_name']
            );
    }

    /**
     * @return mixed
     */
    protected function getEntityName()
    {
        return static::ENTITIES[$this->getEntityTypeId()] ?? $this->entityName;
    }
}
