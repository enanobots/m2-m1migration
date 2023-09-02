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

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\LocalizedException;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class EavValuesSync extends SyncAbstract
{
    /** @var array */
    public const FORCED_MAPPABLES = [];

    /** @var int  */
    public const M1_BATCH_SIZE = 2500;

    /** @var array  */
    protected const ENTITIES = [
        1 => Customer::ENTITY,
        2 => Customer::ENTITY . '_address', // address entity model is missing the constant
        3 => Category::ENTITY,
        4 => Product::ENTITY
    ];

    /** @var string[]  */
    protected const BACKEND_TYPES = [
        'varchar',
        'int',
        'text',
        'datetime',
        'decimal',
    ];

    protected array $mappableAttributes = [];

    protected array $configurableAttributes = [];

    protected array $optionsMap = [];

    protected array $attributeIds = [];

    protected array $entities = [];

    protected array $mismatchedAttributesMap = [];

    protected array $mismatchedAttributeIds = [];

    protected array $m1Attributes = [];

    protected array $m2Attributes = [];

    protected array $entityIds = [];

    protected bool $validateEntityExists = false;

    protected bool $categoryIdsAttrId = false;

    protected bool $nullifyValueId = true;

    protected array $productNamesIterators = [];

    /**
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function sync(): void
    {
        $this->initMappableAttributes();
        $this->initTypeMismatchedAttributes();
        $this->initOptionsMap();
        $this->initAttributeIds();
        $this->initEntityIds();

        $notConfCond = null;
        if ($this->mismatchedAttributeIds) {
            $notConfCond = 'attribute_id NOT IN(' . implode(',', $this->mismatchedAttributeIds) . ') and `value` is not null';
        }

        $onDuplicate = ['value'];
        $this->output->writeln('<info>Importing attribute values...</info>');
        foreach ($this->getTablesToSync() as $m1Table => $m2Table) {
            $this->syncData($m1Table, $m2Table, $notConfCond, $onDuplicate);
            $this->cleanBroken($m2Table);
        }

        $this->output->writeln('<info>Importing attributes with mismatched types...</info>');
        foreach ($this->mismatchedAttributesMap as $entityTypeId => $attributeMapped) {
            foreach ($attributeMapped as $code => $types) {
                $this->syncData(
                    self::ENTITIES[$entityTypeId] . '_entity_' . $types['m1_type'],
                    self::ENTITIES[$entityTypeId] . '_entity_' . $types['m2_type'],
                    "attribute_id = {$this->m1Attributes[$entityTypeId][$code]} and `value` is not null",
                    $onDuplicate
                );
                $this->cleanBroken(self::ENTITIES[$entityTypeId] . '_entity_' . $types['m2_type']);
            }
        }
    }

    /**
     * @return array
     */
    protected function getTablesToSync(): array
    {
        $tables = [];

        foreach (self::ENTITIES as $entityTypeId => $entity) {
            if ($this->shouldSync($entityTypeId)) {
                foreach (self::BACKEND_TYPES as $backendType) {
                    $tables[$entity . '_entity_' . $backendType] = $entity . '_entity_' . $backendType;
                }
            }
        }

        return $tables;
    }

    /**
     * @param string|null $table
     * @throws LocalizedException
     * @throws Zend_Db_Adapter_Exception
     */
    protected function cleanBroken(string $table = null): void
    {
        if ($table) {
            $entityTable = $this->getEntityTable($table);
            if ($this->isEntity($entityTable)) {
                if ($this->connectionHelper->getM2Connection()->tableColumnExists($table, 'entity_id')) {
                    $sql = <<<SQL
DELETE eav FROM $table eav LEFT JOIN $entityTable e ON eav.entity_id = e.entity_id WHERE e.entity_id IS NULL
SQL;
                    $this->connectionHelper->getM2Connection()->query($sql);
                }
            }
        }
    }

    /**
     * @param array $entities
     * @return EavValuesSync
     */
    public function setEntities(array $entities): self
    {
        $this->entities = $entities;

        return $this;
    }

    /**
     * @return int|null
     */
    protected function getEntityTypeId(): ?int
    {
        return null;
    }

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns): array
    {
        if ($this->isMappable($m1Entity)) {
            $m1Entity['value'] = $this->mapOption($m1Entity['value']);
        } elseif ($this->isConfigurable($m1Entity)) {
            $m1Entity['value'] = $this->filterOption($m1Entity['value']);
        }
        if ($this->nullifyValueId) {
            $m1Entity['value_id'] = null;
        }
        unset($m1Entity['entity_type_id']);

        return $this->mapAndValidateAttribute($m1Entity);
    }

    /**
     * @param array $m1Entity
     */
    protected function updateProductName(array &$m1Entity): void
    {
        $name = $m1Entity['value'];
        $storeId = $m1Entity['store_id'];

        if(isset($this->productNamesIterators[$storeId][$name])) {
            $this->productNamesIterators[$storeId][$name]++;
        } else {
            $this->productNamesIterators[$storeId][$name] = 0;
        }

        if($this->productNamesIterators[$storeId][$name] !== 0) {
            $m1Entity['value'] = sprintf(
                '%s/%s',
                trim($name, ' '),
                $this->productNamesIterators[$storeId][$name]
            );
        }
    }

    protected function initMappableAttributes(): void
    {
        $cond = <<<SQL
frontend_input IN('select', 'multiselect') AND (
source_model = 'eav/entity_attribute_source_table' OR backend_model = 'eav/entity_attribute_backend_array'{$this->getForcedMappableCond()}
)
SQL;

        $sql = $this->connectionHelper->getM1connection()
            ->select()
            ->from($this->connectionHelper->getM1TableName('eav_attribute'), ['attribute_id'])
            ->where($cond);

        $this->mappableAttributes = $this->connectionHelper->getM1Connection()->fetchCol($sql);
    }
    /**
     * @return string
     */
    protected function getForcedMappableCond(): string
    {
        if (count($this->getForcedMappables())) {
            $cond = join(',', array_map(static function ($mappable) {
                return "'$mappable'";
            }, $this->getForcedMappables()));
            return " OR attribute_code IN($cond)";
        }
        return '';
    }

    protected function initConfigurableAttributes(): void
    {
        $sql = $this->connectionHelper->getM1connection()
            ->select()
            ->from($this->connectionHelper->getM1TableName('eav_attribute'), ['attribute_id'])
            ->where("frontend_input IN('select', 'multiselect')");

        $this->configurableAttributes = $this->connectionHelper->getM1Connection()->fetchCol($sql);
    }

    /**
     * @param $m1Entity
     * @return bool
     */
    protected function isMappable($m1Entity): bool
    {
        return in_array($m1Entity['attribute_id'] ?? false, $this->mappableAttributes, true);
    }

    /**
     * @param $m1Entity
     * @return bool
     */
    protected function isConfigurable($m1Entity): bool
    {
        return in_array($m1Entity['attribute_id'] ?? false, $this->configurableAttributes, true);
    }

    protected function initOptionsMap(): void
    {
        $connection = $this->connectionHelper->getM2Connection();
        $sql = $connection
            ->select()
            ->from($connection->getTableName('eav_attribute_match'), ['m1_attribute_option_id', 'm2_attribute_option_id']);
        $this->optionsMap = $connection->fetchPairs($sql);
    }

    /**
     * @param $value
     * @return string|null
     */
    protected function mapOption($value): ?string
    {
        $explode = array_filter(explode(',', $value));
        $result = [];

        foreach ($explode as $item) {
            $result[] = $this->optionsMap[$item] ?? '';
        }

        return implode(',', array_filter(array_unique($result)));
    }

    /**
     * @param $value
     * @return string|null
     */
    protected function filterOption($value): ?string
    {
        $result = array_filter(explode(',', $value));


        return implode(',', array_filter(array_unique($result)));
    }

    protected function initAttributeIds(): void
    {
        $m1connection = $this->connectionHelper->getM1connection();
        $m2connection = $this->connectionHelper->getM2Connection();
        $this->m1Attributes = [];
        $this->m2Attributes = [];
        foreach ($this->getEntities() as $entityTypeId) {
            $this->m1Attributes[$entityTypeId] = $m1connection->fetchPairs(
                $m1connection->select()
                    ->from($this->connectionHelper->getM1TableName('eav_attribute'), ['attribute_code', 'attribute_id'])
                    ->where("entity_type_id = $entityTypeId")
            );
            $this->m2Attributes[$entityTypeId] = $m2connection->fetchPairs(
                $m2connection->select()
                    ->from('eav_attribute', ['attribute_code', 'attribute_id'])
                    ->where("entity_type_id = $entityTypeId")
            );
        }

        foreach ($this->m1Attributes as $entityTypeId => $_m1Attributes) {
            foreach ($_m1Attributes as $code => $attributeId) {
                $m2AttributeId = $this->m2Attributes[$entityTypeId][$code] ?? null;
                if ($entityTypeId === 4 && $code === 'category_ids' && $m2AttributeId) {
                    $this->categoryIdsAttrId = $m2AttributeId;
                }
                if ($m2AttributeId !== null) {
                    $this->attributeIds[$attributeId] = $m2AttributeId;
                }
            }
        }
    }

    /**
     * @param int $entityTypeId
     * @return bool
     */
    protected function shouldSync(int $entityTypeId): bool
    {
        if (
            $entityTypeId === $this->getEntityTypeId()
            || count($this->entities) === 0
            || (count($this->entities) && in_array($entityTypeId, $this->entities))
        ) {
            return true;
        }

        return false;
    }

    protected function initTypeMismatchedAttributes(): void
    {
        $m1connection = $this->connectionHelper->getM1connection();
        $m2connection = $this->connectionHelper->getM2Connection();
        $m1AttributeTypes = [];
        $m2AttributeTypes = [];

        $mismatchedTypeAttributes = [];

        foreach ($this->getEntities() as $entityTypeId) {
            $m1AttributeTypes[$entityTypeId] = $m1connection->fetchPairs(
                $m1connection->select()->from($this->connectionHelper->getM1TableName('eav_attribute'), ['attribute_code', 'backend_type'])
                    ->where("entity_type_id = $entityTypeId")
            );
            $m2AttributeTypes[$entityTypeId] = $m2connection->fetchPairs(
                $m2connection->select()->from('eav_attribute', ['attribute_code', 'backend_type'])
                    ->where("entity_type_id = $entityTypeId")
            );
        }
        $idsCond = [];

        foreach ($m1AttributeTypes as $entityTypeId => $m1Attributes) {
            $mismatchedTypeAttributes[$entityTypeId] = [];
            foreach ($m1Attributes as $code => $type) {
                $m2Type = $m2AttributeTypes[$entityTypeId][$code] ?? null;
                if ($m2Type && $type !== $m2Type && $m2Type !== 'static') {
                    $mismatchedTypeAttributes[$entityTypeId][$code] = ['m1_type' => $type, 'm2_type' => $m2Type];
                    $idsCond[] = "(attribute_code = '$code' AND entity_type_id = $entityTypeId)";
                }
            }
        }

        $this->mismatchedAttributesMap = $mismatchedTypeAttributes;
        $mismatchedCond = trim(implode(' OR ', $idsCond));
        if ($mismatchedCond !== '') {
            $this->mismatchedAttributeIds = $m1connection->fetchCol($m1connection->select()->from($this->connectionHelper->getM1TableName('eav_attribute'), ['attribute_id'])
                ->where($mismatchedCond));
        }
    }

    /**
     * @return array|int[]|null[]
     */
    protected function getEntities(): array
    {
        if ($this->getEntityTypeId()) {
            return [$this->getEntityTypeId()];
        } elseif (count($this->entities) === 0) {
            return array_keys(self::ENTITIES);
        } else {
            return $this->entities;
        }
    }

    protected function initEntityIds()
    {
        foreach ($this->getEntities() as $entityTypeId) {
            $entityTable = self::ENTITIES[$entityTypeId] . '_entity';
            $this->entityIds[$entityTypeId] = $this->connectionHelper->getM2Connection()->fetchCol("SELECT entity_id FROM $entityTable");
        }
    }

    /**
     * @param $m1Entity
     * @return array
     */
    protected function filterCategoryIds($m1Entity): array
    {
        $categoryIds = array_filter(explode(',', $m1Entity['value']));
        if (is_array($this->entityIds[3] ?? false)) {
            $categoryEntityIds = $this->entityIds[3];
            $m1Entity['value'] = array_filter(
                $categoryIds,
                static function ($categoryId) use ($categoryEntityIds) {
                    return in_array($categoryId, $categoryEntityIds);
                }
            );
            unset($categoryEntityIds);
        }

        return $m1Entity;
    }

    /**
     * @param $m1Entity
     * @return array
     */
    protected function mapAndValidateAttribute($m1Entity): array
    {
        if (isset($m1Entity['attribute_id'])) {
            $mappedAttrId = $this->attributeIds[$m1Entity['attribute_id']] ?? null;
            if ($mappedAttrId) {
                $m1Entity['attribute_id'] = $mappedAttrId;
                if ($this->categoryIdsAttrId && $this->categoryIdsAttrId == $mappedAttrId) {
                    $this->filterCategoryIds($m1Entity);
                }
            } else {
                $m1Entity = [];
            }
        }
        return $m1Entity;
    }

    /**
     * @param string $table
     * @return string
     */
    protected function getEntityTable(string $table): string
    {
        $array = array_slice(explode('_', $table), 0, 3);
        return implode('_', $array);
    }

    /**
     * @param string $entityTable
     * @return bool
     */
    protected function isEntity(string $entityTable): bool
    {
        return in_array(str_replace('_entity', '', $entityTable), self::ENTITIES);
    }

    /**
     * @return string[]
     */
    public function getForcedMappables(): array
    {
        return static::FORCED_MAPPABLES;
    }
}
