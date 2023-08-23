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

class StoreGroups extends SetupAbstract
{
    /**
     * @return string
     */
    public function getM1TableName(): string
    {
        return 'core_store_group';
    }

    /**
     * @return string
     */
    public function getM2TableName(): string
    {
        return 'store_group';
    }

    /**
     * @return array
     */
    public function getM2TableColumns(): array
    {
        return [
            'group_id',
            'website_id',
            'name',
            'root_category_id',
            'default_store_id'
        ];
    }

    /**
     * @return string
     */
    public function getIncrementFields(): string
    {
        return 'group_id';
    }

    /**
     * @return void
     */
    public function afterImportData()
    {
        $this->getM2Connection()->query(
            'update `' . $this->getM2TableName() . '`
                    set `code` = lower(replace(name, \' \', \'_\'))'
        );
    }
}
