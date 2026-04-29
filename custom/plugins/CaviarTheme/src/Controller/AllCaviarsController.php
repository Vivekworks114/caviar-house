<?php declare(strict_types=1);

namespace CaviarTheme\Controller;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class AllCaviarsController extends StorefrontController
{
    public function __construct(
        private readonly GenericPageLoader $genericPageLoader,
        private readonly SalesChannelRepository $salesChannelProductRepository
    ) {
    }

    #[Route(
        path: '/all-caviars',
        name: 'frontend.caviar.all_caviars',
        methods: ['GET']
    )]
    #[Route(
        path: '/all-caviar',
        name: 'frontend.caviar.all_caviar',
        methods: ['GET']
    )]
    public function index(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $page = $this->genericPageLoader->load($request, $salesChannelContext);

        $criteria = new Criteria();
        $criteria->setLimit(12);
        $criteria->addFilter(new ProductAvailableFilter(
            $salesChannelContext->getSalesChannelId(),
            ProductVisibilityDefinition::VISIBILITY_ALL
        ));
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('manufacturer');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $caviarProducts = $this->salesChannelProductRepository
            ->search($criteria, $salesChannelContext)
            ->getEntities();

        return $this->renderStorefront('@CaviarTheme/storefront/page/all-caviars/index.html.twig', [
            'page' => $page,
            'caviarProducts' => $caviarProducts,
        ]);
    }
}

