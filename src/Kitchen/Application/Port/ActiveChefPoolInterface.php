<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Port;

use Pizzeria\Kitchen\Domain\ValueObject\ChefId;

/**
 * Active Chef Pool read model (doc/08_kitchen_read_models.md) — employment
 * status only, fed by Resource Management's Chef* events over Kafka. Not
 * "currently busy" — that's Busy Chefs (doc/08_kitchen_domain_model.md §4).
 */
interface ActiveChefPoolInterface
{
    public function markActive(ChefId $chefId): void;

    public function markInactive(ChefId $chefId): void;

    public function isActive(ChefId $chefId): bool;

    public function countActive(): int;
}
