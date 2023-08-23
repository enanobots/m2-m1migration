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
 * Class Product
 * @package Nanobots\MigrationTool\Model\Import\Entities
 */
class Order extends SyncAbstract
{
    protected bool $truncate = true;
    protected bool $matchMissingColumns = false;
    protected string $entityName = 'order';
    protected array $deltaExcluded = ['sales_order_payment', 'sales_flat_order_address'];

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns): array
    {
        $insertRow = [];
        foreach ($m1Entity as $columnName => $value) {
            if (in_array($columnName, $matchingColumns, true)) {
                switch ($columnName) {
                    case 'gift_cards':
                    case 'weee_tax_applied':
                    case 'additional_information':
                    case 'product_options':
                        {
                            $insertRow[$columnName] = json_encode(unserialize($value ?? ''));
                            break;
                        }
                    case 'method':
                        {
                            $insertRow[$columnName] = 'm1_' . $value;
                            break;
                        }
                    default:
                        {
                            $insertRow[$columnName] = $value;
                            break;
                        }
                }
            }
        }
        return $insertRow;
    }

    /**
     * @param bool $delta
     * @throws LocalizedException|Zend_Db_Adapter_Exception|Zend_Db_Exception|Zend_Db_Statement_Exception|Exception
     */
    public function sync(bool $delta = false): void
    {
        parent::sync($delta);
        if (!$delta) {
            $this->syncData('eav_entity_store', 'eav_entity_store');
        }
        $this->fillUpOrderSequenceTables();
        $this->updateStatus();
    }

    /**
     * @return $this
     * @throws Exception
     */
    private function fillUpOrderSequenceTables(): self
    {
        $this->output->writeln('<info>Filling up order sequence tables</info>');
        $ordersData = $this->connectionHelper->getM2Connection()->fetchAll(
            'select `entity_id`, `store_id` from `sales_order`
             where `store_id` IS NOT NULL
             order by `entity_id` asc'
        );

        $this->connectionHelper->getM2Connection()->beginTransaction();
        foreach ($ordersData as $orderData) {
            $this->connectionHelper->getM2Connection()->insert(
                'sequence_order_' . $orderData['store_id'],
                ['sequence_value' => $orderData['entity_id']]
            );
        }
        $this->connectionHelper->getM2Connection()->commit();
        return $this;
    }

    protected function getTablesToSync(): array
    {
        return [
            'sales_flat_order' => 'sales_order',
            'sales_flat_order_item' => 'sales_order_item',
            'sales_flat_order_grid' => 'sales_order_grid',
            'sales_flat_order_payment' => 'sales_order_payment',
            'sales_flat_order_address' => 'sales_order_address',
            ];
    }

    protected function getEntityTypeId(): ?int
    {
        return null;
    }

    /**
     * @return string|null
     */
    protected function getUpdatedAtField(): ?string
    {
        return null;
    }
}
