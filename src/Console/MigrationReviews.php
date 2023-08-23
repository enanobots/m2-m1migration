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
use Nanobots\MigrationTool\Model\Import\Entities\ReviewSync;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class MigrationReviews extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:import:reviews";

    /** @var string  */
    public const DESCRIPTION = "Reviews import";

    protected ReviewSync $reviews;

    /**
     * PriceFix constructor.
     * @param ReviewSync $reviews
     * @param State $state
     */
    public function __construct(
        ReviewSync $reviews,
        State $state
    ) {
        parent::__construct($state);
        $this->reviews = $reviews;
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
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     */
    protected function migrate(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input, $output);

        $this->reviews->sync();

        return Cli::RETURN_SUCCESS;
    }
}
