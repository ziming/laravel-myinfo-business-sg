<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidDataOrSignatureForEntityPersonDataException extends HttpException
{
    public function __construct(int $statusCode = 500, string $message = 'Invalid Data or Signature for Person Data', \Exception $previous = null, array $headers = [], ?int $code = 0)
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
