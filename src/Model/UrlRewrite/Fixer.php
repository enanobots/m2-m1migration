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

namespace Nanobots\MigrationTool\Model\UrlRewrite;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Fixer
 * @package Qsolutions\UrlRewrite\Model\UrlRewrite
 */
class Fixer
{
    const CATEGORY_ENTITY_TYPE_ID = 3;
    const PRODUCT_ENTITY_TYPE_ID = 4;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ResourceConnection
     */
    private $resource;
    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;

    /**
     * Fixer constructor.
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->resource = $resourceConnection;
        $this->connection = $resourceConnection->getConnection();
    }

    /**
     * @param string $storeCode
     * @throws \Exception
     */
    public function fixUrlsForStore($storeCode = 'default')
    {
        $store = $this->storeManager->getStore($storeCode);

        if ($store instanceof Store && $store->getId()) { /** We ignore admin store, so ID = '0' doesn't pass */
            $visibleProducts = $this->_getVisibleProducts();

            $categoryUrlKeys = $this->_getCategoryUrlKeys($store);

            $productUrlKeys = $this->_getProductUrlKeys($store);

            $categoryProducts = $this->_getCategoryProducts();

            $categoryPaths = $this->_getCategoryPaths($categoryUrlKeys);

            $productPaths = $this->_getProductPaths($visibleProducts, $productUrlKeys, $categoryProducts, $categoryPaths);

            $this->getConnection()->beginTransaction();

            try {
                $tableName = $this->_getUrlRewriteTableName();
                $this->getConnection()->delete(
                    $tableName,
                    sprintf('store_id = %s AND (entity_type = "category" or entity_type = "product")', $store->getId())
                );
                $categoryPathsInsert = [];
                foreach ($categoryPaths as $categoryId => $categoryPath) {
                    if (empty(array_filter($categoryPath))) {
                        continue;
                    }
                    $categoryPathsInsert[] = $this->_prepareCategoryInsert($categoryId, $categoryPath, $store);
                }

                $productPathsInsert = [];
                foreach ($productPaths as $targetPath => $productPath) {
                    $productId = $this->_getProductIdFromTargetPath($targetPath);
                    if ($productPath) {
                        $productPathsInsert[] = $this->_prepareProductInsert($productId, $productPath, $targetPath, $store);
                    }
                }

                foreach ($categoryPathsInsert as $categoryPath) {
                    $this->getConnection()->insertOnDuplicate($tableName, $categoryPath);
                }

                foreach ($productPathsInsert as $productPath) {
                    $this->getConnection()->insertOnDuplicate($tableName, $productPath);
                }

                $this->getConnection()->commit();
            } catch (\Exception $e) {
                $this->getConnection()->rollBack();
                throw $e;
            }
        }
    }

    /**
     * @param Product $product
     * @param string $storeCode
     */
    public function fixUrlForProduct(Product $product, $storeCode = 'default')
    {
        $store = $this->storeManager->getStore($storeCode);

        $categoryIds = array_filter($product->getCategoryIds(), function ($categoryId) {
            return $categoryId > 2;
        });

        $categoryUrlKeys = $this->_getCategoryUrlKeys($store);

        $categoryPaths = $this->_getCategoryPaths($categoryUrlKeys);

        $productPaths = [];

        $productPaths[sprintf('catalog/product/view/id/%s', $product->getId())] = [$product->getUrlKey()];

        foreach ($categoryIds as $categoryId) {
            $productPaths[sprintf('catalog/product/view/id/%s/category/%s', $product->getId(), $categoryId)] = [
                join('/', $categoryPaths[$categoryId]), $product->getUrlKey()
            ];
        }

        $productPathsInsert = [];
        foreach ($productPaths as $targetPath => $productPath) {
            $productPathsInsert[] = $this->_prepareProductInsert($product->getId(), $productPath, $targetPath, $store);
        }

        try {
            $this->getConnection()->beginTransaction();

            $tableName = $this->_getUrlRewriteTableName();
            $this->getConnection()->delete($tableName, '`entity_id` = ' . $product->getId());

            foreach ($productPathsInsert as $productPath) {
                $this->getConnection()->insertOnDuplicate($tableName, $productPath);
            }

            $this->getConnection()->commit();
        } catch (\Exception $e) {
            $this->getConnection()->rollBack();
        }
    }

