<?php

declare(strict_types=1);

namespace TypedJson;

/**
 * @phpstan-type JsonNode JsonObject | string | bool | int | float | null | list<mixed>
 */
final class JsonObject
{
    /**
     * @param array<string, JsonNode> $members
     */
    public function __construct(public readonly array $members)
    {
    }
}
