<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidStateException extends HttpException
{
    public function __construct(int $statusCode = 404, string $message = 'Invalid State', \Exception $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->message,
        ], $this->getStatusCode());
    }
}
