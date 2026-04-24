<?php declare(strict_types=1);

namespace CaviarTheme\Subscriber;

use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StorefrontSubscriber implements EventSubscriberInterface
{
    private EntityRepository $categoryRepository;
    private UrlGeneratorInterface $router;

    public function __construct(EntityRepository $categoryRepository, UrlGeneratorInterface $router)
    {
        $this->categoryRepository = $categoryRepository;
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
}
