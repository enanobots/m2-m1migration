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

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Model\Import\SyncAbstract;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

/**
 * Class ReviewSync
 * @package Nanobots\MigrationTool\Model\Import\Entities
 */
class ReviewSync extends SyncAbstract
{
    /**
     * @var bool
     */
    protected bool $truncate = true;

    /**
     * @var bool
     */
    protected bool $canDelta = false;

    /**
     * @const array
     */
    public const REVIEW_TABLES = [
        'review_entity' => 'review_entity',
        'review_status' => 'review_status',
        'review' => 'review',
        'review_detail' => 'review_detail',
        'review_entity_summary' => 'review_entity_summary',
        'review_store' => 'review_store',
        'rating_entity' => 'rating_entity',
        'rating' => 'rating',
        'rating_option' => 'rating_option',
        'rating_option_vote' => 'rating_option_vote',
        'rating_option_vote_aggregated' => 'rating_option_vote_aggregated',
        'rating_store' => 'rating_store',
        'rating_title' => 'rating_title',
    ];

    /**
     * @param $m1Table
     * @param $m2Table
     * @param null $cond
     * @param array $onDuplicate
     * @return ReviewSync
     * @throws LocalizedException|Zend_Db_Adapter_Exception|Zend_Db_Exception|Zend_Db_Statement_Exception|Exception
     */
    public function syncData($m1Table, $m2Table, $cond = null, $onDuplicate = [])
    {
        if ($m1Table === 'review_entity_summary') {
            $onDuplicate = ['reviews_count', 'rating_summary'];
        }
        return parent::syncData($m1Table, $m2Table, $cond, $onDuplicate);
    }

    /**
     * @return string[]
     */
    protected function getTablesToSync(): array
    {
        return self::REVIEW_TABLES;
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
        if ($m1Entity['rating_code'] ?? false) {
            $m1Entity['is_active'] = 1;
        }

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
