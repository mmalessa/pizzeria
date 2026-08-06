<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Infrastructure\InMemory;

use Pizzeria\Kitchen\Application\Port\PizzaTaskRepositoryInterface;
use Pizzeria\Kitchen\Domain\Aggregate\PizzaTask;
use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderId;
use Pizzeria\Kitchen\Domain\ValueObject\PizzaTaskId;

final class InMemoryPizzaTaskRepository implements PizzaTaskRepositoryInterface
{
    /** @var array<string, PizzaTask> insertion order preserved — doubles as creation order for FIFO */
    private array $pizzaTasks = [];

    public function save(PizzaTask $pizzaTask): void
    {
        $this->pizzaTasks[$pizzaTask->pizzaTaskId()->toString()] = $pizzaTask;
    }

    public function get(PizzaTaskId $pizzaTaskId): PizzaTask
    {
        return $this->pizzaTasks[$pizzaTaskId->toString()]
            ?? throw new \RuntimeException(sprintf('PizzaTask %s not found.', $pizzaTaskId->toString()));
    }

    public function findByKitchenOrderId(KitchenOrderId $kitchenOrderId): array
    {
        return array_values(array_filter(
            $this->pizzaTasks,
            static fn (PizzaTask $task): bool => $task->kitchenOrderId()->equals($kitchenOrderId),
        ));
    }

    public function findPendingOldestFirst(): array
    {
        // array insertion order = creation order, since entries are never
        // reordered or removed — a direct FIFO query, per
        // doc/08_kitchen_read_models.md's Production Queue.
        return array_values(array_filter(
            $this->pizzaTasks,
            static fn (PizzaTask $task): bool => $task->status() === PizzaTask::STATUS_PENDING,
        ));
    }

    public function findAll(): array
    {
        return array_values($this->pizzaTasks);
    }
}
