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
use Nanobots\MigrationTool\Model\Import\Cms\Blocks;
use Nanobots\MigrationTool\Model\Import\Cms\Pages;
use Nanobots\MigrationTool\Model\Import\Entities\CreditMemo as CreditMemoImport;
use Nanobots\MigrationTool\Model\Import\Entities\CustomerAddressSync;
use Nanobots\MigrationTool\Model\Import\Entities\CustomerSync;
use Nanobots\MigrationTool\Model\Import\Entities\Invoice as InvoiceImport;
use Nanobots\MigrationTool\Model\Import\Entities\Order as OrderImport;
use Nanobots\MigrationTool\Model\Import\Entities\StockSync;
use Nanobots\MigrationTool\Model\Import\Setup\Config as ConfigImport;
use Nanobots\MigrationTool\Model\Import\Setup\EavAttributes;
use Nanobots\MigrationTool\Model\Import\Setup\SequenceTables as SequenceTables;
use Nanobots\MigrationTool\Model\Import\Setup\StoreGroups;
use Nanobots\MigrationTool\Model\Import\Setup\StoreGroups as StoreGroupsImport;
use Nanobots\MigrationTool\Model\Import\Setup\Stores as StoresImport;
use Nanobots\MigrationTool\Model\Import\Setup\Tax as TaxImport;
use Nanobots\MigrationTool\Model\Import\Setup\Websites as WebsitesImport;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class Migration extends MigrationAbstract
{
    /** @var string */
    public const NAME = "nanobots:import:full";

    /** @var string */
    public const DESCRIPTION = "Import data from Magento 1 database";

    protected CatalogImport $catalogImport;
    protected StoresImport $storesImport;
    protected StoreGroupsImport $storeGroupsImport;
    protected WebsitesImport $websitesImport;
    protected SequenceTables $sequenceTables;
    protected EavAttributes $eavAttributes;
    protected CustomerSync $customerImport;
    protected CustomerAddressSync $customerAddressImport;
    protected OrderImport $orderImport;
    protected Blocks $blocks;
    protected Pages $pages;
    protected CreditMemoImport $creditMemoImport;
    protected InvoiceImport $invoiceImport;
    protected StockSync $stockSync;
    protected TaxImport $taxImport;
    protected ConfigImport $configImport;

    /**
     * MigrationTool constructor.
     * @param OrderImport $orderImport
     * @param CustomerSync $customerImport
     * @param CustomerAddressSync $customerAddressImport
     * @param State $state
     * @param EavAttributes $eavAttributes
     * @param CatalogImport $catalogImport
     * @param StoresImport $stores
     * @param StoreGroupsImport $storeGroups
     * @param WebsitesImport $websites
     * @param SequenceTables $sequenceTables
     * @param Blocks $blocks
     * @param Pages $pages
     * @param CreditMemoImport $creditMemoImport
     * @param InvoiceImport $invoiceImport
     * @param StockSync $stockSync
     */
    public function __construct(
        OrderImport $orderImport,
        CustomerSync $customerImport,
        CustomerAddressSync $customerAddressImport,
        State $state,
        EavAttributes $eavAttributes,
        CatalogImport $catalogImport,
        StoresImport $stores,
        StoreGroupsImport $storeGroups,
        WebsitesImport $websites,
        SequenceTables $sequenceTables,
        Blocks $blocks,
        Pages $pages,
        CreditMemoImport $creditMemoImport,
        InvoiceImport $invoiceImport,
        StockSync $stockSync,
        TaxImport $taxImport,
        ConfigImport $configImport
    ) {
        $this->orderImport = $orderImport;
        $this->customerImport = $customerImport;
        $this->customerAddressImport = $customerAddressImport;
        $this->state = $state;
        $this->eavAttributes = $eavAttributes;
        $this->catalogImport = $catalogImport;
        $this->storesImport = $stores;
        $this->storeGroupsImport = $storeGroups;
        $this->websitesImport = $websites;
        $this->sequenceTables = $sequenceTables;
        $this->blocks = $blocks;
        $this->pages = $pages;
        $this->creditMemoImport = $creditMemoImport;
        $this->invoiceImport = $invoiceImport;
        $this->stockSync = $stockSync;
        $this->taxImport = $taxImport;
        $this->configImport = $configImport;
        parent::__construct($state);
    }

    /**
     * @return void
     */
    protected function configure(): void
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

        $this->output->writeln('Importing websites config...');
        $this->websitesImport->insertConfig();

        $this->output->writeln('Importing store groups config...');
        $this->storeGroupsImport->insertConfig();

        $this->output->writeln('Importing store views config...');
        $this->storesImport->insertConfig();

        $this->output->writeln('Importing configuration...');
        $this->configImport->importConfig();

        $this->output->writeln('Importing taxes...');
        $this->taxImport->importData();

        $this->output->writeln('Matching Attributes...');
        $this->eavAttributes->matchEavAttributes();

        if (!$this->delta) {
            $this->output->writeln('Recreating sequence tables...');
            $this->sequenceTables->recreatedSequenceTables();
        }

        $this->customerImport->sync();
        $this->customerImport->updateStatus();
        $this->customerAddressImport->sync();
        $this->customerAddressImport->updateStatus();

        $this->orderImport->sync($this->delta);
        $this->invoiceImport->sync($this->delta);
        $this->creditMemoImport->sync($this->delta);
        $this->catalogImport->importCatalog($this->delta);
        $this->stockSync->sync();

        $this->output->writeln('Syncing CMS blocks...');
        $this->blocks->sync($this->delta);

        $this->output->writeln('Syncing CMS pages...');
        $this->pages->sync($this->delta);

        return Cli::RETURN_SUCCESS;
    }
}
