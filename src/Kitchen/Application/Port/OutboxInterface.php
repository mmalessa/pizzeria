<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Port;

/**
 * doc/09_architecture.md §5 — outbox on publish, after commit. Backed by an
 * in-memory store for this pass (doc/09_integration_contracts.md §2's
 * envelope shape either way); swapping in a real transactional outbox later
 * changes only the implementation behind this interface.
 */
interface OutboxInterface
{
    /**
     * @param non-empty-string $eventType
     * @param positive-int $schemaVersion
     * @param array<string, mixed> $payload
     */
    public function publish(
        string $eventType,
        int $schemaVersion,
        array $payload,
        string $correlationId,
    ): void;
}
