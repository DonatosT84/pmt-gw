<?php

declare(strict_types=1);

namespace App\Payment\Application\Bus;

/**
 * Application-owned query bus contract. Queries never mutate state and return
 * read DTOs (the *View classes).
 */
interface QueryBus
{
    public function ask(object $query): mixed;
}
