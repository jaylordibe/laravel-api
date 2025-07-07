<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BadRequestException extends Exception
{

    /**
     * Additional context for the error.
     *
     * @var array
     */
    public readonly array $context;

    /**
     * Create a new exception instance.
     *
     * @param string $message The exception message.
     * @param array $context Additional data to help debug or explain the error.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for the exception chaining.
     */
    public function __construct(string $message = 'Bad Request', array $context = [], int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the exception's context information.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        // ...
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        $response = [
            'error' => $this->getMessage()
        ];
        return response()->json($response, $this->getCode());
    }

}
