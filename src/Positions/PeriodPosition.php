<?php

namespace Proengeno\Invoice\Positions;

use DateTime;
use DateInterval;
use Proengeno\Invoice\Formatter\FormatableTrait;
use Proengeno\Invoice\Interfaces\PeriodPosition as PeriodPositionInterface;

class PeriodPosition extends Position implements PeriodPositionInterface
{
    use FormatableTrait;

    private $from;
    private $until;

    public function __construct(string $name, float $price, float $quantity, DateTime $from, DateTime $until)
    {
        parent::__construct($name, $price, $quantity);
        $this->from = $from;
        $this->until = $until;
    }

    public static function fromArray(array $attributes)
    {
        extract($attributes);

        return new static($name, $price, $quantity, new DateTime($from), new DateTime($until));
    }

    public function from(): DateTime
    {
        return $this->from;
    }

    public function until(): DateTime
    {
        return $this->until;
    }

    public function period(): DateInterval
    {
        return (clone $this->from())->modify('-1 day')->diff($this->until());
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'from' => $this->from()->format('Y-m-d H:i:s'),
            'until' => $this->until()->format('Y-m-d H:i:s'),
        ]);
    }
}
