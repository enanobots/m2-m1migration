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

namespace Nanobots\MigrationTool\Model\Import;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Helper\ProgressBar;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class GalleryValueSync extends EavValuesSync
{
    protected bool $truncate = true;
    protected bool $matchMissingColumns = false;
    /**
     * @var array
     */
    private $galleryValues;

    /**
     * @return string[]
     */
    protected function getTablesToSync(): array
    {
        return [
            'catalog_product_entity_media_gallery' => 'catalog_product_entity_media_gallery_value'
        ];
    }

    public function getEntityTypeId(): ?int
    {
        return 4;
    }

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns): array
    {
        $m1Entity = parent::prepareRowToInsert($m1Entity, $matchingColumns);

        $m1Entity['record_id'] = null;
        $valueId = $this->galleryValues[$m1Entity['value']] ?? null;
        unset($m1Entity['value']);

        if ($valueId) {
            $m1Entity['value_id'] = $valueId;
        } else {
            return [];
        }

        return $m1Entity;
    }

    public function sync(): void
    {
        $this->initGalleryValues();

        foreach ($this->getTablesToSync() as $m1Table => $m2Table) {
            $this->syncData($m1Table, $m2Table, null, ['disabled']);
            $this->cleanBroken($m2Table);
        }

        $this->insertValueToEntity();
    }

    /**
     * @param $m1Table
     * @param $m2Table
     * @return array
     * @throws LocalizedException|Zend_Db_Exception|Zend_Db_Statement_Exception
     */
    public function matchColumns($m1Table, $m2Table): array
    {
        $matchingColumns = parent::matchColumns($m1Table, $m2Table);
        $matchingColumns[] = 'store_id';
        $matchingColumns[] = 'label';
        $matchingColumns[] = 'position';
        $matchingColumns[] = 'disabled';
        $matchingColumns[] = 'record_id';

        return $matchingColumns;
    }

    /**
     * @param $m1Table
     * @param $idFrom
     * @param $idTo
     * @param $primaryKey
     * @param null $cond
     * @return array
     */
    protected function getM1Rows($m1Table, $idFrom, $idTo, $primaryKey, $cond = null): array
    {
        $sql = $this->connectionHelper->getM2Connection()
            ->select()
            ->from(
                ['g' => $m1Table],
                [
                    'entity_id' => 'entity_id',
                    'value' => 'value',

                ]
            )
            ->joinLeft(
                ['gv' => sprintf('%s_value', $m1Table)],
                'g.value_id = gv.value_id',
                [
                    'store_id' => 'store_id',
                    'label' => 'label',
                    'position' => 'position',
                    'disabled' => 'disabled',
                ]
            );

        if ($idFrom) {
            $sql->where(sprintf('g.%s > ?', $primaryKey), $idFrom);
        }

        $sql->where(sprintf('g.%s < ?', $primaryKey), $idTo)
            ->where('store_id is not null');
        return $this->connectionHelper->getM1connection()->fetchAll($sql);
    }

    private function initGalleryValues(): void
    {
        $this->initEntityIds();
        $m2Connection = $this->connectionHelper->getM2Connection();
        $this->galleryValues = $m2Connection->fetchPairs("SELECT `value`,`value_id` FROM `catalog_product_entity_media_gallery`");
    }

    /**
     * @return array
     */
    private function getEntityValuePairs(): array
    {
        $m2Connection = $this->connectionHelper->getM2Connection();
        return $m2Connection->fetchAll("SELECT `value_id`,`entity_id` FROM `catalog_product_entity_media_gallery_value`");
    }

    /**
     * @throws LocalizedException|Zend_Db_Adapter_Exception|Zend_Db_Exception
     */
    protected function insertValueToEntity(): void
    {
        $entityValuePairs = $this->getEntityValuePairs();
        $m2Table = 'catalog_product_entity_media_gallery_value_to_entity';

        $this->output->writeln('<info>Table sync for: ' . $m2Table . '</info>');
        $this->connectionHelper->getM2Connection()->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->connectionHelper->getM2Connection()->truncateTable($m2Table);

        $count = count($entityValuePairs);
        $this->progressBar = new ProgressBar($this->output, $count);
        $this->progressBar->setBarCharacter('<fg=magenta>=</>');
        if ($count > 0) {
            try {
                $this->connectionHelper->getM2Connection()->beginTransaction();
                foreach (array_chunk($entityValuePairs, self::CHUNK_SIZE) as $chunk) {
                    $this->doInsert(['entity_id', 'value_id'], $m2Table, array_keys($chunk[0]), $chunk);

                    $this->progressBar->advance(count($chunk));
                }
                $this->connectionHelper->getM2Connection()->commit();
            } catch (Exception $e) {
                $this->output->writeln('<error>' . $e->getMessage() . '</error>');
                $this->output->writeln('<error>' . $e->getTraceAsString() . '</error>');

                if ($this->connectionHelper->getM2Connection()->getTransactionLevel() > 0) {
                    $this->connectionHelper->getM2Connection()->rollBack();
                }
            }
        }

        $this->progressBar->finish();
        $this->output->writeln('');
        $this->cleanBroken($m2Table);

        $this->connectionHelper->getM2Connection()->query('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @param string|null $table
     * @throws LocalizedException|Zend_Db_Adapter_Exception
     */
    protected function cleanBroken(string $table = null): void
    {
        parent::cleanBroken($table);
        if ($table) {
            $entityTable = 'catalog_product_entity_media_gallery';
            $sql = <<<SQL
DELETE gv FROM $table gv LEFT JOIN $entityTable g ON gv.value_id = g.value_id WHERE g.value_id IS NULL
SQL;
            $this->connectionHelper->getM2Connection()->query($sql);
        }
    }
}
