<?php

declare(strict_types=1);

namespace KlarnaPayment\Components\Controller\Storefront;

use KlarnaPayment\Components\Callback\AuthorizationCallback;
use KlarnaPayment\Components\Callback\NotificationCallback;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 * @Route(defaults={"_routeScope": {"storefront"}})
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class KlarnaPaymentsCallbackController extends StorefrontController
{
    /** @var NotificationCallback */
    private $notificationCallback;

    /** @var AuthorizationCallback */
    private $authorizationCallback;

    /** @var SalesChannelContextPersister */
    private $contextPersister;

    /** @var SalesChannelContextFactory */
    private $contextFactory;

    /**
     * Because of backwards compatibility to Shopware 6.3, we can't use the typehint in the argument directly
     *
     * @param SalesChannelContextFactory $contextFactory
     */
    public function __construct(NotificationCallback $notificationCallback, AuthorizationCallback $authorizationCallback, SalesChannelContextPersister $contextPersister, $contextFactory)
    {
        $this->notificationCallback  = $notificationCallback;
        $this->authorizationCallback = $authorizationCallback;
        $this->contextPersister      = $contextPersister;
        $this->contextFactory        = $contextFactory;
    }

    /**
     * @Route("/klarna/callback/notification/{transaction_id}", defaults={"csrf_protected": false, "XmlHttpRequest": true}, name="widgets.klarna.callback.notification", methods={"POST"})
     */
    #[Route(path: '/klarna/callback/notification/{transaction_id}', name: 'widgets.klarna.callback.notification', methods: ['POST'], defaults: ['csrf_protected' => false, 'XmlHttpRequest' => true])]
    public function notificationCallback(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $this->notificationCallback->handle($request->get('transaction_id'), (string) $request->get('event_type'), $salesChannelContext);

        return new Response();
    }

    /**
     * @Route("/klarna/callback/authorization/{cart_token}", defaults={"csrf_protected": false, "XmlHttpRequest": true}, name="widgets.klarna.callback.authorization", methods={"POST"})
     */
    #[Route(path: '/klarna/callback/authorization/{cart_token}', name: 'widgets.klarna.callback.authorization', methods: ['POST'], defaults: ['csrf_protected' => false, 'XmlHttpRequest' => true])]
    public function authorizationCallback(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $token = $request->get('cart_token');

        $customerPayload = $this->contextPersister->load($token, $salesChannelContext->getSalesChannel()->getId());

        $customerContext = $this->contextFactory->create($token, $salesChannelContext->getSalesChannel()->getId(), $customerPayload);

        $this->authorizationCallback->handle($request->get('authorization_token'), $customerContext);

        return new Response();
    }
}