    /**
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return ResourceConnection
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return string
     */
    protected function _getUrlRewriteTableName()
    {
        return $this->getResource()->getTableName('url_rewrite');
    }

    /**
     * @return int
     */
    protected function _getCategoryUrlKeyId()
    {
        $sql = $this->getConnection()->select()->from($this->resource->getTableName('eav_attribute'))
            ->where('entity_type_id = ' . self::CATEGORY_ENTITY_TYPE_ID . ' AND attribute_code = \'url_key\'');
        $sql->reset(Select::COLUMNS);
        $sql->columns('attribute_id');
        $categoryUrlKeyId = $this->getConnection()->fetchOne($sql);
        return $categoryUrlKeyId;
    }

    /**
     * @return int
     */
    protected function _getProductUrlKey()
    {
        $sql = $this->getConnection()->select()->from($this->resource->getTableName('eav_attribute'))
            ->where('entity_type_id = ' . self::PRODUCT_ENTITY_TYPE_ID . ' AND attribute_code = \'url_key\'');
        $sql->reset(Select::COLUMNS);
        $sql->columns('attribute_id');
        $productUrlKeyId = $this->getConnection()->fetchOne($sql);
        return $productUrlKeyId;
    }

    /**
     * @return int
     */
    protected function _getProductVisibilityId()
    {
        $sql = $this->getConnection()->select()->from($this->resource->getTableName('eav_attribute'))
            ->where('entity_type_id = ' . self::PRODUCT_ENTITY_TYPE_ID . ' AND attribute_code = \'visibility\'');
        $sql->reset(Select::COLUMNS);
        $sql->columns('attribute_id');
        $productVisibilityId = $this->getConnection()->fetchOne($sql);
        return $productVisibilityId;
    }

    /**
     * @return array
     */
    protected function _getVisibleProducts()
    {
        $productVisibilityId = $this->_getProductVisibilityId();
        $sql = $this->getConnection()->select()->from($this->resource->getTableName('catalog_product_entity_int'))
            ->where('attribute_id = ' . $productVisibilityId . ' and value = ' . Visibility::VISIBILITY_BOTH);
        $sql->reset(Select::COLUMNS);
        $sql->columns('entity_id');
        $visibleProducts = $this->getConnection()->fetchCol($sql);
        return $visibleProducts;
    }

    /**
     * @param Store $store
     * @return array
     */
    protected function _getCategoryUrlKeys(Store $store)
    {
        $categoryUrlKeyId = $this->_getCategoryUrlKeyId();

        $sql = $this->getConnection()->select()->from('catalog_category_entity_varchar')
            ->where('attribute_id = ' . $categoryUrlKeyId . ' AND store_id = ' . $store->getId());
        $sql->reset(Select::COLUMNS);
        $sql->columns(['entity_id', 'value']);
        $categoryUrlKeys = $this->getConnection()->fetchPairs($sql);

        $sql = $this->getConnection()->select()->from('catalog_category_entity_varchar')
            ->where('attribute_id = ' . $categoryUrlKeyId . ' AND store_id = 0');
        $sql->reset(Select::COLUMNS);
        $sql->columns(['entity_id', 'value']);
        $categoryUrlKeysDefault = $this->getConnection()->fetchPairs($sql);

        $categoryUrlKeys = array_replace($categoryUrlKeysDefault, $categoryUrlKeys);
        return $categoryUrlKeys;
    }

    /**
     * @param Store $store
     * @return array
     */
    protected function _getProductUrlKeys(Store $store)
    {
        $productUrlKeyId = $this->_getProductUrlKey();

        $sql = $this->getConnection()->select()->from('catalog_product_entity_varchar')
            ->where('attribute_id = ' . $productUrlKeyId . ' AND store_id = ' . $store->getId());
        $sql->reset(Select::COLUMNS);
        $sql->columns(['entity_id', 'value']);
        $productUrlKeys = $this->getConnection()->fetchPairs($sql);

        $sql = $this->getConnection()->select()->from('catalog_product_entity_varchar')
            ->where('attribute_id = ' . $productUrlKeyId . ' AND store_id = 0');
        $sql->reset(Select::COLUMNS);
        $sql->columns(['entity_id', 'value']);
        $productUrlKeysDefault = $this->getConnection()->fetchPairs($sql);

        $productUrlKeys = array_replace($productUrlKeysDefault, $productUrlKeys);
        return $productUrlKeys;
    }

