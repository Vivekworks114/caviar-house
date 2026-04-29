<?php declare(strict_types=1);

namespace CaviarTheme\Controller;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class AllSalmonsController extends StorefrontController
{
    public function __construct(
        private readonly GenericPageLoader $genericPageLoader,
        private readonly SalesChannelRepository $salesChannelProductRepository
    ) {
    }

    #[Route(
        path: '/all-salmons',
        name: 'frontend.caviar.all_salmons',
        methods: ['GET']
    )]
    public function index(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $page = $this->genericPageLoader->load($request, $salesChannelContext);

        $criteria = new Criteria();
        $criteria->setLimit(48);
        $criteria->addFilter(new ProductAvailableFilter(
            $salesChannelContext->getSalesChannelId(),
            ProductVisibilityDefinition::VISIBILITY_ALL
        ));
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('manufacturer');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $products = $this->salesChannelProductRepository
            ->search($criteria, $salesChannelContext)
            ->getEntities();

        $salmonProducts = $products->filter(static function ($product): bool {
            $name = mb_strtolower((string) ($product->getTranslation('name') ?? $product->getName() ?? ''));

            return str_contains($name, 'saumon')
                || str_contains($name, 'salmon')
                || str_contains($name, 'balik');
        });

        if ($salmonProducts->count() === 0) {
            $salmonProducts = $products;
        }

        return $this->renderStorefront('@CaviarTheme/storefront/page/all-caviars/index.html.twig', [
            'page' => $page,
            'caviarProducts' => $salmonProducts,
            'isSalmonPage' => true,
        ]);
    }
}

