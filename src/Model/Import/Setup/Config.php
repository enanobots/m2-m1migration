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

use Exception;
use Laminas\Json\Json;
use Nanobots\MigrationTool\Model\Import\TableImportAbstract;
use Symfony\Component\Console\Helper\ProgressBar;

class Config extends TableImportAbstract
{
    public const PATHS = [
        'general/%',
        'currency/%',
        'cataloginventory/%',
        'tax/calculation/%',
        'tax/cart_display/%',
        'tax/classes/%',
        'tax/defaults/%',
        'tax/display/%',
        'tax/ignore_notification/%',
        'tax/sales_display/%',
        'tax/weee/%'
    ];

    protected $valueMap = [];

    protected bool $truncate = false;

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns = []): array
    {
        $m1Entity['config_id'] = null;

        if (isset($this->valueMap[$m1Entity['value']])) {
            $m1Entity['value'] = $this->valueMap[$m1Entity['value']];
        } elseif ($this->isSerialized($m1Entity['value'])) {
            $value = unserialize($m1Entity['value'] ?? '');
            $m1Entity['value'] = Json::encode($value);
        }

        return $m1Entity;
    }

    /**
     * @throws Exception
     */
    public function importConfig()
    {
        $this->syncData('core_config_data', 'core_config_data', $this->getPaths());
    }

    /**
     * @param $m1Table
     * @param $m2Table
     * @param null $cond
     * @param array $onDuplicate
     * @return $this
     * @throws \Zend_Db_Exception
     */
    public function syncData($m1Table, $m2Table, $cond = null, $onDuplicate = [])
    {
        $this->output->writeln('<info>Syncing config data</info>');

        $m1Table = $this->connectionHelper->getM1TableName($m1Table);

        $m1TableRows = $this->connectionHelper->getM1connection()->fetchAll(
            'select * from `' . $m1Table . '` ' . ($cond ? 'where ' . $cond : '')
        );

        $this->progressBar = new ProgressBar($this->output, count($m1TableRows));
        $this->progressBar->setBarCharacter('<fg=magenta>=</>');

        $this->connectionHelper->getM2Connection()->beginTransaction();
        try {
            foreach ($m1TableRows as $m1TableRow) {
                $m2Row = $this->prepareRowToInsert($m1TableRow);
                $this->connectionHelper->getM2Connection()->insertOnDuplicate(
                    $m2Table,
                    $m2Row,
                    ['value']
                );
                $this->progressBar->advance();
            }
            $this->connectionHelper->getM2Connection()->commit();
        } catch (Exception $e) {
            $this->connectionHelper->getM2Connection()->rollBack();
            throw $e;
        }

        $this->progressBar->finish();
        $this->output->writeln('');

        return $this;
    }

    /**
     * @return string
     */
    protected function getPaths()
    {
        if (!count(self::PATHS)) {
            return '';
        }

        $conds = [];

        foreach (self::PATHS as $path) {
            $conds[] = "`path` like '$path'";
        }

        return implode(' or ', $conds);
    }

    /**
     * @param $data
     * @return bool
     */
    protected function isSerialized($data): bool
    {
        return (boolean) preg_match('/^((s|i|d|b|a|O|C):|N;)/', $data);
    }
}
