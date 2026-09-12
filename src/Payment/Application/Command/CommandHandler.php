<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

/**
 * Marker for command handlers. Used by the container to wire each handler to
 * the command bus without a framework attribute leaking into this layer.
 * Each implementation handles exactly one command via __invoke().
 */
interface CommandHandler
{
}
