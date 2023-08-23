<?php
/**
 * Created by Q-Solutions Studio
 *
 * @category    Nanobots
 * @package     Nanobots_MigrationTool
 * @author      Wojciech M. Wnuk <wojtek@qsolutionsstudio.com>
 */

namespace Nanobots\MigrationTool\Console;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Helper\Import\ScopeFixer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Statement_Exception;

class ScopeFix extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:scope:fix";

    /** @var string  */
    public const DESCRIPTION = "Fixing issues with scope";

    protected ScopeFixer $scopeFixer;

    /**
     * PriceFix constructor.
     * @param ScopeFixer $scopeFixer
     * @param State $state
     */
    public function __construct(
        ScopeFixer $scopeFixer,
        State $state
    ) {
        parent::__construct($state);
        $this->scopeFixer = $scopeFixer;
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
     * @throws LocalizedException|Zend_Db_Statement_Exception
     */
    protected function migrate(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input, $output);

        $this->output->writeln('<info>Running attribute scopes fixer...</info>');
        $this->scopeFixer->fixScopes();

        return Cli::RETURN_SUCCESS;
    }
}
