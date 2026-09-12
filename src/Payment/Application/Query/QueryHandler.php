<?php

declare(strict_types=1);

namespace App\Payment\Application\Query;

/**
 * Marker for query handlers. Wired to the query bus by the container.
 * Each implementation handles exactly one query via __invoke() and returns a
 * read DTO.
 */
interface QueryHandler
{
}
