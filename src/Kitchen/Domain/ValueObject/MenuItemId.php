<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Domain\ValueObject;

/**
 * Resource Management's menuItemId, referenced only — Kitchen never mints
 * one (doc/08_kitchen_value_objects.md).
 */
final class MenuItemId
{
    private function __construct(private readonly string $value)
    {
        if ($value === '') {
            throw new \InvalidArgumentException('MenuItemId cannot be empty.');
        }
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
