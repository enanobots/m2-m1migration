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

class RelationsSync extends EavValuesSync
{
    protected bool $truncate = true;
    protected bool $matchMissingColumns = false;
    protected bool $validateEntityExists = true;

    /**
     * @return array
     */
    public function getTablesToSync(): array
    {
        return [
            'catalog_product_website' => 'catalog_product_website',
            'catalog_category_product' => 'catalog_category_product',
            'catalog_product_relation' => 'catalog_product_relation',
            'catalog_product_super_attribute' => 'catalog_product_super_attribute',
            'catalog_product_super_attribute_label' => 'catalog_product_super_attribute_label',
            'catalog_product_super_link' => 'catalog_product_super_link',
            'catalog_product_link' => 'catalog_product_link',
            'catalog_product_link_attribute_decimal' => 'catalog_product_link_attribute_decimal',
            'catalog_product_link_attribute_int' => 'catalog_product_link_attribute_int',
            'catalog_product_link_attribute_varchar' => 'catalog_product_link_attribute_varchar',
        ];
    }

    /**
     * @return string[]
     */
    public function getTablesToCleanBrokenLinks(): array
    {
        return [
            'catalog_product_link_attribute_decimal',
            'catalog_product_link_attribute_int',
            'catalog_product_link_attribute_varchar',
            ];
    }

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns): array
    {
        if (isset($m1Entity['attribute_id'])) {
            $mappedAttrId = $this->attributeIds[$m1Entity['attribute_id']] ?? null;
            if ($mappedAttrId) {
                $m1Entity['attribute_id'] = $mappedAttrId;
            } else {
                return [];
            }
        }

        return $m1Entity;
    }

    /**
     * @param bool $delta
     * @throws LocalizedException|Zend_Db_Adapter_Exception|Zend_Db_Exception|Zend_Db_Statement_Exception
     */
    public function sync(bool $delta = false): void
    {
        $this->setEntities([3, 4]);
        $this->initAttributeIds();

        foreach ($this->getTablesToSync() as $m1Table => $m2Table) {
            $this->syncData($m1Table, $m2Table);
        }

        $this->cleanBroken();
    }

    /**
     * @param string|null $table
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     */
    protected function cleanBroken(string $table = null): void
    {
        $this->output->writeln('<info>Cleaning broken website relations...</info>');
        $sql = <<<SQL
DELETE cpw FROM catalog_product_website cpw LEFT JOIN catalog_product_entity cpe ON cpe.entity_id = cpw.product_id WHERE cpe.entity_id IS NULL
SQL;
        $this->connectionHelper->getM2Connection()->query($sql);

        $this->output->writeln('<info>Cleaning broken category relations...</info>');
        $sql = <<<SQL
DELETE ccp FROM catalog_category_product ccp
LEFT JOIN catalog_product_entity cpe ON cpe.entity_id = ccp.product_id
WHERE cpe.entity_id IS NULL
SQL;
        $this->connectionHelper->getM2Connection()->query($sql);

        $sql = <<<SQL
DELETE ccp FROM catalog_category_product ccp
LEFT JOIN catalog_category_entity cce ON cce.entity_id = ccp.category_id
WHERE cce.entity_id IS NULL
SQL;
        $this->connectionHelper->getM2Connection()->query($sql);

        $this->output->writeln('<info>Cleaning broken configurable product relations...</info>');
        $sql = <<<SQL
DELETE cpr FROM catalog_product_relation cpr
LEFT JOIN catalog_product_entity cpe
ON cpe.entity_id = cpr.parent_id WHERE cpe.entity_id IS NULL
SQL;
        $this->connectionHelper->getM2Connection()->query($sql);
        $sql = <<<SQL
DELETE cpr FROM catalog_product_relation cpr
LEFT JOIN catalog_product_entity cpe
ON cpe.entity_id = cpr.child_id WHERE cpe.entity_id IS NULL
SQL;
        $this->connectionHelper->getM2Connection()->query($sql);

        $sql = <<<SQL
DELETE cpsl FROM catalog_product_super_link cpsl
LEFT JOIN catalog_product_entity cpe
ON cpe.entity_id = cpsl.product_id WHERE cpe.entity_id IS NULL
SQL;
        $this->connectionHelper->getM2Connection()->query($sql);

        $sql = <<<SQL
DELETE cpsl FROM catalog_product_super_link cpsl
LEFT JOIN catalog_product_entity cpe
ON cpe.entity_id = cpsl.parent_id WHERE cpe.entity_id IS NULL
SQL;
        $this->connectionHelper->getM2Connection()->query($sql);

        $this->output->writeln('<info>Cleaning broken product relations...</info>');
        $sql = <<<SQL
DELETE cpl FROM catalog_product_link cpl
LEFT JOIN catalog_product_entity cpe
ON cpe.entity_id = cpl.product_id WHERE cpe.entity_id IS NULL
SQL;
        $this->connectionHelper->getM2Connection()->query($sql);

        $sql = <<<SQL
DELETE cpl FROM catalog_product_link cpl
LEFT JOIN catalog_product_entity cpe
ON cpe.entity_id = cpl.linked_product_id WHERE cpe.entity_id IS NULL
SQL;
        $this->connectionHelper->getM2Connection()->query($sql);

        foreach ($this->getTablesToCleanBrokenLinks() as $_table) {
            $this->output->writeln("<info>Cleaning broken '$_table' relations...</info>");

            $sql = <<<SQL
DELETE cplv FROM $_table cplv LEFT JOIN catalog_product_link cpl ON cpl.link_id = cplv.link_id WHERE cpl.link_id IS NULL
SQL;
            $this->connectionHelper->getM2Connection()->query($sql);
        }
    }
}
