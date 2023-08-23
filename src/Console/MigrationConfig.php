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
use Nanobots\MigrationTool\Model\Import\Setup\Config as ConfigImport;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationConfig extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:import:config";

    /** @var string  */
    public const DESCRIPTION = "Config import";

    protected ConfigImport $configImport;

    /**
     * PriceFix constructor.
     * @param ConfigImport $configImport
     * @param State $state
     */
    public function __construct(
        ConfigImport $configImport,
        State $state
    ) {
        parent::__construct($state);
        $this->configImport = $configImport;
    }

    protected function configure()
    {
        $this->setName(self::NAME)->setDescription(self::DESCRIPTION);
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws LocalizedException
     */
    protected function migrate(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input, $output);
        $output->writeln('Importing configuration...');
        $this->configImport->importConfig();

        return Cli::RETURN_SUCCESS;
    }
}
