<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

/**
 * Attribute to document a business exception that can be thrown by an API endpoint.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ApiException
{
    /**
     * Create a new API exception attribute instance.
     *
     * @param string $exception The fully qualified exception class name
     * @param int $statusCode The HTTP status code returned when this exception occurs
     * @param string $description Human-readable description of when this exception occurs
     * @param array $schema Custom schema for the error response (optional)
     */
    public function __construct(
        public string $exception,
        public int $statusCode = 422,
        public string $description = '',
        public array $schema = [],
    ) {
    }
}
