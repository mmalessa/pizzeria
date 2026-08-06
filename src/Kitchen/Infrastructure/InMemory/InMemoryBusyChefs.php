<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Infrastructure\InMemory;

use Pizzeria\Kitchen\Application\Port\BusyChefsInterface;
use Pizzeria\Kitchen\Domain\ValueObject\ChefId;

final class InMemoryBusyChefs implements BusyChefsInterface
{
    /** @var array<string, true> */
    private array $busy = [];

    public function markBusy(ChefId $chefId): void
    {
        $this->busy[$chefId->toString()] = true;
    }

    public function markFree(ChefId $chefId): void
    {
        unset($this->busy[$chefId->toString()]);
    }

    public function isFree(ChefId $chefId): bool
    {
        return !isset($this->busy[$chefId->toString()]);
    }
}
