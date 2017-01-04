<?php
/**
 * Copyright © 2017 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Test\Unit\Model\Map;

use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Framework\DB\Select;
use Magento\Catalog\Model\ProductRepository;
use Magento\CatalogUrlRewrite\Model\Map\DataMapPoolInterface;
use Magento\CatalogUrlRewrite\Model\Map\DataProductMap;
use Magento\CatalogUrlRewrite\Model\Map\DataCategoryMap;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;

/**
 * Class DataProductMapTest
 */
class DataProductMapTest extends \PHPUnit_Framework_TestCase
{
    /** @var DataMapPoolInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $dataMapPoolMock;

    /** @var DataCategoryMap|\PHPUnit_Framework_MockObject_MockObject */
    private $dataCategoryMapMock;

    /**
     * @var CollectionFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $collectionFactoryMock;

    /**
     * @var ProductCollection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $productCollectionMock;

    /** @var DataProductMap|\PHPUnit_Framework_MockObject_MockObject */
    private $model;

    protected function setUp()
    {
        $this->dataMapPoolMock = $this->getMock(DataMapPoolInterface::class);
        $this->dataCategoryMapMock = $this->getMock(DataCategoryMap::class, [], [], '', false);
        $this->collectionFactoryMock = $this->getMock(CollectionFactory::class, ['create'], [], '', false);
        $this->productCollectionMock = $this->getMock(
            ProductCollection::class,
            ['getSelect', 'getConnection', 'getAllIds'],
            [],
            '',
            false
        );

        $this->collectionFactoryMock->expects($this->any())
            ->method('create')
            ->willReturn($this->productCollectionMock);

        $this->dataMapPoolMock->expects($this->any())
            ->method('getDataMap')
            ->willReturn($this->dataCategoryMapMock);

        $this->model = (new ObjectManager($this))->getObject(
            DataProductMap::class,
            [
                'collectionFactory' => $this->collectionFactoryMock,
                'dataMapPool' => $this->dataMapPoolMock,
                'mapData' => [],
            ]
        );
    }

    /**
     * Tests getAllData, getData and resetData functionality
     */
    public function testGetAllData()
    {
        $productIds = ['1' => [1, 2, 3], '2' => [2, 3], '3' => 3];
        $productIdsOther = ['2' => [2, 3, 4]];

        $connectionMock = $this->getMock(AdapterInterface::class);
        $selectMock = $this->getMock(Select::class, [], [], '', false);

        $this->productCollectionMock->expects($this->exactly(3))
            ->method('getAllIds')
            ->willReturnOnConsecutiveCalls($productIds, $productIdsOther, $productIds);
        $this->productCollectionMock->expects($this->any())
            ->method('getConnection')
            ->willReturn($connectionMock);
        $connectionMock->expects($this->any())
            ->method('getTableName')
            ->willReturn($this->returnValue($this->returnArgument(0)));
        $this->productCollectionMock->expects($this->any())
            ->method('getSelect')
            ->willReturn($selectMock);
        $selectMock->expects($this->any())
            ->method('from')
            ->willReturnSelf();
        $selectMock->expects($this->any())
            ->method('joinInner')
            ->willReturnSelf();
        $selectMock->expects($this->any())
            ->method('where')
            ->willReturnSelf();
        $this->dataCategoryMapMock->expects($this->any())
            ->method('getAllData')
            ->willReturn([]);
        $this->dataMapPoolMock->expects($this->any())
            ->method('resetDataMap')
            ->with(DataCategoryMap::class, 1);
        $this->assertEquals($productIds, $this->model->getAllData(1));
        $this->assertEquals($productIds[2], $this->model->getData(1, 2));
        $this->assertEquals($productIdsOther, $this->model->getAllData(2));
        $this->assertEquals($productIdsOther[2], $this->model->getData(2, 2));
        $this->model->resetData(1);
        $this->assertEquals($productIds[2], $this->model->getData(1, 2));
        $this->assertEquals($productIds, $this->model->getAllData(1));
    }
}
