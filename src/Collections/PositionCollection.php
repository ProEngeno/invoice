<?php

declare(strict_types=1);

namespace Proengeno\Invoice\Collections;

use ArrayIterator;
use Exception;
use InvalidArgumentException;
use Proengeno\Invoice\Collections\Collection;
use Proengeno\Invoice\Invoice;
use Proengeno\Invoice\Interfaces\Position;
use Proengeno\Invoice\Formatter\Formatter;
use Proengeno\Invoice\Interfaces\InvoiceArray;
use Proengeno\Invoice\Collections\PositionCollection;
use ReflectionClass;

class PositionCollection implements InvoiceArray
{
    private Collection $positions;
    private ?Formatter $formatter = null;

    public function __construct(Position ...$positions)
    {
        $this->positions = new Collection($positions);
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

    private function cloneWithPositions(Collection $positions): self
    {
        $snapshotPositions = $this->positions;
        $this->positions = $positions;
        $instance = clone($this);
        $this->positions = $snapshotPositions;

        return $instance;
    }

    public function setFormatter(Formatter $formatter = null): void
    {
        foreach ($this->positions as $position) {
            $position->setFormatter($formatter);
        }
        $this->formatter = $formatter;
    }

    public function format(string $method, array $attributes = []): string
    {
        if ($this->formatter === null) {
            return (string)$this->$method();
        }
        return $this->formatter->format($this, $method, $attributes);
    }

    /**
     * @psalm-return list<Position>
     */
    public function all(): array
    {
        return $this->positions->all();
    }

    public function merge(PositionCollection $positions): self
    {
        return $this->cloneWithPositions(
            $this->positions->merge($positions->all())
        );
    }

    /** @param string|array|callable $condition */
    public function only($condition): self
    {
        return $this->cloneWithPositions(
            $this->positions->filter(
                fn(Position $position): bool => $this->buildClosure($condition)($position)
            )
        );
    }

    /** @param string|array|callable $condition */
    public function except($condition): PositionCollection
    {
        return $this->cloneWithPositions(
            $this->positions->filter(
                fn(Position $position): bool => ! $this->buildClosure($condition)($position)
            )
        );
    }

    public function sort(callable $callback, bool $descending = false, int $options = SORT_REGULAR): self
    {
        return $this->cloneWithPositions(
            $this->positions->sort($callback, $descending, $options),
        );
    }

    /**
     * @param string|callable $condition
     *
     * @return array<array-key, PositionCollection>
     */
    public function group($condition): array
    {
        $groups = [];

        if (! is_callable($condition)) {
            $condition = fn(Position $pos): string => $pos->$condition();
        }

        $preGroups = $this->positions->group($condition);
        foreach ($preGroups as $key => $positions) {
            $groups[$key] = $this->cloneWithPositions($positions);
        }

        return $groups;
    }

    public function sumAmount(): float
    {
        return $this->sum('amount');
    }

    public function sum(string $key): float
    {
        return $this->positions->reduce(function(float $amount, Position $position) use ($key): float {
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

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->positions->all());
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
        throw new Exception(PositionCollection::class . " is immutable.");
    }

    public function offsetUnset($offset): void
    {
        throw new Exception(PositionCollection::class . " is immutable.");
    }

    public function count(): int
    {
        return $this->positions->count();
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
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