    /**
     * @return array
     */
    protected function _getCategoryProducts()
    {
        $sql = $this->getConnection()->select()->from('catalog_category_product');
        $sql->reset(Select::COLUMNS);
        $sql->columns(['category_id', 'product_id']);
        return $this->getConnection()->fetchAll($sql);
    }

    /**
     * @param $categoryUrlKeys
     * @return array
     */
    protected function _getCategoryPaths($categoryUrlKeys)
    {
        $sql = $this->getConnection()->select()->from('catalog_category_entity')
            ->where('level > 1');
        $sql->reset(Select::COLUMNS);
        $sql->columns(['entity_id', 'path']);
        $categoryPaths = array_map(
            function ($path) use ($categoryUrlKeys) {
                $categoryPath = array_filter(explode('/', $path));
                foreach ($categoryPath as $index => $item) {
                    if ($item <= 2) {
                        unset($categoryPath[$index]);
                        continue;
                    }
                    if (isset($categoryUrlKeys[$item])) {
                        $categoryPath[$index] = $categoryUrlKeys[$item];
                    } else {
                        $categoryPath = [];
                        break;
                    }
                }

                return $categoryPath;
            },
            $this->getConnection()->fetchPairs($sql)
        );
        return $categoryPaths;
    }

    /**
     * @param $visibleProducts
     * @param $productUrlKeys
     * @param $categoryProducts
     * @param $categoryPaths
     * @return array
     */
    protected function _getProductPaths($visibleProducts, $productUrlKeys, $categoryProducts, $categoryPaths)
    {
        $productPaths = [];

        foreach ($visibleProducts as $productId) {
            if (isset($productUrlKeys[$productId])) {
                $productPaths[sprintf('catalog/product/view/id/%s', $productId)] = $productUrlKeys[$productId];
            }
        }

        foreach ($categoryProducts as $categoryProduct) {
            $categoryId = $categoryProduct['category_id'];
            $productId = $categoryProduct['product_id'];
            if ($categoryId <= 2
                || !in_array($productId, $visibleProducts)
                || isset($productPaths[sprintf('catalog/product/view/id/%s/category/%s', $productId, $categoryId)])) {
                continue;
            }
            $productUrlKey = null;
            if (isset($productUrlKeys[$productId])) {
                $productPaths[sprintf('catalog/product/view/id/%s/category/%s', $productId, $categoryId)] = $productUrlKey;
            }
        }
        return $productPaths;
    }

    /**
     * @param int $categoryId
     * @param array $categoryPath
     * @param Store $store
     * @return array
     */
    protected function _prepareCategoryInsert(int $categoryId, array $categoryPath, Store $store)
    {
        return [
            'entity_type' => 'category',
            'entity_id' => $categoryId,
            'request_path' => end($categoryPath),
            'target_path' => sprintf('catalog/category/view/id/%s', $categoryId),
            'redirect_type' => 0,
            'store_id' => $store->getId(),
            'description' => null,
            'is_autogenerated' => 1,
            'metadata' => null,
        ];
    }

    /**
     * @param $targetPath
     * @return mixed
     */
    protected function _getProductIdFromTargetPath($targetPath)
    {
        return array_filter(explode('/', $targetPath))[4];
    }

    /**
     * @param int $productId
     * @param string $productPath
     * @param string $targetPath
     * @param Store $store
     * @return array
     */
    protected function _prepareProductInsert(int $productId, string $productPath, string $targetPath, Store $store)
    {
        $targetPathData = array_filter(explode('/', $targetPath));
        $categoryId = (isset($targetPathData[count($targetPathData) - 2])
            && $targetPathData[count($targetPathData) - 2] == 'category')
            ? array_pop($targetPathData) : null;

        return [
            'entity_type' => 'product',
            'entity_id' => $productId,
            'request_path' => sprintf(
                '%s%s',
                $productPath,
                $this->getProductUrlSuffix($store)
            ),
            'target_path' => $targetPath,
            'redirect_type' => 0,
            'store_id' => $store->getId(),
            'description' => null,
            'is_autogenerated' => 1,
            'metadata' => is_null($categoryId) ? null : json_encode(['category_id' => $categoryId])
        ];
    }

    /**
     * @param Store $store
     * @return string
     */
    protected function getProductUrlSuffix(Store $store): string
    {
        return $store->getConfig(ProductUrlPathGenerator::XML_PATH_PRODUCT_URL_SUFFIX);
    }
}
