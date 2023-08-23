<?php
/**
 * Created by Q-Solutions Studio
 *
 * @category    Nanobots
 * @package     Nanobots_MigrationTool
 * @author      Wojciech M. Wnuk <wojtek@qsolutionsstudio.com>
 */

namespace Nanobots\MigrationTool\Console;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Helper\Import\StatusFixer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusFix extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:status:fix";

    /** @var string  */
    public const DESCRIPTION = "Fixing issues with status";

    /**
     * @var StatusFixer
     */
    private $statusFixer;

    /**
     * PriceFix constructor.
     * @param StatusFixer $statusFixer
     * @param State $state
     */
    public function __construct(
        StatusFixer $statusFixer,
        State $state
    ) {
        parent::__construct($state);
        $this->statusFixer = $statusFixer;
    }

    protected function configure()
    {
        $this->setName(self::NAME)->setDescription(self::DESCRIPTION);
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws LocalizedException
     */
    protected function migrate(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input, $output);

        $this->output->writeln('<info>Running status fixer...</info>');
        $this->statusFixer->fixStatus();

        return Cli::RETURN_SUCCESS;
    }
}
