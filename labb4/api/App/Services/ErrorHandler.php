<?php
namespace App\Services;

use App\Http\Response;

class ErrorHandler
{
    public function __construct(private Response $response) {}

    public function handleError(\Exception $e): bool
    {
        $this->response->addError($e->getMessage());
        return false;
    }

}
