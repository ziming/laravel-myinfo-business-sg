<?php

namespace Ziming\LaravelMyinfoBusinessSg\Exceptions;

use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidAccessTokenException extends HttpException
{
    /**
     * InvalidAccessTokenException constructor.
     * @param int $statusCode - Default is 400, Bad Request
     * @param \Exception|null $previous
     * @param int $code
     */
    public function __construct(int $statusCode = Response::HTTP_BAD_REQUEST, string $message = 'Invalid Access Token', \Exception $previous = null, array $headers = [], ?int $code = 0)
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
