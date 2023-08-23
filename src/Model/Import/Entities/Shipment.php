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

use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Model\Import\SyncAbstract;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class Shipment extends SyncAbstract
{
    protected bool $truncate = true;
    protected bool $matchMissingColumns = false;
    protected string $entityName = 'invoice';
    protected array $deltaExcluded = ['sales_flat_invoice_comment'];

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns): array
    {
        $insertRow = [];
        foreach ($m1Entity as $columnName => $value) {
            /** match M1 row to M2 */
            if (in_array($columnName, $matchingColumns, true)) {
                switch ($columnName) {
                    case 'weee_tax_applied':
                        {
                            if ($value == "a:0:{}") {
                                $insertRow[$columnName] = null;
                            } else {
                                $insertRow[$columnName] = null;
                            }
                            break;
                        }
                    case 'additional_information': {
                            $insertRow[$columnName] = json_encode(unserialize($value ?? ''));
                            break;
                    }
                    default: {
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
     * @throws LocalizedException|Zend_Db_Adapter_Exception|Zend_Db_Exception|Zend_Db_Statement_Exception
     */
    public function sync(bool $delta = false): void
    {
        parent::sync($delta);
        $this->fillUpOrderSequenceTables();
        $this->updateStatus();
    }

    protected function getTablesToSync(): array
    {
        return [
            'sales_flat_shipment' => 'sales_shipment',
            'sales_flat_shipment_comment' => 'sales_shipment_comment', // OK
            'sales_flat_shipment_grid' => 'sales_shipment_grid', // OK
            'sales_flat_shipment_item' => 'sales_shipment_item', //
            'sales_flat_shipment_track' => 'sales_shipment_track', // OK
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

    /**
     * @return $this
     * @throws Exception|Zend_Db_Adapter_Exception
     */
    private function fillUpOrderSequenceTables(): self
    {
        $this->output->writeln('<info>Filling up invoice sequence tables</info>');
        $ordersData = $this->connectionHelper->getM2Connection()->fetchAll(
            'select `entity_id`, `store_id` from `sales_invoice`
             where `store_id` IS NOT NULL
             order by `entity_id` asc'
        );

        $this->connectionHelper->getM2Connection()->beginTransaction();
        foreach ($ordersData as $orderData) {
            $this->connectionHelper->getM2Connection()->insert(
                'sequence_invoice_' . $orderData['store_id'],
                ['sequence_value' => $orderData['entity_id']]
            );
        }
        $this->connectionHelper->getM2Connection()->commit();
        return $this;
    }
}
