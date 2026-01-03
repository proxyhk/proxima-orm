<?php

namespace Proxima\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)] // Bu etiket sadece Sınıfların tepesine konabilir
class Table
{
    public function __construct(
        public string $name
    ) {}
}