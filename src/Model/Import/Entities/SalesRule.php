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

namespace Nanobots\MigrationTool\Model\Import\Entities;

use Nanobots\MigrationTool\Model\Import\TableImportAbstract as TableImport;

class SalesRule extends TableImport
{
    /** @var array  */
    const CONDITIONS_SERIALIZED_MAP = [
        'salesrule\/rule_condition_address',
        'salesrule\/rule_condition_combine',
        'salesrule\/rule_condition_product_combine',
        'salesrule\/rule_condition_product_found',
        'salesrule\/rule_condition_product_subselect',
        'salesrule\/rule_condition_product',
    ];

    /** @var array  */
    public $m2Map = [];

    /** @var string  */
    protected string $m2Table;

    /**  */
    public function mapM1ClassNames()
    {
        foreach (self::CONDITIONS_SERIALIZED_MAP as $m1Class) {
            $this->m2Map[] = $this->convertM1ClassToM2Class($m1Class);
        }
    }

    /**
     * @param $m1ClassMap
     * @return string
     */
    public function convertM1ClassToM2Class($m1ClassMap)
    {
        list($module, $class) = explode('\/', $m1ClassMap);
        $m2Module = "Magento\\\\"  . $module . '\\\\Model';
        $m2Module = str_replace('salesrule' , 'SalesRule', $m2Module);

        $classMap = explode('_', $class);
        $model = '';
        foreach ($classMap as $class) {
            $model .= '\\\\' . ucwords($class);
        }

        $fullClass = $m2Module . $model;
        return $fullClass;
    }

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @param bool $isEnterprise
     * @return array|mixed
     * @throws \Zend_Db_Adapter_Exception
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns): array
    {
        $insertRow = [];
        foreach($m1Entity as $columnName => $value) {
            /** match M1 row to M2 */
            if (in_array($columnName, $matchingColumns)) {
                switch ($columnName) {
                    case 'conditions_serialized': {
                        $insertRow[$columnName] = str_replace(
                            self::CONDITIONS_SERIALIZED_MAP,
                            $this->m2Map,
                            json_encode(unserialize($value ?? '')));
                        break;
                    }
                    case 'actions_serialized': {
                        $m1Values = unserialize($value ?? '');
                        $conditions = $m1Values['conditions'] ?? null;

                        if (is_array($conditions)) {
                            foreach ($conditions as &$condition) {
                                if (isset($condition['type'])) {
                                    if ($condition['type'] == "salesrule/rule_condition_product") {
                                         $condition['value'] = $condition['value'];
                                    }
                                }
                            }
                        }

                        $m1Values['conditions'] = $conditions;

                        $insertRow[$columnName] = str_replace(
                            self::CONDITIONS_SERIALIZED_MAP,
                            $this->m2Map,
                            json_encode($m1Values));
                        break;
                    }
                    case 'attribute_id': {
                        $attributeCode = $this->connectionHelper->getM1connection()->fetchOne(
                            'select `attribute_code` from `eav_attribute` where `attribute_id` = "' . $m1Entity['attribute_id'] .'"'
                        );

                        $m2AttributeId = $this->connectionHelper->getM2Connection()->fetchOne(
                            'select `attribute_id` from `eav_attribute` where `attribute_code` = "' . $attributeCode . '"'
                        );

                        $insertRow['attribute_id'] = $m2AttributeId;
                        break;
                    }

                    default: {
                        $insertRow[$columnName] = $value;
                        break;
                    }
                }
            }
        }

        return $insertRow;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Exception
     * @throws \Zend_Db_Statement_Exception
     */
    public function importData()
    {
        $this->mapM1ClassNames();
        $this->connectionHelper->getM2Connection()->query('SET FOREIGN_KEY_CHECKS=0');

        $this->connectionHelper->getM2Connection()->truncateTable('salesrule');
        $this->connectionHelper->getM2Connection()->truncateTable('salesrule_coupon');
        $this->connectionHelper->getM2Connection()->truncateTable('salesrule_coupon_usage');
        $this->connectionHelper->getM2Connection()->truncateTable('salesrule_customer');
        $this->connectionHelper->getM2Connection()->truncateTable('salesrule_customer_group');
        $this->connectionHelper->getM2Connection()->truncateTable('salesrule_label');
        $this->connectionHelper->getM2Connection()->truncateTable('salesrule_product_attribute');
        $this->connectionHelper->getM2Connection()->truncateTable('salesrule_website');

        $this
            ->syncData('salesrule' , 'salesrule', true)
            ->syncData('salesrule_coupon', 'salesrule_coupon')
            ->syncData('salesrule_coupon_usage' , 'salesrule_coupon_usage')
            ->syncData('salesrule_customer', 'salesrule_customer')
            ->syncData('salesrule_customer_group', 'salesrule_customer_group', true)
            ->syncData('salesrule_label', 'salesrule_label')
            ->syncData('salesrule_product_attribute', 'salesrule_product_attribute', true)
            ->syncData('salesrule_website', 'salesrule_website', true)
        ;

        $this->connectionHelper->getM2Connection()->query('SET FOREIGN_KEY_CHECKS=1');
    }
}
