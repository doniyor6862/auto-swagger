<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiResponse
{
    public function __construct(
        public int $statusCode,
        public string $description,
        public ?string $type = null,
        public ?string $ref = null,
        public array $content = [],
        public array $headers = []
    ) {
    }
}
