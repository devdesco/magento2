<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Model\Map;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * Map that holds data for products ids from a category and subcategories
 */
class DataProductMap implements DataMapInterface
{
    /** @var array */
    private $data = [];

    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var DataMapPoolInterface */
    private $dataMapPool;

    /** @var ResourceConnection */
    private $connection;

    /**
     * Constructor
     * @param CollectionFactory $collectionFactory
     * @param DataMapPoolInterface $dataMapPool
     * @param ResourceConnection $connection
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        DataMapPoolInterface $dataMapPool,
        ResourceConnection $connection
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->dataMapPool = $dataMapPool;
        $this->connection = $connection;
    }

    /**
     * Gets all data from a map identified by a category Id
     *
     * @param int $categoryId
     * @return array
     */
    public function getData($categoryId)
    {
        if (empty($this->data[$categoryId])) {
            $this->data[$categoryId] = $this->queryData($categoryId);
        }
        return $this->data[$categoryId];
    }

    /**
     * Queries the database and returns results
     *
     * @param int $categoryId
     * @return array
     */
    private function queryData($categoryId)
    {
        $productsCollection = $this->collectionFactory->create();
        $productsCollection->getSelect()
            ->joinInner(
                ['cp' => $this->connection->getTableName('catalog_category_product')],
                'cp.product_id = e.entity_id',
                []
            )
            ->where(
                $productsCollection->getConnection()->prepareSqlCondition(
                    'cp.category_id',
                    ['in' => $this->dataMapPool->getDataMap(DataCategoryMap::class, $categoryId)->getData($categoryId)]
                )
            )
            ->group('e.entity_id');

        return $productsCollection->getAllIds();
    }

    /**
     * Resets current map and it's dependencies
     *
     * @param int $categoryId
     * @return $this
     */
    public function resetData($categoryId)
    {
        $this->dataMapPool->resetDataMap(DataCategoryMap::class, $categoryId);
        unset($this->data);
        $this->data = [];
        return $this;
    }
}
