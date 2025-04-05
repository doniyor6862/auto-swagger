<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exception thrown when there is insufficient stock for a product
 */
class InsufficientStockException extends Exception
{
    /**
     * Unique error code for this exception type
     */
    const ERROR_CODE = 'INSUFFICIENT_STOCK';
    
    public function __construct(
        protected int $productId,
        protected int $requestedQuantity,
        protected int $availableQuantity,
        string $message = null
    ) {
        $this->message = $message ?? "Insufficient stock for product {$productId}. Requested: {$requestedQuantity}, Available: {$availableQuantity}";
    }

    /**
     * Report the exception.
     */
    public function report(): bool
    {
        // Log the exception
        return false;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->message,
            'error_code' => self::ERROR_CODE,
            'exception' => class_basename($this),
            'product_id' => $this->productId,
            'requested_quantity' => $this->requestedQuantity,
            'available_quantity' => $this->availableQuantity,
        ], 409);
    }
}
