<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Infrastructure\Outbox;

use Pizzeria\Kitchen\Application\Port\OutboxInterface;
use Pizzeria\Shared\SchemaValidatorInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * doc/09_architecture.md §5: outbox on publish, schema-validated
 * (doc/09_integration_contracts.md §3) before anything leaves the
 * process, envelope shape per doc/09_integration_contracts.md §2.
 *
 * Logs instead of relaying to a real broker producer for this pass
 * (session scope decision) — proving consumption under RoadRunner is
 * this pass's goal; real publish-back is a disclosed follow-up, not
 * an architectural change.
 */
final class LoggingOutbox implements OutboxInterface
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(string $eventType, int $schemaVersion, array $payload, string $correlationId): void
    {
        $this->schemaValidator->validate($eventType, (string) $schemaVersion, $payload);

        $envelope = [
            'eventId' => Uuid::uuid4()->toString(),
            'eventType' => $eventType,
            'schemaVersion' => $schemaVersion,
            'occurredAt' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.vp'),
            'correlationId' => $correlationId,
            'payload' => $payload,
        ];

        $this->logger->info('[outbox] publish {eventType}', $envelope);
    }
}
