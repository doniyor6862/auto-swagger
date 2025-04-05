<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ApiProperty
{
    public function __construct(
        public string $type = 'string',
        public string $description = '',
        public bool $required = false,
        public mixed $example = null,
        public ?string $format = null,
        public ?array $enum = null,
        public bool $nullable = false,
    ) {
    }
}
