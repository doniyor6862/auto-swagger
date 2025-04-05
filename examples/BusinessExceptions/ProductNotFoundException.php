<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exception thrown when a product is not found
 */
class ProductNotFoundException extends Exception
{
    /**
     * Unique error code for this exception type
     */
    const ERROR_CODE = 'PRODUCT_NOT_FOUND';
    
    public function __construct(
        protected int $productId,
        string $message = null
    ) {
        $this->message = $message ?? "Product with ID {$productId} not found";
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
        ], 409);
    }
}
