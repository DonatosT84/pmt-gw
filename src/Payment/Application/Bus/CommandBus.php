<?php

declare(strict_types=1);

namespace App\Payment\Application\Bus;

/**
 * Application-owned command bus contract. Commands mutate state and may return
 * a minimal use-case result (e.g. a payment id plus checkout instructions).
 */
interface CommandBus
{
    public function dispatch(object $command): mixed;
}
