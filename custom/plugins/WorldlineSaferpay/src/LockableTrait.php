<?php declare(strict_types = 1);

namespace Worldline\Saferpay;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;

trait LockableTrait
{
    private function acquireLock(string $name): ?LockInterface
    {
        /** @noinspection PhpInternalEntityUsedInspection */
        if (SemaphoreStore::isSupported()) {
            $store = new SemaphoreStore();
        } else {
            $store = new FlockStore();
        }

        $lock = (new LockFactory($store))->createLock('Worldline_Saferpay_' . $name);
        if (!$lock->acquire()) {
            return null;
        }

        return $lock;
    }
}
