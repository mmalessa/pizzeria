<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Port;

use Pizzeria\Kitchen\Domain\Aggregate\KitchenOrder;
use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderId;

interface KitchenOrderRepositoryInterface
{
    public function exists(KitchenOrderId $kitchenOrderId): bool;

    public function save(KitchenOrder $kitchenOrder): void;

    public function get(KitchenOrderId $kitchenOrderId): KitchenOrder;
}
