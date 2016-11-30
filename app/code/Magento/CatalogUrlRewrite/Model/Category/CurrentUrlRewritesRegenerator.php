<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Model\Category;

use Magento\Catalog\Model\Category;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\UrlRewrite\Model\OptionProvider;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewrite\Service\V1\Data\UrlRewriteFactory;
use Magento\CatalogUrlRewrite\Model\Map\UrlRewriteMap;
use Magento\CatalogUrlRewrite\Model\Map\MapPoolInterface;
use Magento\Framework\App\ObjectManager;
use Magento\UrlRewrite\Model\ArrayMerger;

class CurrentUrlRewritesRegenerator
{
    /** @var \Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator */
    protected $categoryUrlPathGenerator;

    /** @var \Magento\UrlRewrite\Service\V1\Data\UrlRewriteFactory */
    protected $urlRewriteFactory;

    /** @var UrlFinderInterface */
    protected $urlFinder;

    /** @var MapPoolInterface */
    private $mapPool;

    /** @var \Magento\UrlRewrite\Model\ArrayMerger */
    private $arrayMerger;

    /**
     * @param \Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator $categoryUrlPathGenerator
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewriteFactory $urlRewriteFactory
     * @param UrlFinderInterface $urlFinder
     * @param MapPoolInterface|null $mapPool
     * @param \Magento\UrlRewrite\Model\ArrayMerger|null $arrayMerger
     */
    public function __construct(
        CategoryUrlPathGenerator $categoryUrlPathGenerator,
        UrlRewriteFactory $urlRewriteFactory,
        UrlFinderInterface $urlFinder,
        MapPoolInterface $mapPool = null,
        ArrayMerger $arrayMerger = null
    ) {
        $this->categoryUrlPathGenerator = $categoryUrlPathGenerator;
        $this->urlRewriteFactory = $urlRewriteFactory;
        $this->urlFinder = $urlFinder;
        $this->mapPool = $mapPool ?: ObjectManager::getInstance()->get(MapPoolInterface::class);
        $this->arrayMerger = $arrayMerger ?: ObjectManager::getInstance()->get(ArrayMerger::class);
    }

    /**
     * Generate list based on current url rewrites
     *
     * @param int $storeId
     * @param \Magento\Catalog\Model\Category $category
     * @param int|null $rootCategoryId
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    public function generate($storeId, Category $category, $rootCategoryId = null)
    {
        if ($rootCategoryId) {
            $categoryUrlRewriteMap = $this->mapPool->getMap(UrlRewriteMap::class, $rootCategoryId);

            /** @var UrlRewrite[] $currentUrlRewrites */
            $currentUrlRewrites = $categoryUrlRewriteMap->getByIdentifiers(
                [
                    UrlRewrite::STORE_ID => $storeId,
                    UrlRewrite::ENTITY_ID => $category->getEntityId(),
                    UrlRewrite::ENTITY_TYPE => UrlRewriteMap::ENTITY_TYPE_CATEGORY,
                ]
            );
        } else {
            $currentUrlRewrites = $this->urlFinder->findAllByData(
                [
                    UrlRewrite::STORE_ID => $storeId,
                    UrlRewrite::ENTITY_ID => $category->getEntityId(),
                    UrlRewrite::ENTITY_TYPE => UrlRewriteMap::ENTITY_TYPE_CATEGORY,
                ]
            );
        }

        foreach ($currentUrlRewrites as $rewrite) {
            $this->arrayMerger->addData(
                $rewrite->getIsAutogenerated()
                ? $this->generateForAutogenerated($rewrite, $storeId, $category)
                : $this->generateForCustom($rewrite, $storeId, $category)
            );
        }
        return $this->arrayMerger->getResetData();
    }

    /**
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite $url
     * @param int $storeId
     * @param Category $category
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    protected function generateForAutogenerated($url, $storeId, Category $category)
    {
        if ($category->getData('save_rewrites_history')) {
            $targetPath = $this->categoryUrlPathGenerator->getUrlPathWithSuffix($category, $storeId);
            if ($url->getRequestPath() !== $targetPath) {
                $generatedUrl = $this->urlRewriteFactory->create()
                    ->setEntityType(CategoryUrlRewriteGenerator::ENTITY_TYPE)
                    ->setEntityId($category->getEntityId())
                    ->setRequestPath($url->getRequestPath())
                    ->setTargetPath($targetPath)
                    ->setRedirectType(OptionProvider::PERMANENT)
                    ->setStoreId($storeId)
                    ->setIsAutogenerated(0);
                $this->arrayMerger->addData([$generatedUrl]);
                return $this->arrayMerger->getResetData();
            }
        }
        return [];
    }

    /**
     * @param \Magento\UrlRewrite\Service\V1\Data\UrlRewrite $url
     * @param int $storeId
     * @param Category $category
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    protected function generateForCustom($url, $storeId, Category $category)
    {
        $targetPath = !$url->getRedirectType()
            ? $url->getTargetPath()
            : $this->categoryUrlPathGenerator->getUrlPathWithSuffix($category, $storeId);
        if ($url->getRequestPath() !== $targetPath) {
            $generatedUrl = $this->urlRewriteFactory->create()
                ->setEntityType(CategoryUrlRewriteGenerator::ENTITY_TYPE)
                ->setEntityId($category->getEntityId())
                ->setRequestPath($url->getRequestPath())
                ->setTargetPath($targetPath)
                ->setRedirectType($url->getRedirectType())
                ->setStoreId($storeId)
                ->setDescription($url->getDescription())
                ->setIsAutogenerated(0)
                ->setMetadata($url->getMetadata());
            $this->arrayMerger->addData([$generatedUrl]);
            return $this->arrayMerger->getResetData();
        }
        return [];
    }
}
