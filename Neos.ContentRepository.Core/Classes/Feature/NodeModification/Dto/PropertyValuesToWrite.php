<?php

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Feature\NodeModification\Dto;

use Neos\ContentRepository\Core\SharedModel\Node\PropertyNames;

/**
 * Property values to write, supports arbitrary objects. Will be then converted to {@see SerializedPropertyValues}
 * inside the events and persisted commands.
 *
 * This object does not store the types of the values separately, while in {@see SerializedPropertyValues}, the types
 * are stored in the data structure.
 * We expect the value types to match the NodeType's property types (this is validated in the command handler).
 *
 * A null value will cause to unset a nodes' property.
 *
 * @api used as part of commands
 */
final readonly class PropertyValuesToWrite
{
    /**
     * @param array<string,mixed> $values
     */
    private function __construct(
        public array $values
    ) {
    }

    public static function createEmpty(): self
    {
        return new self([]);
    }

    /**
     * @param array<string,mixed> $values
     */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    public static function fromJsonString(string $jsonString): self
    {
        try {
            return self::fromArray(\json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            throw new \RuntimeException(sprintf('Failed to JSON-decode "%s": %s', $jsonString, $e->getMessage()), 1723032130, $e);
        }
    }

    /**
     * Adds a property value to write.
     *
     * To declare to unset a property, `null` is used:
     *
     *     $propertyValues->withValue('my-property', null);
     *
     */
    public function withValue(string $valueName, mixed $value): self
    {
        $values = $this->values;
        $values[$valueName] = $value;
        return new self($values);
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->values, $other->values));
    }

    /** @internal you should not need this in user-land */
    public function withoutUnsets(): self
    {
        return new self(array_filter($this->values, fn ($value) => $value !== null));
    }

    /** @internal you should not need this in user-land */
    public function getPropertiesToUnset(): PropertyNames
    {
        return PropertyNames::fromArray(
            array_keys(
                array_filter($this->values, fn ($value) => $value === null)
            )
        );
    }
}
