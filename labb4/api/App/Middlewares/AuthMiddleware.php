<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\AuthRepository;
use Closure;

class AuthMiddleware
{
    public function __construct(
        private Response $response,
        private AuthRepository $authRepository
    ) {
    }
    public function handle(Request $request, Closure $next): Mixed {

        $jwt = $request->getBearerToken();
        if (!$jwt) {
            $this->response->addError('No token found');
            return $this->response->badRequest();
        }

        $jwt = $this->authRepository->validateAccessToken($jwt);

        if (!$jwt) {
            $this->response->addError('Invalid token');
            return $this->response->unauthorized();
        }

        $request->setAttribute('userId', (int)$jwt->user_id);

        return $next($request);
    }
}