<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ApiRequestBody
{
    public function __construct(
        public string $description = '',
        public bool $required = true,
        public array $content = [],
        public ?string $ref = null,
    ) {
    }
}
