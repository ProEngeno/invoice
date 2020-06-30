<?php

namespace Proengeno\Invoice;

use Proengeno\Invoice\Formatter\Formatter;
use Proengeno\Invoice\Interfaces\Calculator;
use Proengeno\Invoice\Interfaces\Formatable;
use Proengeno\Invoice\Positions\PositionGroup;
use Proengeno\Invoice\Formatter\FormatableTrait;
use Proengeno\Invoice\Calculator\BcMathCalculator;
use Proengeno\Invoice\Positions\PositionCollection;

class Invoice implements \JsonSerializable, Formatable
{
    use FormatableTrait;

    private static $calculator;
    protected $positionGroups;

    public function __construct(PositionGroup ...$positionGroups)
    {
        $this->positionGroups = $positionGroups;
    }

    public static function fromArray(array $positionsGroupsArray)
    {
        $positionGroups = [];

        foreach ($positionsGroupsArray as $positionGroup) {
            $positionGroups[] = PositionGroup::fromArray($positionGroup);
        }

        return new static(...$positionGroups);
    }

    public static function negateFromArray(array $positionsGroupsArray)
    {
        foreach ($positionsGroupsArray as $positionGroupKey => $positionGroup) {
            foreach ($positionGroup['positions'] ?? [] as $positionClass => $positions) {
                foreach ($positions as $positionKey => $positionAttributes) {
                    $positionsGroupsArray[$positionGroupKey]['positions'][$positionClass][$positionKey]['price'] *= -1;
                }
            }
        }

        return static::fromArray($positionsGroupsArray);
    }

    public static function getCalulator(): Calculator
    {
        if (null === self::$calculator) {
            self::$calculator = new BcMathCalculator;
        }

        return self::$calculator;
    }

    public static function setCalulator(Calculator $calculator): void
    {
        self::$calculator = $calculator;
    }

    public function negate()
    {
        return static::negateFromArray($this->jsonSerialize());
    }

    public function setFormatter(Formatter $formatter): void
    {
        $this->formatter = $formatter;
        foreach ($this->positionGroups as $positionGroup) {
            $positionGroup->setFormatter($formatter);
        }
    }

    public function positionGroups(): array
    {
        return $this->positionGroups;
    }

    public function netPositions(string $name = null): PositionCollection
    {
        return $this->filterPositions('isNet', $name);
    }

    public function grossPositions(string $name = null): PositionCollection
    {
        return $this->filterPositions('isGross', $name);
    }

    public function netAmount(): int
    {
        return $this->sum('netAmount');
    }

    public function vatAmount(): int
    {
        return $this->sum('vatAmount');
    }

    public function grossAmount(): int
    {
        return $this->sum('grossAmount');
    }

    public function jsonSerialize(): array
    {
        $array = [];
        foreach ($this->positionGroups as $positionGroup) {
            $array[] = $positionGroup->jsonSerialize();
        }
        return $array;
    }

    private function sum(string $method): int
    {
        return array_reduce($this->positionGroups, function(int $total, PositionGroup $positionGroup) use ($method): int {
            return $total + $positionGroup->$method();
        }, 0);
    }

    private function filterPositions(string $vatType, string $name = null): PositionCollection
    {
        $positions = new PositionCollection;
        $positions->setFormatter($this->formatter);
        foreach ($this->positionGroups as $positionGroup) {
            if ($positionGroup->$vatType()) {
                if (null === $name) {
                    $positions = $positions->merge($positionGroup->positions());
                } else {
                    $positions = $positions->merge($positionGroup->positions()->only($name));
                }
            }
        }
        return $positions;
    }
}
