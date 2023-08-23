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
use Nanobots\MigrationTool\Model\Import\Entities\SalesRule;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class MigrationSalesRules extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:import:salesrules";

    /** @var string  */
    public const DESCRIPTION = "Import sales rule data";

    /** @var SalesRule  */
    protected SalesRule $salesrule;

    /**
     * @param SalesRule $salesRule
     * @param State $state
     */
    public function __construct(
        SalesRule $salesRule,
        State $state
    ) {
        $this->salesrule = $salesRule;
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
        $this->salesrule->importData();
        return Cli::RETURN_SUCCESS;
    }
}
