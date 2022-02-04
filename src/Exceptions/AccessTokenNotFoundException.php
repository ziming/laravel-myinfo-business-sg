<?php

namespace Ziming\LaravelMyinfoBusinessSg\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class AccessTokenNotFoundException extends HttpException
{
    /**
     * AccessTokenNotFoundException constructor.
     * @param \Exception|null $previous
     * @param int $code
     */
    public function __construct(int $statusCode = 404, string $message = 'Access Token Not Found', \Exception $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function render()
    {
        return response()->json([
            'message' => $this->message,
        ], $this->getStatusCode());
    }
}
