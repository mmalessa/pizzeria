<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Domain\ValueObject;

use Ramsey\Uuid\Uuid;

/**
 * doc/08_kitchen_aggregates.md §2. Kitchen's own identity — generated
 * locally, no external correlation.
 */
final class PizzaTaskId
{
    private function __construct(private readonly string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('PizzaTaskId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
