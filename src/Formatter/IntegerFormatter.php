<?php

namespace Proengeno\Invoice\Formatter;

use NumberFormatter;

class IntegerFormatter
{
    protected $formatter;

    public function __construct(string $locale)
    {
        $this->formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    }

    public function setPattern(string $pattern): void
    {
        $this->formatter->setPattern($pattern);
    }

    public function format(int $value): string
    {
        return $this->formatter->format($value / 100);
    }
}
