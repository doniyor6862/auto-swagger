<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ApiSecurity
{
    public function __construct(
        public string $name,
        public array $scopes = []
    ) {
    }
}
