<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Exceptions;

use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidAccessTokenException extends HttpException
{
    public function __construct(int $statusCode = Response::HTTP_BAD_REQUEST, string $message = 'Invalid Access Token', \Exception $previous = null, array $headers = [], ?int $code = 0)
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
