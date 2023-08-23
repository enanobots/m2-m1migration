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
 *
 * Special Thanks to @Ecomdev
 */

namespace Nanobots\MigrationTool\Model\Import;

class InsertMultipleOnDuplicate
{
    private ?array $_onDuplicate;

    /**
     * @param array $columnsToUpdate
     * @return $this
     */
    public function onDuplicate(array $columnsToUpdate): self
    {
        $this->_onDuplicate = $columnsToUpdate;
        return $this;
    }

    /**
     * Flatten a multi-dimensional array
     *
     * @param array $productData
     * @return array
     */
    public static function flatten(array $productData): array
    {
        $flattened = [];

        array_walk_recursive($productData, static function ($a) use (&$flattened) {
            $flattened[] = $a;
        });

        return $flattened;
    }

    /**
     * @param $table
     * @param $columnNames
     * @param $rowCount
     * @return string|null
     */
    public function buildInsertQuery($table, $columnNames, $rowCount): ?string
    {
        $rowTemplate = sprintf('(%s)', implode(',', array_fill(0, count($columnNames), '?')));

        $statement = sprintf(
            'INSERT INTO %s (%s) VALUES %s%s',
            $table,
            implode(',', $columnNames),
            str_repeat(
                sprintf('%s, ', $rowTemplate),
                $rowCount - 1
            ),
            $rowTemplate
        );

        if (!$this->_onDuplicate) {
            return $statement;
        }

        $onDuplicateStatements = [];
        foreach ($this->_onDuplicate as $columnName) {
            $onDuplicateStatements[] = sprintf('%1$s = VALUES(%1$s)', $columnName);
        }

        return sprintf('%s ON DUPLICATE KEY UPDATE %s', $statement, implode(', ', $onDuplicateStatements));
    }
}
