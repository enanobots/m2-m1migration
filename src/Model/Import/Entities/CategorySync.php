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

class CategorySync extends SyncAbstract
{
    /**
     * @var bool
     */
    protected bool $truncate = true;

    /**
     * @var bool
     */
    protected bool $matchMissingColumns = false;

    /**
     * @var bool
     */
    protected bool $syncEavValues = true;

    /**
     * @return array
     */
    protected function getTablesToSync(): array
    {
        return [
            'catalog_category_entity' => $this->connectionHelper->getM2Connection()->getTableName('catalog_category_entity')
        ];
    }

    public function prepareRowToInsert($m1Entity, $matchingColumns): array
    {
        unset($m1Entity['entity_type_id']);

        return $m1Entity;
    }

    protected function getEntityTypeId(): ?int
    {
        return 3;
    }
}
