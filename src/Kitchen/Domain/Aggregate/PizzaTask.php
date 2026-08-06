<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Domain\Aggregate;

use Pizzeria\Kitchen\Domain\ValueObject\ChefId;
use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderId;
use Pizzeria\Kitchen\Domain\ValueObject\MenuItemId;
use Pizzeria\Kitchen\Domain\ValueObject\PizzaTaskId;

/**
 * doc/08_kitchen_aggregates.md §2.
 *
 * "A chef must be Active and currently free" (invariant 1) and "strictly
 * FIFO selection" (invariant 3) are cross-aggregate/read-model concerns
 * enforced by TaskSelectionPolicy (Application layer) before pickUp() is
 * called — this class only guards its own local state transitions.
 */
final class PizzaTask
{
    public const string STATUS_PENDING = 'Pending';
    public const string STATUS_IN_PREPARATION = 'InPreparation';
    public const string STATUS_READY = 'Ready';

    private ?ChefId $chefId = null;

    private function __construct(
        private readonly PizzaTaskId $pizzaTaskId,
        private readonly KitchenOrderId $kitchenOrderId,
        private readonly MenuItemId $menuItemId,
        private readonly string $correlationId,
        private string $status,
    ) {
    }

    /**
     * $correlationId is inherited from the parent KitchenOrder at creation
     * (doc/09_integration_contracts.md §2's propagation rule, applied here
     * the same way it applies to KitchenOrder itself) so ChefFinishedPizza
     * can carry it forward, the same as OrderReadyForPickup does.
     */
    public static function createPending(
        PizzaTaskId $pizzaTaskId,
        KitchenOrderId $kitchenOrderId,
        MenuItemId $menuItemId,
        string $correlationId,
    ): self {
        return new self($pizzaTaskId, $kitchenOrderId, $menuItemId, $correlationId, self::STATUS_PENDING);
    }

    public function pickUp(ChefId $chefId): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \LogicException(sprintf(
                'PizzaTask %s cannot be picked up from status %s.',
                $this->pizzaTaskId->toString(),
                $this->status,
            ));
        }

        $this->chefId = $chefId;
        $this->status = self::STATUS_IN_PREPARATION;
    }

    /**
     * Raises PizzaPrepared (internal) and ChefFinishedPizza (external) in
     * the caller (doc/08_kitchen_aggregates.md §2, invariant 2) — this
     * method only performs the aggregate's own state transition.
     */
    public function finish(): void
    {
        if ($this->status !== self::STATUS_IN_PREPARATION) {
            throw new \LogicException(sprintf(
                'PizzaTask %s cannot finish from status %s.',
                $this->pizzaTaskId->toString(),
                $this->status,
            ));
        }

        $this->status = self::STATUS_READY;
    }

    public function pizzaTaskId(): PizzaTaskId
    {
        return $this->pizzaTaskId;
    }

    public function kitchenOrderId(): KitchenOrderId
    {
        return $this->kitchenOrderId;
    }

    public function menuItemId(): MenuItemId
    {
        return $this->menuItemId;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function chefId(): ?ChefId
    {
        return $this->chefId;
    }
}
