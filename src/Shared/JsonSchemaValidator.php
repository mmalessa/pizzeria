<?php

declare(strict_types=1);

namespace Pizzeria\Shared;

use JsonSchema\Validator;

/**
 * doc/09_integration_contracts.md §5 — "Today's implementation": a thin
 * adapter reading contracts/<eventType>.v<schemaVersion>.schema.json and
 * validating with a JSON Schema library. Swapping this for a real Schema
 * Registry later changes only this class, per SchemaValidatorInterface.
 */
final class JsonSchemaValidator implements SchemaValidatorInterface
{
    public function __construct(private readonly string $contractsDir)
    {
    }

    public function validate(string $eventType, string $schemaVersion, array $payload): void
    {
        $schemaPath = sprintf('%s/%s.v%s.schema.json', $this->contractsDir, $eventType, $schemaVersion);

        if (!is_file($schemaPath)) {
            throw new SchemaValidationException(sprintf(
                'No contract schema found for %s (v%s) at %s.',
                $eventType,
                $schemaVersion,
                $schemaPath,
            ));
        }

        $schema = json_decode((string) file_get_contents($schemaPath));
        $payloadObject = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

        $validator = new Validator();
        $validator->validate($payloadObject, $schema);

        if (!$validator->isValid()) {
            $errors = array_map(
                static fn (array $error): string => sprintf('[%s] %s', $error['property'], $error['message']),
                $validator->getErrors(),
            );

            throw new SchemaValidationException(sprintf(
                'Payload for %s (v%s) failed schema validation: %s',
                $eventType,
                $schemaVersion,
                implode('; ', $errors),
            ));
        }
    }
}
