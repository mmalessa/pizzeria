<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Service;

use Pizzeria\Kitchen\Application\Port\OrderProgressInterface;
use Pizzeria\Kitchen\Domain\Aggregate\KitchenOrder;

/**
 * doc/08_kitchen_domain_services.md — answers whether every pizza for this
 * order has reached Ready. Takes the whole KitchenOrder, not an extracted
 * totalPizzaCount scalar (domain service signature convention,
 * design_notes/dn_0003.md).
 */
final class OrderReadinessCheck
{
    public function __construct(private readonly OrderProgressInterface $orderProgress)
    {
    }

    public function isReady(KitchenOrder $kitchenOrder): bool
    {
        return $this->orderProgress->completedCount($kitchenOrder->kitchenOrderId()) >= $kitchenOrder->totalPizzaCount();
    }
}
