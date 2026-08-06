<?php

declare(strict_types=1);

namespace Pizzeria\Shared;

/**
 * doc/09_integration_contracts.md §5 — technical-only plumbing, no domain
 * behaviour or language. Only Infrastructure/ and UI/ may reference this
 * (doc/09_architecture.md §2); Domain/ and Application/ never do.
 */
interface SchemaValidatorInterface
{
    /** @throws SchemaValidationException */
    public function validate(string $eventType, string $schemaVersion, array $payload): void;
}
