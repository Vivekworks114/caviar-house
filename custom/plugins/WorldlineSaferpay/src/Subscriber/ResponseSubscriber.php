<?php declare(strict_types=1);

namespace Worldline\Saferpay\Subscriber;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @noinspection PhpUnused
 */
class ResponseSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -1499],
        ];
    }

    /**
     * @noinspection PhpUnused
     */
    public function onResponse(ResponseEvent $event): void
    {
        $event->getResponse()->headers->set(PlatformRequest::HEADER_FRAME_OPTIONS, 'sameorigin');
    }
}
