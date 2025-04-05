<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiParameter
{
    public function __construct(
        public string $name,
        public string $in = 'query', // query, path, header, cookie
        public string $description = '',
        public bool $required = false,
        public ?string $type = 'string',
        public ?string $format = null,
        public ?array $schema = null,
        public mixed $example = null,
    ) {
    }
}
