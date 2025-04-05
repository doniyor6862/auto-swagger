<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\AutoSwagger\Attributes\ApiResource;

/**
 * Resource for a custom response format without a specific model
 */
#[ApiResource(
    schema: [
        'type' => 'object',
        'properties' => [
            'success' => [
                'type' => 'boolean',
                'description' => 'Whether the operation was successful',
                'example' => true
            ],
            'message' => [
                'type' => 'string',
                'description' => 'A message describing the result',
                'example' => 'Dashboard data retrieved successfully'
            ],
            'stats' => [
                'type' => 'object',
                'properties' => [
                    'total_orders' => [
                        'type' => 'integer',
                        'description' => 'Total number of orders',
                        'example' => 1250
                    ],
                    'total_revenue' => [
                        'type' => 'number',
                        'format' => 'float',
                        'description' => 'Total revenue in USD',
                        'example' => 52436.75
                    ],
                    'average_order_value' => [
                        'type' => 'number',
                        'format' => 'float',
                        'description' => 'Average order value in USD',
                        'example' => 41.95
                    ],
                    'top_categories' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => [
                                    'type' => 'string',
                                    'example' => 'Electronics'
                                ],
                                'count' => [
                                    'type' => 'integer',
                                    'example' => 450
                                ],
                                'percentage' => [
                                    'type' => 'number',
                                    'format' => 'float',
                                    'example' => 36.0
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'timestamp' => [
                'type' => 'string',
                'format' => 'date-time',
                'description' => 'When the dashboard data was retrieved',
                'example' => '2025-04-05T22:30:00+00:00'
            ]
        ],
        'required' => ['success', 'message', 'stats', 'timestamp']
    ],
    description: 'Dashboard statistics resource'
)]
class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'success' => true,
            'message' => 'Dashboard data retrieved successfully',
            'stats' => [
                'total_orders' => $this->totalOrders,
                'total_revenue' => $this->totalRevenue,
                'average_order_value' => $this->averageOrderValue,
                'top_categories' => $this->topCategories->map(function ($category) {
                    return [
                        'name' => $category->name,
                        'count' => $category->count,
                        'percentage' => $category->percentage
                    ];
                })
            ],
            'timestamp' => now()->toIso8601String()
        ];
    }
}
