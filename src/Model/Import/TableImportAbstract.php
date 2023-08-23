<?php
/**
 * Created by Q-Solutions Studio
 *
 * @category    Nanobots
 * @package     Nanobots_MigrationTool
 * @author      Jakub Winkler <jwinkler@qsolutionsstudio.com>
 */

namespace Nanobots\MigrationTool\Model\Import;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Helper\Connection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

abstract class TableImportAbstract
{
    /** @var int  */
    public const M1_BATCH_SIZE = 2500;

    /** @var int  */
    protected const CHUNK_SIZE = 2500;

    protected ConsoleOutput $output;

    protected Connection $connectionHelper;

    protected ProgressBar $progressBar;

    private InsertMultipleOnDuplicate $insertMultipleOnDuplicate;

    protected bool $truncate = true;

    protected bool $matchMissingColumns = false;

    protected array $missingColumns = [];

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @return array
     */
    abstract public function prepareRowToInsert($m1Entity, $matchingColumns): array;

    /**
     * ImportAbstract constructor.
     * @param Connection $connection
     * @param InsertMultipleOnDuplicate $insertMultipleOnDuplicate
     * @param ConsoleOutput $output
     */
    public function __construct(
        Connection $connection,
        InsertMultipleOnDuplicate $insertMultipleOnDuplicate,
        ConsoleOutput $output
    ) {
        $this->connectionHelper = $connection;
        $this->output = $output;
        $this->insertMultipleOnDuplicate = $insertMultipleOnDuplicate;
    }

