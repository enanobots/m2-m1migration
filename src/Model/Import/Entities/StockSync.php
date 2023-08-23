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

namespace Nanobots\MigrationTool\Model\Import\Entities;

use Nanobots\MigrationTool\Model\Import\SyncAbstract;

class StockSync extends SyncAbstract
{
    protected bool $truncate = true;
    protected bool $canDelta = false;
    protected string $entityName = 'cataloginventory_stock';

    /**
     * @inheritDoc
     */
    protected function getTablesToSync(): array
    {
        return [
            'cataloginventory_stock_item' => 'cataloginventory_stock_item',
            'cataloginventory_stock_status' => 'cataloginventory_stock_status',
            'cataloginventory_stock_status_idx' => 'cataloginventory_stock_status_idx',
        ];
    }

    /**
     * @return int|null
     */
    protected function getEntityTypeId(): ?int
    {
        return null;
    }

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns): array
    {
        return $m1Entity;
    }

    /**
     * @return string
     */
    protected function getUpdatedAtField(): string
    {
        return '';
    }
}
