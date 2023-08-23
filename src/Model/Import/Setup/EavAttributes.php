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

namespace Nanobots\MigrationTool\Model\Import\Setup;

use Exception;
use Magento\Catalog\Model\Attribute\Backend\Startdate;

use Magento\Catalog\Model\Category\Attribute\Backend\Image;
use Magento\Catalog\Model\Category\Attribute\Source\Page;
use Magento\Catalog\Model\Product\Attribute\Backend\Price;
use Magento\Catalog\Model\Product\Attribute\Frontend\Image as ImageFrontend;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Eav\Model\Entity\Attribute\Backend\Datetime;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Helper\Connection as ConnectionHelper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class EavAttributes
{
    /** @var array  */
    public const EAV_ENTITY_TYPES = [
        3  => 3,
        4 => 4,
    ];

    /** @var array  */
    public const SKIP_ATTRIBUTES_CODES = [
        'is_recurring',
        'msrp_enabled',
        'recurring_profile',
        'group_price',
    ];

    /** @var array */
    public const BLOCKED_COLS = [
        'is_visible_on_checkout'
    ];

    /** @var array  */
    public const M2_INSTALL_ENTITY_VALUES = [];

    /** @var array  */
    public const M2_EAV_EXTRA_TABLES = [
        'eav_attribute_option_swatch',
        'eav_attribute_match'
    ];

    public const FRONTEND_MODEL_MATCH = [
        'catalog/category_attribute_frontend_image' => ImageFrontend::class
    ];

    /** @var array  */
    public const BACKEND_MODEL_MATCH = [
        'catalog/category_attribute_backend_image' => Image::class,
        'catalog/product_attribute_backend_price' => Price::class,
        'catalog/product_attribute_backend_startdate' => Startdate::class,
        'eav/entity_attribute_backend_datetime' => Datetime::class,
        'eav/entity_attribute_backend_array' => ArrayBackend::class,
        'catalog/category_attribute_backend_sortby' => 'Magento\Eav\Model\Entity\Attribute\Backend\Sortby'
    ];

    /** @var array  */
    public const SOURCE_MODEL_MATCH = [
        'eav/entity_attribute_source_table' => Table::class,
        'eav/entity_attribute_backend_datetime' > Datetime::class,
        'eav/entity_attribute_backend_array' => ArrayBackend::class,
        'eav/entity_attribute_source_boolean' => Boolean::class,
        'catalog/category_attribute_source_sortby' => 'Magento\Eav\Model\Entity\Attribute\Source\Sortby',
        'catalog/category_attribute_source_page' => Page::class,
    ];

    /** @var array  */
    public const ENTITY_RELATED_TABLES = [
        1 => [
            'customer_form_attribute'
        ],
        2 => [],
        3 => [
            'eav_entity_attribute'
        ],
        4 => [
            'eav_entity_attribute'
        ]
    ];

    /** @var ConnectionHelper  */
    protected $connectionHelper;

    /**
     * @var ConsoleOutput
     */
    private ConsoleOutput $output;

    /**
     * EavAttributes constructor.
     * @param ConnectionHelper $connection
     * @param ConsoleOutput $output
     * @throws LocalizedException
     * @throws Zend_Db_Statement_Exception
     */
    public function __construct(
        ConnectionHelper $connection,
        ConsoleOutput $output
    ) {
        $this->connectionHelper = $connection;
        $this->connectionHelper->initializeMagento1Connection();
        $this->output = $output;
    }

    /**
     * @return AdapterInterface|Mysql
     */
    private function getM1Connection()
    {
        return $this->connectionHelper->getM1connection();
    }

    /**
     * @return AdapterInterface|Mysql
     */
    private function getM2Connection()
    {
        return $this->connectionHelper->getM2Connection();
    }

    /**
     * @return $this
     */
    private function clearPreviouslyImportedAttributes(): self
    {
        foreach (self::M2_INSTALL_ENTITY_VALUES as $entityTypeId => $attributeId) {
            /** clear attributes above installed (clear EE install) */
            $this->getM2Connection()->delete(
                'eav_attribute',
                'attribute_id > "' . $attributeId . '" and `entity_type_id` = "' . $entityTypeId . '" '
            );

            /** remove attribute option and values */
            if ($entityTypeId === 4) {
                $this->getM2Connection()->delete(
                    'eav_attribute',
                    'attribute_id > "' . $attributeId . '"'
                );
            }
        }

        return $this;
    }

    /**
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     * @throws Exception
     */
    public function matchEavAttributes(): void
    {
        /**
         * truncate tables not used in the project (bronson)
         */
        $this->clearPreviouslyImportedAttributes();

        $this->getM2Connection()->query('SET foreign_key_checks = 0');
        $this->getM2Connection()->truncateTable('eav_attribute_option_value');
        $this->getM2Connection()->truncateTable('eav_attribute_option');
        $this->getM2Connection()->query('SET foreign_key_checks = 1');

        foreach (self::M2_EAV_EXTRA_TABLES as $eavTable) {
            $this->getM2Connection()->truncateTable($eavTable);
        }

        foreach (self::EAV_ENTITY_TYPES as $m1EntityTypeId => $m2EntityTypeId) {
            $eavAttributes = $this->getM1Connection()->fetchAll(
                'select * from `eav_attribute` where `entity_type_id` = (:entity_type_id)',
                [
                    'entity_type_id' => $m1EntityTypeId
                ]
            );

            foreach ($eavAttributes as $eavAttribute) {
                $attributeCode = $eavAttribute['attribute_code'];

                if (!$this->shouldSkip($attributeCode)) {
                    $m2AttributeId = $this->isAttributeImported($attributeCode, $m2EntityTypeId);
                    if ($m2AttributeId) {
                        $this->output->write("<info>Updating attribute options for $attributeCode (Entity Type ID: $m2EntityTypeId)...</info>");
                        $this->insertM2AttributeLabels($eavAttribute['attribute_id'], $m2AttributeId);
                        $this->insertM2AttributeOptions($eavAttribute['attribute_id'], $m2AttributeId);
                        $this->output->writeln("<comment>OK</comment>");
                    } else {
                        $this->output->write("<info>Creating attribute $attributeCode (Entity Type ID: $m2EntityTypeId)...</info>");
                        $this->insertM2EntityAttribute($eavAttribute, $m2EntityTypeId);
                        $this->output->writeln("<comment>OK</comment>");
                    }
                } else {
                    $this->output->writeln("<info>Skipping attribute $attributeCode (Entity Type ID: $m2EntityTypeId)...</info>");
                }
            }
        }
    }

    /**
     * @param $entityTypeId
     * @return string
     */
    private function getEntityEavAttributeTable($entityTypeId): string
    {
        return $this->getM2Connection()->fetchOne(
            'select `additional_attribute_table` from `eav_entity_type`
              where `entity_type_id` = (:entity_type_id)
            ',
            [
                'entity_type_id' => $entityTypeId,
            ]
        );
    }

    /**
     * @param $attributeCode
     * @param $entityTypeId
     * @return int|null
     */
    private function isAttributeImported($attributeCode, $entityTypeId): ?int
    {
        return $this->getM2Connection()->fetchOne(
            'select `attribute_id` from `eav_attribute`
              where `entity_type_id` = (:entity_type_id)
              and `attribute_code` = (:attribute_code)
            ',
            [
                'entity_type_id' => $entityTypeId,
                'attribute_code' => $attributeCode
            ]
        );
    }

    /**
     * @param $m1backendType
     * @return mixed
     */
    private function matchBackendType(?string $m1backendType): ?string
    {
        return self::BACKEND_MODEL_MATCH[$m1backendType] ?? 'varchar';
    }

    /**
     * @param $m1SourceModel
     * @return mixed
     */
    private function matchSourceModel(?string $m1SourceModel): ?string
    {
        return self::SOURCE_MODEL_MATCH[$m1SourceModel] ?? $m1SourceModel;
    }

    /**
     * @param $m1AttributeId
     * @param $m2AttributeId
     * @throws Exception
     */
    private function insertM2AttributeOptions($m1AttributeId, $m2AttributeId)
    {
        $this->getM2Connection()->beginTransaction();
        $m1AttributeOptions = $this->getM1Connection()->fetchAll(
            'select option_id, sort_order from `eav_attribute_option` where `attribute_id` = (:attribute_id) order by `sort_order`',
            [
                'attribute_id' => $m1AttributeId
            ]
        );

        /** delete imported values */
        $this->getM2Connection()->delete(
            'eav_attribute_option',
            'attribute_id = "' . $m2AttributeId . '"'
        );

        foreach ($m1AttributeOptions as $m1AttributeOption) {
            /** insert attribute options */
            $this->getM2Connection()->insert(
                'eav_attribute_option',
                [
                    'option_id' => $m1AttributeOption['option_id'],
                    'attribute_id' => $m2AttributeId,
                    'sort_order' => $m1AttributeOption['sort_order']
                ]
            );

            $this->getM2Connection()->insert(
                'eav_attribute_match',
                [
                    'm1_attribute_option_id' => $m1AttributeOption['option_id'],
                    'm2_attribute_option_id' => $m1AttributeOption['option_id']
                ]
            );

            $attributeOptionsValue = $this->getM1Connection()->fetchAll(
                'select `option_id`, `value`, `store_id` from `eav_attribute_option_value` where `option_id` = (:option_id) ',
                [
                    'option_id' => $m1AttributeOption['option_id']
                ]
            );

            foreach ($attributeOptionsValue as $item) {
                $this->getM2Connection()->insert(
                    'eav_attribute_option_value',
                    [
                        'option_id' => $item['option_id'],
                        'store_id' => $item['store_id'],
                        'value' => $item['value']
                    ]
                );
            }
        }
        $this->getM2Connection()->commit();
    }

    /**
     * @param $attributeData
     * @param $entityTypeId
     * @throws Zend_Db_Adapter_Exception
     * @throws Exception
     */
    private function insertM2EntityAttribute($attributeData, $entityTypeId)
    {
        $entityTypeEavTable = $this->getEntityEavAttributeTable($entityTypeId);

        $m1EntityTypeTableData = $this->getM1Connection()->fetchAll(
            'select * from `' . $entityTypeEavTable . '`
                where `attribute_id` = (:attribute_id)',
            [
                'attribute_id' => $attributeData['attribute_id']
            ]
        );

        $dataToInsert = $m1EntityTypeTableData[0];

        foreach (self::BLOCKED_COLS as $col) {
            unset($dataToInsert[$col]);
        }

        $this->getM2Connection()->insert(
            'eav_attribute',
            [
                'entity_type_id' => $entityTypeId,
                'attribute_code' => $attributeData['attribute_code'],
                'attribute_model' => null, // only 1 attribute use custom value here
                'backend_model' => $this->matchBackendType($attributeData['backend_model']),
                'backend_type' => $attributeData['backend_type'],
                'backend_table' => $attributeData['backend_table'],
                'frontend_model' => $this->matchFrontendModel($attributeData['frontend_model']),
                'frontend_input' => $attributeData['frontend_input'],
                'frontend_label' => $attributeData['frontend_label'],
                'frontend_class' => $attributeData['frontend_class'],
                'source_model' => $this->matchSourceModel($attributeData['source_model']),
                'is_required' => $attributeData['is_required'],
                'is_user_defined' => $attributeData['is_user_defined'],
                'default_value' => $attributeData['default_value'],
                'is_unique' => $attributeData['is_unique'],
                'note' => $attributeData['note'],
            ]
        );

        $m2EavAttributeId = $this->getM2Connection()->lastInsertId('eav_attribute');
        $dataToInsert['attribute_id'] = $m2EavAttributeId;

        if (isset($dataToInsert['validate_rules'])) {
            $dataToInsert['validate_rules'] = json_encode(unserialize($dataToInsert['validate_rules'] ?? ''));
        }

        /** some values needs to be unset for importer */
        unset($dataToInsert['is_configurable']);

        $this->getM2Connection()->insert(
            $entityTypeEavTable,
            $dataToInsert
        );

        $this->insertM2AttributeLabels($attributeData['attribute_id'], $m2EavAttributeId);

        $this->insertM2AttributeOptions($attributeData['attribute_id'], $m2EavAttributeId);

        foreach (self::ENTITY_RELATED_TABLES[$entityTypeId] as $additionalTable) {
            $m1AttributeSetCond = $entityTypeId !== 4 ? '`attribute_set_id` < 10' : '`attribute_set_id` = 4';
            $extraTableData = $this->getM1Connection()->fetchAll(
                'select * from `' . $additionalTable . '`
                    where `attribute_id` = (:attribute_id)
                        and
                          ' . $m1AttributeSetCond, // this needs to be changes to configurable values
                [
                    'attribute_id' => $attributeData['attribute_id']
                ]
            );

            foreach ($extraTableData as $extraData) {
                /** some values needs to be unset for importer */
                unset($extraData['entity_attribute_id']);

                $extraData['attribute_id'] = $m2EavAttributeId;

                /** assign to attribute set and group / general */
                if ($entityTypeId === 4) {
                    $extraData['entity_type_id'] = 4;
                    $extraData['attribute_set_id'] = 4;
                    $extraData['attribute_group_id'] = 7;
                    $extraData['sort_order'] += 1000;
                }

                if ($entityTypeId === 3) {
                    $extraData['entity_type_id'] = 3;
                    $extraData['attribute_set_id'] = 3;
                    $extraData['attribute_group_id'] = 4;
                    $extraData['sort_order'] += 1000;
                }

                $this->getM2Connection()->insert(
                    $additionalTable,
                    $extraData
                );
            }
        }
    }

    /**
     * @param $frontendModel
     * @return string|null
     */
    protected function matchFrontendModel(?string $frontendModel): ?string
    {
        return self::FRONTEND_MODEL_MATCH[$frontendModel] ?? null;
    }

    /**
     * @param $attributeCode
     * @return bool
     */
    public function shouldSkip($attributeCode): bool
    {
        return in_array($attributeCode, self::SKIP_ATTRIBUTES_CODES, true);
    }

    /**
     * @param string $m1AttributeId
     * @param string $m2AttributeId
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     */
    private function insertM2AttributeLabels(string $m1AttributeId, string $m2AttributeId)
    {
        $this->getM2Connection()->beginTransaction();
        $m1AttributeLabels = $this->getM1Connection()->fetchAll(
            'select `store_id`, `value` from `eav_attribute_label` where `attribute_id` = (:attribute_id)',
            [
                'attribute_id' => $m1AttributeId
            ]
        );

        /** delete imported values */
        $this->getM2Connection()->delete(
            'eav_attribute_label',
            'attribute_id = "' . $m2AttributeId . '"'
        );

        foreach ($m1AttributeLabels as $m1AttributeLabel) {
            /** insert attribute options */
            $this->getM2Connection()->insert(
                'eav_attribute_label',
                [
                    'attribute_id' => $m2AttributeId,
                    'store_id' => $m1AttributeLabel['store_id'],
                    'value' => $m1AttributeLabel['value']
                ]
            );
        }
        $this->getM2Connection()->commit();
    }
}
