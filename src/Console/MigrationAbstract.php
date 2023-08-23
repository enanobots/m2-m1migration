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

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

abstract class MigrationAbstract extends Command
{
    protected InputInterface $input;
    protected OutputInterface $output;
    protected State $state;
    protected bool $delta;

    /**
     * MigrationToolAbstract constructor.
     * @param State $state
     * @param string|null $name
     */
    public function __construct(State $state, string $name = null)
    {
        parent::__construct($name);
        $this->state = $state;
    }

    protected function configure()
    {
        $this->setDefinition(
            new InputDefinition(
                [
                    new InputOption(
                        'delta',
                        'd',
                        InputOption::VALUE_NONE,
                        'Apply data delta'
                    )
                ]
            )
        );

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws LocalizedException
     */
    protected function init(InputInterface $input, OutputInterface $output): void
    {
        try {
            $this->state->getAreaCode();
        } catch (LocalizedException $e) {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        }

        $this->input = $input;
        $this->output = $output;

        $this->delta = (bool)$this->input->getOption('delta');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
     {
        try {
            return $this->migrate($input, $output);
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            $output->writeln("<error>{$e->getTraceAsString()}</error>");

            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws LocalizedException|Zend_Db_Exception|Zend_Db_Statement_Exception
     */
    abstract protected function migrate(InputInterface $input, OutputInterface $output): int;
}
