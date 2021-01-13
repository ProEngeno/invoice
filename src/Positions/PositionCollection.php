<?php

declare(strict_types=1);

namespace Proengeno\Invoice\Positions;

use ReflectionClass;
use InvalidArgumentException;
use Proengeno\Invoice\Invoice;
use Proengeno\Invoice\Interfaces\Position;
use Proengeno\Invoice\Formatter\Formatter;
use Proengeno\Invoice\Interfaces\InvoiceArray;
use Proengeno\Invoice\Formatter\FormatableTrait;
use Proengeno\Invoice\Formatter\PositionIterator;

class PositionCollection implements InvoiceArray
{
    use FormatableTrait;

    private array $positions = [];

    public function __construct(Position ...$positions)
    {
        $this->positions = $positions;
    }

    public static function fromArray(array $positionsArray): self
    {
        $positions = [];
        foreach ($positionsArray as $positionClass => $attributesArray) {
            foreach ($attributesArray as $attributes) {
                $positions[] = self::newPosition($positionClass, $attributes);
            }
        }

        return new self(...$positions);
    }

    public static function createWithFormatter(array $positions, Formatter $formatter = null): self
    {
        $instance = new self(...$positions);
        $instance->setFormatter($formatter);

        return $instance;
    }

    public function add(Position $position): void
    {
        $this->positions[] = $position;
    }

    public function all(): array
    {
        if ($this->formatter === null) {
            return $this->positions;
        }

        $positions = [];
        foreach ($this as $position) {
            $positions[] = $position;
        }

        return $positions;
    }

    public function merge(PositionCollection $positions): self
    {
        return self::createWithFormatter(
            array_merge($this->positions, $positions->all()),
            $this->formatter
        );
    }

    /** @param string|array|callable $condition */
    public function only($condition): self
    {
        return self::createWithFormatter(
            array_filter($this->positions, fn(Position $position): bool => $this->buildClosure($condition)($position)),
            $this->formatter
        );
    }

    /** @param string|array|callable $condition */
    public function except($condition): self
    {
        return self::createWithFormatter(
            array_filter($this->positions, fn(Position $position): bool => ! $this->buildClosure($condition)($position)),
            $this->formatter
        );
    }

    public function sort(callable $callback, bool $descending = false, int $options = SORT_REGULAR): self
    {
        $results = [];

        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($this->positions as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options)
            : asort($results, $options);

        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->positions[$key];
        }

        return self::createWithFormatter($results, $this->formatter);
    }

    public function group(string $key): array
    {
        $results = [];
        foreach ($this->positions as $position) {
            if (! array_key_exists((string)$position->$key(), $results)) {
                $results[(string)$position->$key()] = static::createWithFormatter([$position], $this->formatter);
                continue;
            }
            $results[(string)$position->$key()]->add($position);
        }
        return $results;
    }

    public function sumAmount(): float
    {
        return $this->sum('amount');
    }

    public function sum(string $key): float
    {
        return array_reduce($this->positions, function(float $amount, Position $position) use ($key): float {
            return Invoice::getCalulator()->add($amount, $position->$key());
        }, 0.0);
    }

    /** @return mixed */
    public function min(string $key)
    {
        $min = null;

        foreach ($this->positions as $position) {
            if ($min === null || $position->$key() < $min) {
                $min = $position->$key();
            }
        }

        return $min;
    }

    /** @return mixed */
    public function max(string $key)
    {
        $max = null;

        foreach ($this->positions as $position) {
            if ($max === null || $position->$key() > $max) {
                $max = $position->$key();
            }
        }

        return $max;
    }

    public function getIterator(): PositionIterator
    {
        return new PositionIterator($this->positions, $this->formatter);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->positions[$offset]);
    }

    public function offsetGet($offset): Position
    {
        $position = $this->getIterator()->offsetGet($offset);
        $position->setFormatter($this->formatter);

        return $position;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->positions[] = $value;
        } else {
            $this->positions[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->positions[$offset]);
    }

    public function count(): int
    {
        return count($this->positions);
    }

    public function isEmpty(): bool
    {
        return count($this->positions) === 0;
    }

    public function jsonSerialize(): array
    {
        $array = [];
        foreach ($this->positions as $position) {
            $array[get_class($position)][] = $position->jsonSerialize();
        }
        return $array;
    }

    /** @param string|array|callable $condition */
    private function buildClosure($condition): callable
    {
        if (is_callable($condition)) {
            return $condition;
        }

        return function (Position $position) use ($condition) {
            if (!is_array($condition)) {
                $condition = [$condition];
            }
            return in_array($position->name(), $condition);
        };
    }

    private static function newPosition(string $positionClass, array $attributes): Position
    {
        if (class_exists($positionClass)) {
            if ((new ReflectionClass($positionClass))->implementsInterface(Position::class) ) {
                return $positionClass::fromArray($attributes);
            }
            throw new InvalidArgumentException("$positionClass doesn't implement '" . Position::class . "' interface");
        }
        throw new InvalidArgumentException("$positionClass doesn't exists");
    }
}
