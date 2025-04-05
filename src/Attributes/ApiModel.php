<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ApiModel
{
    public function __construct(
        public string $description = '',
        public array $properties = []
    ) {
    }
}
