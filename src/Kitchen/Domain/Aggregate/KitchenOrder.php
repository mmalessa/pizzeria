<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Domain\Aggregate;

use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderId;
use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderLine;

/**
 * doc/08_kitchen_aggregates.md §1.
 *
 * The idempotent-creation guard (invariant 2: no-op if a KitchenOrder for
 * this ID already exists) is a repository-existence check, enforced by the
 * Application-layer coordinator before this aggregate is constructed — not
 * something the aggregate itself can know.
 */
final class KitchenOrder
{
    public const string STATUS_ACCEPTED = 'Accepted';
    public const string STATUS_READY_FOR_PICKUP = 'ReadyForPickup';

    /**
     * @param KitchenOrderLine[] $lines
     */
    private function __construct(
        private readonly KitchenOrderId $kitchenOrderId,
        private readonly array $lines,
        private readonly int $totalPizzaCount,
        private readonly string $correlationId,
        private string $status,
    ) {
    }

    /**
     * @param KitchenOrderLine[] $lines
     */
    public static function accept(KitchenOrderId $kitchenOrderId, array $lines, string $correlationId): self
    {
        $totalPizzaCount = array_sum(array_map(
            static fn (KitchenOrderLine $line): int => $line->quantity(),
            $lines,
        ));

        return new self($kitchenOrderId, $lines, $totalPizzaCount, $correlationId, self::STATUS_ACCEPTED);
    }

    /**
     * Invariant 3: only ever called once Order Progress reaches
     * totalPizzaCount — checked by OrderReadinessCheck (Application layer),
     * not by this aggregate. This guard only protects the state machine
     * itself against being called twice.
     */
    public function markReady(): void
    {
        if ($this->status !== self::STATUS_ACCEPTED) {
            throw new \LogicException(sprintf(
                'KitchenOrder %s cannot transition to ReadyForPickup from status %s.',
                $this->kitchenOrderId->toString(),
                $this->status,
            ));
        }

        $this->status = self::STATUS_READY_FOR_PICKUP;
    }

    public function kitchenOrderId(): KitchenOrderId
    {
        return $this->kitchenOrderId;
    }

    /** @return KitchenOrderLine[] */
    public function lines(): array
    {
        return $this->lines;
    }

    public function totalPizzaCount(): int
    {
        return $this->totalPizzaCount;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function status(): string
    {
        return $this->status;
    }
}
