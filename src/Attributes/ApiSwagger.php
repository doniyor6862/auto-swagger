<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

/**
 * Attribute to mark a controller method or class for inclusion in Swagger documentation.
 * When applied to a class, all public methods will be included.
 * When applied to a method, only that method will be included.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ApiSwagger
{
    /**
     * Create a new ApiSwagger attribute instance.
     *
     * @param bool $include Whether to include this method in Swagger documentation
     */
    public function __construct(
        public bool $include = true,
    ) {
    }
}
