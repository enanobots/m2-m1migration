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

class MigrationShipment extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:import:shipment";

    /** @var string  */
    public const DESCRIPTION = "Import shipment data";

    protected SequenceTables $sequenceTables;

    /**
     * MigrationToolSales constructor.
     * @param SequenceTables $sequenceTables
     * @param State $state
     */
    public function __construct(
        SequenceTables $sequenceTables,
        State $state
    ) {
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
        return Cli::RETURN_SUCCESS;
    }
}
