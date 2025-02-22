<?php

declare(strict_types=1);

namespace Ziming\LaravelMyinfoBusinessSg\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class MyinfoEntityPersonDataNotFoundException extends HttpException
{
    public function __construct(int $statusCode = 404, string $message = 'MyInfo Person Data not found', \Exception $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->message,
        ], $this->getStatusCode());
    }
}
