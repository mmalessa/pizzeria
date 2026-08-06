<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Port;

use Pizzeria\Kitchen\Domain\ValueObject\ChefId;

/**
 * Busy Chefs read model (doc/08_kitchen_read_models.md) — fed by
 * PizzaPreparationStarted (mark busy) / PizzaPrepared (mark free), enforces
 * "a chef prepares one pizza at a time" (doc/08_kitchen_aggregates.md §2,
 * invariant 1).
 */
interface BusyChefsInterface
{
    public function markBusy(ChefId $chefId): void;

    public function markFree(ChefId $chefId): void;

    public function isFree(ChefId $chefId): bool;
}
