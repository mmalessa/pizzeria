<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Command;

final class AcceptOrder
{
    /**
     * @param list<array{menuItemId: string, quantity: int}> $lines
     */
    public function __construct(
        public readonly string $kitchenOrderId,
        public readonly array $lines,
        public readonly string $correlationId,
    ) {
    }
}
