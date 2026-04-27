<?php declare(strict_types=1);

namespace CaviarTheme\Subscriber;

use Shopware\Core\Content\Product\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StorefrontSubscriber implements EventSubscriberInterface
{
    private EntityRepository $categoryRepository;
    private SalesChannelRepository $salesChannelProductRepository;
    private SystemConfigService $systemConfigService;
    private UrlGeneratorInterface $router;

    public function __construct(
        EntityRepository $categoryRepository,
        SalesChannelRepository $salesChannelProductRepository,
        SystemConfigService $systemConfigService,
        UrlGeneratorInterface $router
    )
    {
        $this->categoryRepository = $categoryRepository;
        $this->salesChannelProductRepository = $salesChannelProductRepository;
        $this->systemConfigService = $systemConfigService;
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $params = $event->getParameters();
        $header = $params['header'] ?? null;
        $route = (string) ($params['controllerAction'] ?? '');

        if ($route === 'frontend.home.page') {
            $criteria = new Criteria();
            $criteria->addFilter(new ProductAvailableFilter(
                $event->getSalesChannelContext()->getSalesChannelId(),
                ProductVisibilityDefinition::VISIBILITY_ALL
            ));
            $criteria->addAssociation('cover.media');
            $criteria->addAssociation('manufacturer');
            $criteria->setLimit($this->getFeaturedLimit());
            $this->applyFeaturedSourceFilters($criteria);

            $featuredProducts = $this->salesChannelProductRepository->search($criteria, $event->getSalesChannelContext())->getEntities();

            // Fallback to latest products when config resolves to zero products
            if ($featuredProducts->count() === 0) {
                $fallbackCriteria = new Criteria();
                $fallbackCriteria->setLimit($this->getFeaturedLimit());
                $fallbackCriteria->addFilter(new ProductAvailableFilter(
                    $event->getSalesChannelContext()->getSalesChannelId(),
                    ProductVisibilityDefinition::VISIBILITY_ALL
                ));
                $fallbackCriteria->addAssociation('cover.media');
                $fallbackCriteria->addAssociation('manufacturer');
                $fallbackCriteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

                $featuredProducts = $this->salesChannelProductRepository->search($fallbackCriteria, $event->getSalesChannelContext())->getEntities();
            }

            $event->setParameter('featuredProducts', $featuredProducts);

            $navigationCategoryId = $event->getSalesChannelContext()->getSalesChannel()->getNavigationCategoryId();
            $categoryCriteria = new Criteria();
            $categoryCriteria->setLimit(6);
            $categoryCriteria->addFilter(new EqualsFilter('parentId', $navigationCategoryId));
            $categoryCriteria->addFilter(new EqualsFilter('active', true));
            $categoryCriteria->addAssociation('media');

            $homeCategories = $this->categoryRepository->search($categoryCriteria, $event->getContext())->getEntities();
            $event->setParameter('homeCategories', $homeCategories);
        }

        if (!$header || !$header->getNavigation() || !$header->getNavigation()->getActive()) {
            return;
        }

        $active = $header->getNavigation()->getActive();
        $customFields = $active->getTranslated()['customFields'] ?? [];

        if (empty($customFields['custom_menu_topbar_cat'])) {
            return;
        }

        $linkedCategoryId = $customFields['custom_menu_topbar_cat'];
        $context = $event->getContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $linkedCategoryId));
        $criteria->addAssociation('media');

        $subCategories = $this->categoryRepository->search($criteria, $context)->getElements();

        // ✅ Add SEO URLs
        foreach ($subCategories as $category) {
            $seoUrl = $this->router->generate('frontend.navigation.page', [
                'navigationId' => $category->getId(),
            ]);

            $category->assign(['seoUrl' => $seoUrl]);
        }


        $event->setParameter('customTopbarCategories', $subCategories);
    }

    private function getFeaturedLimit(): int
    {
        $configuredLimit = (int) ($this->systemConfigService->get('CaviarTheme.config.featuredProductsLimit') ?? 15);

        if ($configuredLimit < 1) {
            return 15;
        }

        return min($configuredLimit, 24);
    }

    private function applyFeaturedSourceFilters(Criteria $criteria): void
    {
        $source = (string) ($this->systemConfigService->get('CaviarTheme.config.featuredProductsSource') ?? 'latest');

        if ($source === 'category') {
            $categoryId = (string) ($this->systemConfigService->get('CaviarTheme.config.featuredCategoryId') ?? '');
            if (Uuid::isValid($categoryId)) {
                $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $categoryId));
            } else {
                $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
            }

            return;
        }

        if ($source === 'manual') {
            $rawIds = (string) ($this->systemConfigService->get('CaviarTheme.config.featuredProductIds') ?? '');
            $ids = array_values(array_filter(array_map(
                static fn (string $id): string => trim($id),
                explode(',', $rawIds)
            )));

            $validIds = array_values(array_filter($ids, static fn (string $id): bool => Uuid::isValid($id)));

            if ($validIds !== []) {
                $orFilters = array_map(
                    static fn (string $id): EqualsFilter => new EqualsFilter('id', $id),
                    $validIds
                );
                $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $orFilters));
                return;
            }
        }

        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
    }
}
