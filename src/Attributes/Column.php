<?php

namespace Proxima\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)] // Bu etiket sadece değişkenlerin tepesine konabilir
class Column
{
    public function __construct(
        public string $type,          // string, integer, text, boolean, decimal
        public int $length = 255,     // varchar length or decimal precision
        public int $scale = 0,        // decimal scale (digits after decimal point)
        public bool $primaryKey = false,
        public bool $autoIncrement = false,
        public bool $nullable = false,
        public bool $unique = false,
        public string|int|float|null $default = null  // default value
    ) {}
}