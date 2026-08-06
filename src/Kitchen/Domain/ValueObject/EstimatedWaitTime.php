<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Domain\ValueObject;

/**
 * Computed at AcceptOrder time; exists only transiently, never persisted
 * as a KitchenOrder field (doc/08_kitchen_value_objects.md).
 */
final class EstimatedWaitTime
{
    public function __construct(private readonly int $seconds)
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('EstimatedWaitTime cannot be negative.');
        }
    }

    public function seconds(): int
    {
        return $this->seconds;
    }
}
