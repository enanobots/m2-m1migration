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
use Nanobots\MigrationTool\Model\Import\Catalog as CatalogImport;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class MigrationCatalog extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:import:catalog";

    /** @var string  */
    public const DESCRIPTION = "Import catalog from Magento 1 database";

    /** @var CatalogImport  */
    protected CatalogImport $catalogImport;

    /**
     * @param \Magento\Framework\App\State $state
     * @param \Nanobots\MigrationTool\Model\Import\Catalog $catalogImport
     */
    public function __construct(
        State $state,
        CatalogImport $catalogImport
    ) {
        $this->catalogImport = $catalogImport;
        parent::__construct($state);
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
     * @throws LocalizedException|Zend_Db_Exception|Zend_Db_Statement_Exception
     */
    protected function migrate(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input, $output);

        $this->output->writeln('Importing only catalog...');
        $this->catalogImport->importCatalog($this->delta);

        return Cli::RETURN_SUCCESS;
    }
}
