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
use Nanobots\MigrationTool\Model\Import\Cms\Blocks as CmsImportBlocks;
use Nanobots\MigrationTool\Model\Import\Cms\Pages as CmsImportPages;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class MigrationCmsBlocks extends MigrationAbstract
{
    /** @var string  */
    public const NAME = "nanobots:import:cms_blocks";

    /** @var string  */
    public const DESCRIPTION = "Import CMS Pages and Blocks from Magento 1 Database";

    /** @var CmsImportBlocks  */
    protected CmsImportBlocks $cmsImportBlocks;

    /** @var CmsImportPages  */
    protected CmsImportPages $cmsImportPages;

    /**
     * MigrationToolCms constructor.
     * @param CmsImportBlocks $cmsImportBlocks
     * @param CmsImportPages $cmsImportPages
     * @param State $state
     */
    public function __construct(
        CmsImportBlocks $cmsImportBlocks,
        CmsImportPages $cmsImportPages,
        State $state
    ) {
        $this->cmsImportBlocks = $cmsImportBlocks;
        $this->cmsImportPages = $cmsImportPages;
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
     * @throws LocalizedException|Zend_Db_Adapter_Exception|Zend_Db_Exception|Zend_Db_Statement_Exception
     */
    protected function migrate(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input, $output);
        $this->cmsImportBlocks->sync($this->delta);
        return Cli::RETURN_SUCCESS;
    }
}
