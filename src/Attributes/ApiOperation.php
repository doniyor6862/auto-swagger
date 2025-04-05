<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ApiOperation
{
    public function __construct(
        public string $summary = '',
        public string $description = '',
        public array $responses = [],
        public array $parameters = [],
        public array $tags = [],
        public bool $deprecated = false,
    ) {
    }
}
