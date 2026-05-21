<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Analytics\Domain;

use InvalidArgumentException;
use Kalaanba\Modules\Analytics\Domain\Exceptions\InvalidEventPropertiesException;

/**
 * Versioned schema describing the shape of a single analytics event name.
 *
 * Source: docs/engines/analytics/Analytics_Insights_Intelligence_Engine_System_Document.md
 *   §11 (event naming) — names follow `domain.action` (snake_case).
 *   §9, §10 — `properties` is the engine-specific bag this schema validates.
 *
 * Schema versions are immutable. To change a payload shape in a breaking
 * way, register a new EventSchema with an incremented schemaVersion.
 */
final readonly class EventSchema
{
    /** Pattern enforced on every event_name (matches OutboxEnvelope). */
    private const EVENT_NAME_PATTERN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/';

    /**
     * @param  list<string>  $requiredProperties  Keys that MUST be present in `properties`.
     * @param  list<string>  $optionalProperties  Keys that MAY be present in `properties`.
     */
    public function __construct(
        public string $eventName,
        public int $schemaVersion,
        public array $requiredProperties = [],
        public array $optionalProperties = [],
    ) {
        if (! preg_match(self::EVENT_NAME_PATTERN, $eventName)) {
            throw new InvalidArgumentException(
                "Event name \"{$eventName}\" must follow domain.action format."
            );
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException(
                "schema_version must be >= 1, got {$schemaVersion}."
            );
        }

        $overlap = array_intersect($requiredProperties, $optionalProperties);
        if ($overlap !== []) {
            throw new InvalidArgumentException(
                'A key cannot be both required and optional: '.implode(', ', $overlap)
            );
        }
    }

    /** Stable identity used by the registry (event_name + version). */
    public function key(): string
    {
        return $this->eventName.'@v'.$this->schemaVersion;
    }

    /**
     * Validate a `properties` bag against this schema.
     *
     * Required keys must all be present. Unknown keys are rejected so
     * payload drift surfaces in tests instead of silently propagating.
     *
     * @param  array<string, mixed>  $properties
     *
     * @throws InvalidEventPropertiesException
     */
    public function validateProperties(array $properties): void
    {
        $allowed = array_merge($this->requiredProperties, $this->optionalProperties);

        $missing = array_diff($this->requiredProperties, array_keys($properties));
        if ($missing !== []) {
            throw new InvalidEventPropertiesException(
                "Event {$this->key()} is missing required properties: "
                .implode(', ', $missing)
            );
        }

        $unknown = array_diff(array_keys($properties), $allowed);
        if ($unknown !== []) {
            throw new InvalidEventPropertiesException(
                "Event {$this->key()} received unknown properties: "
                .implode(', ', $unknown)
            );
        }
    }
}
