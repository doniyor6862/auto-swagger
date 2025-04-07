<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ApiOperation
{
    public function __construct(
        public string $summary = '',
        public string $description = '',
        public string $method = 'GET',
        public ?string $path = null,
        public ?string $operationId = null,
        public array $responses = [],
        public array $parameters = [],
        public array $tags = [],
        public array $security = [],
        public bool $deprecated = false,
    ) {
    }
}
