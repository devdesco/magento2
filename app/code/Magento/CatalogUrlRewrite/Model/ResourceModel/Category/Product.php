<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Model\ResourceModel\Category;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\UrlRewrite\Model\Storage\DbStorage;

class Product extends AbstractDb
{
    /**
     * Product/Category relation table name
     */
    const TABLE_NAME = 'catalog_url_rewrite_product_category';

    /**
     * Chunk for mass insert
     */
    const CHUNK_SIZE = 100;

    /**
     * Primary key auto increment flag
     *
     * @var bool
     */
    protected $_isPkAutoIncrement = false;

    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, 'url_rewrite_id');
    }

    /**
     * @param array $insertData
     * @return int
     */
    public function saveMultiple(array $insertData)
    {
        $connection = $this->getConnection();
        if (sizeof($insertData) <= self::CHUNK_SIZE) {
            return $connection->insertMultiple($this->getTable(self::TABLE_NAME), $insertData);
        }
        $data = array_chunk($insertData, self::CHUNK_SIZE);
        $totalCount = 0;
        foreach ($data as $insertData) {
            $totalCount += $connection->insertMultiple($this->getTable(self::TABLE_NAME), $insertData);
        }
        return $totalCount;
    }

    /**
     * Removes data by primary key
     *
     * @param array $removeData
     * @return int
     */
    public function removeMultiple(array $removeData)
    {
        return $this->getConnection()->delete(
            $this->getTable(self::TABLE_NAME),
            ['url_rewrite_id in (?)' => $removeData]
        );
    }

    /**
     * Removes data by entities from url_rewrite table using a select
     *
     * @param array $filter
     * @return int
     */
    public function removeMultipleByFilter(array $filter)
    {
        return $this->getConnection()->delete(
            $this->getTable(self::TABLE_NAME),
            ['url_rewrite_id in (?)' => $this->prepareSelect($filter)]
        );
    }

    /**
     * Prepare select statement for specific filter
     *
     * @param array $data
     * @return \Magento\Framework\DB\Select
     */
    private function prepareSelect($data)
    {
        $select = $this->getConnection()->select();
        $select->from($this->getTable(DbStorage::TABLE_NAME), 'url_rewrite_id');

        foreach ($data as $column => $value) {
            $select->where($this->getConnection()->quoteIdentifier($column) . ' IN (?)', $value);
        }
        return $select;
    }
}
