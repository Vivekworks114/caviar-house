<?php declare(strict_types=1);

namespace Jkweb\Shopware\Plugin\CategoryListing\DataResolver;

use Jkweb\Shopware\Plugin\CategoryListing\Struct\CategoryListingStruct;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CategoryListingDataResolver extends AbstractCmsElementResolver
{
    public function __construct(private readonly NavigationLoader $navigationLoader)
    {
    }

    public function getType(): string
    {
        return 'category-listing';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $categoriesConfig = $config->get('categories');

        if (!$categoriesConfig || $categoriesConfig->isStatic()) {
            return null;
        }

        $categoryIds = $categoriesConfig->getValue();

        if (!$categoryIds) {
            return null;
        }

        $criteria = new Criteria($categoryIds);
        $criteria->addAssociation('media');

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('categories_' . $slot->getUniqueIdentifier(), CategoryDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $categoryListing = new CategoryListingStruct();
        $slot->setData($categoryListing);

        $config = $slot->getFieldConfig();

        $categories = $config->get('categories');
        if ($categories->isMapped()) {
            $categoryCollection = new CategoryCollection();
            $categoryListing->setCategories($categoryCollection);

            $categoryIds = $categories->getValue();
            if (!$categoryIds) {
                $this->addChildCategories($resolverContext, $categoryCollection);
            } else {
                foreach ($categoryIds as $categoryId) {
                    $this->addCategory($slot, $categoryCollection, $result, $categoryId);
                }
            }
        }

        if ($rowClass = $config->get('rowElementClassName')) {
            $categoryListing->setRowElementClassName($rowClass->getValue());
        }

        if ($colClass = $config->get('colElementClassName')) {
            $categoryListing->setColElementClassName($colClass->getValue());
        }

        if ($headingPosition = $config->get('headingPosition')) {
            $categoryListing->setHeadingPosition($headingPosition->getValue());
        }
    }

    private function addCategory(CmsSlotEntity $slot, CategoryCollection $categoryCollection, ElementDataCollection $result, string $configId): void
    {
        $searchResult = $result->get('categories_' . $slot->getUniqueIdentifier());
        if (!$searchResult) {
            return;
        }

        $category = $searchResult->get($configId);
        if ($category instanceof CategoryEntity) {
            $categoryCollection->add($category);
        }
    }

    private function addChildCategories(ResolverContext $resolverContext, CategoryCollection $categoryCollection): void
    {
        if ($resolverContext instanceof EntityResolverContext) {
            $category = $resolverContext->getEntity();
            if ($category instanceof CategoryEntity) {
                $tree = $this->navigationLoader->load($category->getId(), $resolverContext->getSalesChannelContext(), $category->getId(), 1);
                foreach ($tree->getTree() as $child) {
                    $categoryCollection->add($child->getCategory());
                }
            }
        }
    }
}
