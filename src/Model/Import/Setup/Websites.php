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

class Websites extends SetupAbstract
{
    /**
     * @return string
     */
    public function getM1TableName(): string
    {
        return 'core_website';
    }

    /**
     * @return string
     */
    public function getM2TableName(): string
    {
        return 'store_website';
    }

    /**
     * @return array
     */
    public function getM2TableColumns(): array
    {
        return [
            'website_id',
            'code',
            'name',
            'sort_order',
            'default_group_id',
            'is_default'
        ];
    }

    public function getIncrementFields(): string
    {
        return 'website_id';
    }

    public function afterImportData()
    {

    }

}