    /**
     * @param $m1Table
     * @param $m2Table
     * @return array
     * @throws LocalizedException
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function matchColumns($m1Table, $m2Table): array
    {
        $this->connectionHelper->initializeMagento1Connection();
        $m1ColumnNames = $this->connectionHelper->getM1connection()->fetchAll(
            'show columns from `' . $m1Table . '`'
        );

        $m2ColumnNames = $this->connectionHelper->getM2connection()->fetchAll(
            'show columns from `' . $m2Table . '`'
        );

        if ($this->matchMissingColumns) {
            $this->missingColumns = array_diff(array_map(static function ($m2Column) {
                return $m2Column['Field'];
            }, $m2ColumnNames), array_map(static function ($m1Column) {
                return $m1Column['Field'];
            }, $m1ColumnNames));
        }

        $matchingColumns = [];

        foreach ($m1ColumnNames as $m1ColumnName) {
            foreach ($m2ColumnNames as $m2ColumnName) {
                if ($m1ColumnName['Field'] === $m2ColumnName['Field']) {
                    $matchingColumns[] = $m2ColumnName['Field'];
                    break;
                }
            }
        }

        ksort($matchingColumns);
        return $matchingColumns;
    }

    /**
     * @param $m1Table
     * @param $m2Table
     * @param null $cond
     * @param array $onDuplicate
     * @return $this
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function syncData($m1Table, $m2Table, $cond = null, $onDuplicate = [])
    {
        $this->output->writeln('<info>Table sync for: ' . $m2Table . '</info>');
        $this->connectionHelper->getM2Connection()->query('SET FOREIGN_KEY_CHECKS = 0');
        if ($this->truncate) {
            $this->connectionHelper->getM2Connection()->truncateTable($m2Table);
        }

        $m1TotalRows = $this->connectionHelper->getM1connection()->fetchOne('select count(*) as count from ' . $m1Table);
        $primaryKey = $this->getM1PrimaryKey($m1Table);

        $m1Rows = $this->connectionHelper->getM1connection()->fetchAll(
            sprintf(
                'SELECT CEIL(%s/%s)*%s as id_value_to FROM %s GROUP BY 1 order by id_value_to',
                $primaryKey,
                self::M1_BATCH_SIZE,
                self::M1_BATCH_SIZE,
                $m1Table
            )
        );

        $this->progressBar = new ProgressBar($this->output, $m1TotalRows);
        $lastValueFrom = 0;

        foreach ($m1Rows as $row) {
            $matchingColumns = $this->matchColumns($m1Table, $m2Table);
            $m1TableRows = $this->getM1Rows($m1Table, $lastValueFrom, $row['id_value_to'], $primaryKey, $cond);
            $lastValueFrom = $row['id_value_to'];
            $m2Rows = [];

            if ($this->matchMissingColumns && count($this->missingColumns)) {
                $matchingColumns = array_merge($matchingColumns, $this->missingColumns);
            }

            $count = count($m1TableRows);
            $this->progressBar->setBarCharacter('<fg=magenta>=</>');

            if ($count > 0) {
                foreach ($m1TableRows as $m1TableRow) {
                    $m2Row = $this->prepareRowToInsert($m1TableRow, $matchingColumns);
                    if (!empty($m2Row)) {
                        $m2Rows[] = $m2Row;
                    }
                }
                try {
                    $this->connectionHelper->getM2Connection()->beginTransaction();
                    foreach (array_chunk($m2Rows, self::CHUNK_SIZE) as $chunk) {
                        $this->doInsert($onDuplicate, $m2Table, array_keys($chunk[0]), $chunk);
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
        }

        $this->progressBar->finish();
        $this->output->writeln('');

        $this->connectionHelper->getM2Connection()->query('SET FOREIGN_KEY_CHECKS=1');
        return $this;
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
        $sql = $this->connectionHelper->getM1connection()
            ->select()
            ->from(['t' => $m1Table])
            ->where(sprintf('t.%s <= ?', $primaryKey), $idTo);

        if ($idFrom !== null) {
            $sql->where(sprintf('t.%s > ?', $primaryKey), $idFrom);
        }

        if ($cond) {
            $sql->where($cond);
        }

        return $this->connectionHelper->getM1connection()->fetchAll($sql);
    }

    /**
     * @param $m1Table
     * @return string
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    protected function getM1PrimaryKey($m1Table): string
    {
        $primaryKey = $this->connectionHelper->getM1connection()
            ->query(sprintf('SHOW KEYS FROM %s WHERE Key_name = "PRIMARY"', $m1Table))
            ->fetchObject();

        return $primaryKey->Column_name;
    }

    /**
     * @param array $onDuplicate
     * @param string $m2Table
     * @param array $matchingColumns
     * @param array $chunk
     */
    protected function doInsert(array $onDuplicate, string $m2Table, array $matchingColumns, array $chunk): void
    {
        $preparedStatement = $this->insertMultipleOnDuplicate
            ->onDuplicate($onDuplicate)
            ->buildInsertQuery($m2Table, $matchingColumns, count($chunk));

        $this->connectionHelper->getM2Connection()
            ->prepare($preparedStatement)
            ->execute(InsertMultipleOnDuplicate::flatten($chunk));
    }

    /**
     * @param string $dataType
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     */
    public function fillUpSequenceTables(string $dataType): void
    {
        $this->output->writeln('<info>Filling up order sequence tables</info>');
        $storeIds = $this->connectionHelper->getM2Connection()->fetchCol(
            'select  distinct(store_id) from sales_' . $dataType . '
             where `store_id` IS NOT NULL'
        );

        foreach ($storeIds as $storeId) {
            $maxStoreIncrementId = $this->connectionHelper->getM2Connection()->fetchOne(
                'select increment_id from sales_' . $dataType . '
                    where `store_id` = "' . $storeId . '" order by entity_id desc limit 1'
            );

            $nextIncrementId = (int)substr($maxStoreIncrementId, strlen($storeId)) + 1;
            $this->connectionHelper->getM2Connection()->query(
                sprintf(
                    'ALTER TABLE sequence_%s_%d AUTO_INCREMENT=%d',
                    $dataType,
                    $storeId,
                    $nextIncrementId
                )
            );
        }
    }
}
