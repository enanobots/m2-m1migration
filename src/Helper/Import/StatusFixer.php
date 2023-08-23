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

namespace Nanobots\MigrationTool\Helper\Import;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Nanobots\MigrationTool\Helper\Connection;

class StatusFixer extends Connection
{
    /**
     * @var array
     */
    protected $statuses = [
        Status::STATUS_ENABLED,
        Status::STATUS_DISABLED
    ];

    protected $visibility = [
        Visibility::VISIBILITY_NOT_VISIBLE,
        Visibility::VISIBILITY_IN_CATALOG,
        Visibility::VISIBILITY_IN_SEARCH,
        Visibility::VISIBILITY_BOTH
    ];

    public function fixStatus() : void
    {
        $eavTable = $this->getM2Connection()->getTableName('eav_attribute');
        $statusId = $this->getM2Connection()->fetchOne(
            $this->getM2Connection()->select()->from($eavTable, ['attribute_id'])->where("attribute_code = 'status' AND entity_type_id = 4")
        );
        $visibilityId = $this->getM2Connection()->fetchOne(
            $this->getM2Connection()->select()->from($eavTable, ['attribute_id'])->where("attribute_code = 'visibility' AND entity_type_id = 4")
        );

        $valueTable = $this->getM2Connection()->getTableName('catalog_product_entity_int');

        $cond = join(',', $this->statuses);
        $this->getM2Connection()->update($valueTable, ['value' => Status::STATUS_DISABLED], "attribute_id = $statusId AND value NOT IN($cond)");

        $cond = join(',', $this->visibility);
        $this->getM2Connection()->update($valueTable, ['value' => Visibility::VISIBILITY_NOT_VISIBLE], "attribute_id = $visibilityId AND value NOT IN($cond)");
    }
}
