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

class Stores extends SetupAbstract
{
    /**
     * @return string
     */
    public function getM1TableName(): string
    {
        return 'core_store';
    }

    /**
     * @return string
     */
    public function getM2TableName(): string
    {
        return 'store';
    }

    /**
     * @return array
     */
    public function getM2TableColumns(): array
    {
        return [
            'store_id',
            'code',
            'website_id',
            'group_id',
            'name',
            'sort_order',
            'is_active'
        ];
    }

    public function getIncrementFields(): string
    {
        return 'store_id';
    }

    public function afterImportData()
    {
    }
}
