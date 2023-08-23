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

class CustomerSync extends SyncAbstract
{
    /**
     * @var bool
     */
    protected bool $truncate = true;

    /**
     * @var bool
     */
    protected bool $syncEavValues = true;

    protected function getTablesToSync(): array
    {
        return [
            'customer_entity' => $this->connectionHelper->getM2Connection()->getTableName('customer_entity')
        ];
    }

    public function prepareRowToInsert($m1Entity, $matchingColumns): array
    {
        unset($m1Entity['attribute_set_id']);
        unset($m1Entity['entity_type_id']);

        return $m1Entity;
    }

    protected function getEntityTypeId(): ?int
    {
        return 1;
    }
}
