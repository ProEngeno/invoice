<?php

namespace Proengeno\Invoice\Interfaces;

interface Position extends \JsonSerializable, Formatable
{
    /** @return static */
    public static function fromArray(array $attributes);

    public function name(): string;

    public function quantity(): float;

    public function price(): float;

    public function amount(): float;

    public function format(string $method): string;

    public function jsonSerialize(): array;
}
