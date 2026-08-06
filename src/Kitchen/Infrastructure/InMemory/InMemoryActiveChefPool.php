<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Infrastructure\InMemory;

use Pizzeria\Kitchen\Application\Port\ActiveChefPoolInterface;
use Pizzeria\Kitchen\Domain\ValueObject\ChefId;

final class InMemoryActiveChefPool implements ActiveChefPoolInterface
{
    /** @var array<string, true> */
    private array $active = [];

    public function markActive(ChefId $chefId): void
    {
        $this->active[$chefId->toString()] = true;
    }

    public function markInactive(ChefId $chefId): void
    {
        unset($this->active[$chefId->toString()]);
    }

    public function isActive(ChefId $chefId): bool
    {
        return isset($this->active[$chefId->toString()]);
    }

    public function countActive(): int
    {
        return count($this->active);
    }
}
