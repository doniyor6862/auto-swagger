<?php

namespace Laravel\AutoSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ApiResource
{
    /**
     * Create a new API resource attribute instance.
     *
     * @param string|null $model The fully qualified class name of the model this resource represents
     * @param array $schema Custom schema definition to use when no model is specified
     * @param array $relations Define related resources to include in the documentation
     *                        Format: ['relation_name' => 'ResourceClass'] or 
     *                                ['relation_name' => ['resource' => 'ResourceClass', 'isCollection' => true]]
     * @param bool $isPaginated Whether this resource is paginated (for collections)
     * @param bool $isCollection Whether this resource represents a collection
     * @param string|null $description Description of the resource
     * @param bool $includeAllRelations Whether to include all relations in the documentation
     */
    public function __construct(
        public ?string $model = null,
        public array $schema = [],
        public array $relations = [],
        public bool $isPaginated = false,
        public bool $isCollection = false,
        public ?string $description = null,
        public bool $includeAllRelations = false,
    ) {
    }
}
