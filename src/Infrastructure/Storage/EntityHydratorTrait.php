<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Stringable;

trait EntityHydratorTrait
{
    /**
     * Extrahiert ein Objekt (Entity) automatisch in ein Array für die Datenbank (snake_case).
     *
     * @param array<string, mixed> $overrides Manuelle Werte für Spalten, die vom Standard abweichen.
     *
     * @return array<string, mixed>
     */
    protected function extractEntity(object $entity, array $overrides = []): array
    {
        $reflection = new ReflectionClass($entity);
        $data = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isInitialized($entity)) {
                continue;
            }

            $propName = $property->getName();
            $dbColumn = \strtolower(\preg_replace('/(?<!^)[A-Z]/', '_$0', $propName) ?? '');

            if (\array_key_exists($dbColumn, $overrides)) {
                $data[$dbColumn] = $overrides[$dbColumn];
                continue;
            }

            $value = $property->getValue($entity);

            if ($value instanceof DateTimeInterface) {
                $data[$dbColumn] = $value->format('Y-m-d H:i:s');
                continue;
            }

            if ($value instanceof Stringable) {
                $data[$dbColumn] = (string) $value;
                continue;
            }

            if (\is_bool($value)) {
                $data[$dbColumn] = (int) $value;
                continue;
            }

            if (\is_array($value)) {
                $data[$dbColumn] = \json_encode($value, \JSON_UNESCAPED_UNICODE);
                continue;
            }

            if (\is_object($value) && \property_exists($value, 'value')) {
                $data[$dbColumn] = $value->value;
                continue;
            }

            $data[$dbColumn] = $value;
        }

        foreach ($overrides as $key => $val) {
            if (\array_key_exists($key, $data)) {
                continue;
            }
            $data[$key] = $val;
        }

        return $data;
    }

    /**
     * Baut aus einem Datenbank-Row (snake_case) vollautomatisch dein Objekt zusammen.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     * @param array<string, mixed> $row
     * @param array<string, mixed> $overrides Werte, die direkt in den Konstruktor gegeben werden sollen.
     *
     * @return T
     */
    protected function hydrateEntity(string $className, array $row, array $overrides = []): object
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            $args[] = $this->resolveHydrationValue($parameter, $row, $overrides);
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $overrides
     */
    private function resolveHydrationValue(ReflectionParameter $parameter, array $row, array $overrides): mixed
    {
        $propName = $parameter->getName();

        if (\array_key_exists($propName, $overrides)) {
            return $overrides[$propName];
        }

        $dbColumn = \strtolower(\preg_replace('/(?<!^)[A-Z]/', '_$0', $propName) ?? '');

        if (!\array_key_exists($dbColumn, $row)) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            return null;
        }

        $rawValue = $row[$dbColumn];
        $type = $parameter->getType();

        if ($rawValue === null) {
            if ($type !== null && !$type->allowsNull() && $parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            return null;
        }

        return $this->castHydrationValueForType($rawValue, $type);
    }

    private function castHydrationValueForType(mixed $rawValue, mixed $type): mixed
    {
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

        if (\in_array($typeName, [DateTimeImmutable::class, DateTime::class, DateTimeInterface::class], true)) {
            $timeStr = \is_scalar($rawValue) ? (string) $rawValue : 'now';

            return new DateTimeImmutable($timeStr);
        }

        if ($typeName === 'array') {
            $jsonStr = \is_scalar($rawValue) ? (string) $rawValue : '';

            return \json_decode($jsonStr, true) ?? [];
        }

        if ($typeName === 'bool') {
            return (bool) $rawValue;
        }

        if ($typeName === 'int') {
            return \is_scalar($rawValue) ? (int) $rawValue : 0;
        }

        if ($typeName !== null && \class_exists($typeName)) {
            $voStr = \is_scalar($rawValue) ? (string) $rawValue : '';

            return new $typeName($voStr);
        }

        return $rawValue;
    }
}
