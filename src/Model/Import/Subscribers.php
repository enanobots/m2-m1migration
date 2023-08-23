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

namespace Nanobots\MigrationTool\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class Subscribers extends TableImportAbstract
{
    /**
     * @param $m1Entity
     * @param array $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns = []) : array
    {
        return $m1Entity;
    }

    /**
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function syncSubscribers() : void
    {
        $this->syncData('newsletter_subscriber', 'newsletter_subscriber');
    }
}
