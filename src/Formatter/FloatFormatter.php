<?php

namespace Proengeno\Invoice\Formatter;

use NumberFormatter;

class FloatFormatter
{
    protected $formatter;

    public function __construct(string $locale)
    {
        $this->formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
    }

    public function setPattern(string $pattern): void
    {
        $this->formatter->setPattern($pattern);
    }

    public function format(float $value): string
    {
        return $this->formatter->format($value);
    }
}
