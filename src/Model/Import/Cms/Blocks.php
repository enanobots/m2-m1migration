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

namespace Nanobots\MigrationTool\Model\Import\Cms;

use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Model\Import\SyncAbstract;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class Blocks extends SyncAbstract
{
    /**
     * @var string
     */
    protected string $entityName = 'cms_block';

    /**
     * @var bool
     */
    protected bool $truncate = true;

    /**
     * @var bool
     */
    protected bool $matchMissingColumns = false;

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
     * @param bool $delta
     * @throws LocalizedException|Zend_Db_Adapter_Exception|Zend_Db_Exception|Zend_Db_Statement_Exception
     */
    public function sync(bool $delta = false): void
    {
        parent::sync($delta);
        $this->truncate = true;
        $this->syncData('cms_block_store', 'cms_block_store');

        $this->updateStatus();
    }

    /**
     * @return string[]
     */
    protected function getTablesToSync(): array
    {
        return [
            'cms_block' => 'cms_block',
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
     * @return string
     */
    protected function getUpdatedAtField(): string
    {
        return 'update_time';
    }
}
