<?php
/**
 * Created by Q-Solutions Studio
 * Date: 27.08.2019
 *
 * @category    Nanobots
 * @package     Nanobots_MigrationTool
 * @author      Maciej Buchert <maciej@qsolutionsstudio.com>
 */

namespace Nanobots\MigrationTool\Console;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Helper\Import\PriceFixer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Statement_Mysqli_Exception;

class PriceFix extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:price:fix";

    /** @var string  */
    public const DESCRIPTION = "Fixing issues with product indexer";

    protected PriceFixer $priceFixer;

    /**
     * PriceFix constructor.
     * @param PriceFixer $priceFixer
     * @param State $state
     */
    public function __construct(
        PriceFixer $priceFixer,
        State $state
    ) {
        parent::__construct($state);
        $this->priceFixer = $priceFixer;
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
     * @throws Zend_Db_Statement_Mysqli_Exception|LocalizedException
     */
    protected function migrate(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input, $output);

        $this->output->writeln('<info>Running price fixer...</info>');
        $this->priceFixer->fixPrices();

        return Cli::RETURN_SUCCESS;
    }
}
