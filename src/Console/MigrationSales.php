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

declare(strict_types=1);

namespace Nanobots\MigrationTool\Console;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Model\Import\Entities\CreditMemo as CreditMemoImport;
use Nanobots\MigrationTool\Model\Import\Entities\Invoice as InvoiceImport;
use Nanobots\MigrationTool\Model\Import\Entities\Order as OrderImport;
use Nanobots\MigrationTool\Model\Import\Setup\SequenceTables as SequenceTables;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class MigrationSales extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:import:sales";

    /** @var string  */
    public const DESCRIPTION = "Import sales data";

    protected SequenceTables $sequenceTables;
    protected OrderImport $orderImport;
    protected InvoiceImport $invoiceImport;
    protected CreditMemoImport $creditMemoImport;

    /**
     * MigrationToolSales constructor.
     * @param CreditMemoImport $creditMemoImport
     * @param SequenceTables $sequenceTables
     * @param InvoiceImport $invoiceImport
     * @param OrderImport $orderImport
     * @param State $state
     */
    public function __construct(
        CreditMemoImport $creditMemoImport,
        SequenceTables $sequenceTables,
        InvoiceImport $invoiceImport,
        OrderImport $orderImport,
        State $state
    ) {
        $this->creditMemoImport = $creditMemoImport;
        $this->invoiceImport = $invoiceImport;
        $this->orderImport = $orderImport;
        $this->sequenceTables = $sequenceTables;
        parent::__construct($state);
    }

    /**  */
    protected function configure()
    {
        $this->setName(self::NAME)->setDescription(self::DESCRIPTION);
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws LocalizedException
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     */
    protected function migrate(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input, $output);

        if (!$this->delta) {
            $this->sequenceTables->recreatedSequenceTables();
        }
        $this->orderImport->sync($this->delta);
        $this->invoiceImport->sync($this->delta);
        $this->creditMemoImport->sync($this->delta);

        return Cli::RETURN_SUCCESS;
    }
}
