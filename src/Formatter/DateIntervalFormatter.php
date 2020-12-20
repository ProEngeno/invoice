<?php

namespace Proengeno\Invoice\Formatter;

use DateTime;
use Proengeno\Invoice\Interfaces\TypeFormatter;

class DateIntervalFormatter implements TypeFormatter
{
    private string $pattern;

    public function __construct(string $locale)
    {
        if ($locale == 'de_DE') {
            $this->pattern = '%a Tage';
        } else {
            $this->pattern = '%a days';
        }
    }

    public function setPattern(string $pattern): void
    {
        $this->pattern = $pattern;
    }

    public function format($value): string
    {
        return $value->format($this->pattern);
    }
}
