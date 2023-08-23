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

use Nanobots\MigrationTool\Model\Import\EavValuesSync;
use Nanobots\MigrationTool\Model\Import\Entities\CustomerAddressSync;
use Nanobots\MigrationTool\Model\Import\Entities\CustomerSync;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class MigrationCustomer extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:import:customer";

    /** @var string  */
    public const DESCRIPTION = "Import customer data from Magento 1 database";

    protected CustomerSync $customerImport;
    protected CustomerAddressSync $customerAddressImport;
    protected EavValuesSync $valuesSync;

    /**
     * MigrationToolCustomer constructor.
     * @param CustomerSync $customerImport
     * @param CustomerAddressSync $customerAddressImport
     * @param State $state
     * @param EavValuesSync $valuesSync
     */
    public function __construct(
        CustomerSync $customerImport,
        CustomerAddressSync $customerAddressImport,
        State $state,
        EavValuesSync $valuesSync
    ) {
        $this->customerImport = $customerImport;
        $this->customerAddressImport = $customerAddressImport;
        $this->state = $state;
        $this->valuesSync = $valuesSync;
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
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     */
    protected function migrate(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input, $output);

        $this->customerImport->sync($this->delta);
        $this->customerImport->updateStatus();
        $this->customerAddressImport->sync($this->delta);
        $this->customerAddressImport->updateStatus();

        return Cli::RETURN_SUCCESS;
    }
}
