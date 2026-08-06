<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\UI\Http;

use Pizzeria\Kitchen\Application\Port\KitchenOrderRepositoryInterface;
use Pizzeria\Kitchen\Application\Port\OrderProgressInterface;
use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Order Progress read model, exposed over HTTP — exercises RoadRunner's
 * HTTP mode alongside the jobs consumers, per doc/09_architecture.md §3.
 */
final class OrderProgressController
{
    public function __construct(
        private readonly KitchenOrderRepositoryInterface $kitchenOrders,
        private readonly OrderProgressInterface $orderProgress,
    ) {
    }

    #[Route('/kitchen/orders/{kitchenOrderId}/progress', name: 'kitchen_order_progress', methods: ['GET'])]
    public function __invoke(string $kitchenOrderId): JsonResponse
    {
        $id = KitchenOrderId::fromString($kitchenOrderId);
        $kitchenOrder = $this->kitchenOrders->get($id);

        return new JsonResponse([
            'kitchenOrderId' => $id->toString(),
            'status' => $kitchenOrder->status(),
            'totalPizzaCount' => $kitchenOrder->totalPizzaCount(),
            'completedCount' => $this->orderProgress->completedCount($id),
            'correlationId' => $kitchenOrder->correlationId(),
        ]);
    }
}
