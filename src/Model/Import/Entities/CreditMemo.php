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

class CreditMemo extends SyncAbstract
{
    protected bool $truncate = true;
    protected bool $matchMissingColumns = false;

    protected string $entityName = 'creditmemo';
    protected array $deltaExcluded = ['sales_flat_creditmemo_comment'];

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
            if (in_array($columnName, $matchingColumns)) {
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
            'sales_flat_creditmemo' => 'sales_creditmemo',
            'sales_flat_creditmemo_item' => 'sales_creditmemo_item',
            'sales_flat_creditmemo_comment' => 'sales_creditmemo_comment',
            'sales_flat_creditmemo_grid' => 'sales_creditmemo_grid',
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
        $this->output->writeln('<info>Filling up creditmemo sequence tables</info>');
        $ordersData = $this->connectionHelper->getM2Connection()->fetchAll(
            'select `entity_id`, `store_id` from `sales_creditmemo`
             where `store_id` IS NOT NULL
             order by `entity_id` asc'
        );

        $this->connectionHelper->getM2Connection()->beginTransaction();
        foreach ($ordersData as $orderData) {
            $this->connectionHelper->getM2Connection()->insert(
                'sequence_creditmemo_' . $orderData['store_id'],
                ['sequence_value' => $orderData['entity_id']]
            );
        }
        $this->connectionHelper->getM2Connection()->commit();
        return $this;
    }
}
