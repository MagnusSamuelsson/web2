<?php
namespace App\Controllers;

use App\Core\Environment;
use App\Http\Cookie;
use App\Repositories\AuthRepository;
use App\Repositories\UserRepository;
use App\Models\User;
use App\Http\Request;
use App\Http\Response;
use Rammewerk\Router\Foundation\Route;
use Rammewerk\Router\Error\InvalidRoute;

#[Route('/auth')]
class AuthController
{
    public function __construct(
        private Request $request,
        private Response $response,
        private AuthRepository $authRepository,
        private UserRepository $userRepository
    ) {
    }
    public function checkAuth(): Response
    {
        if (!$this->request->isGet()) {
            throw new InvalidRoute('Method not allowed');
        }
        $userId = $this->request->getAttribute('userId');
        $user = $this->userRepository->getUserById($userId);

        return $this->response->ok( [
            'user' => $user
        ]);
    }
    #[Route('/token')]
    public function token(): Response
    {
        if (!$this->request->isGet()) {
            throw new InvalidRoute('Method not allowed');
        }

        $refreshToken = $this->request->cookie(Environment::get('REFRESH_TOKEN_COOKIE_NAME'));

        if (!$refreshToken) {
            return $this->response->unauthorized([
                'message' => 'No refresh token found'
            ]);
        }

        $refreshToken = $this->authRepository->getRefreshToken($refreshToken);

        if (!$refreshToken) {
            return $this->response->unauthorized([
                'message' => 'Invalid refresh token'
            ]);
        }


        $user = $this->userRepository->getUserById($refreshToken->user_id);

        $accessToken = $this->authRepository
            ->setRefreshTokenExpiration($refreshToken)
            ->generateRefreshToken($refreshToken)
            ->updateRefreshToken($refreshToken)
            ->generateAccessToken($refreshToken->user_id);

        $refreshTokenCookie = new Cookie(
            name: Environment::get('REFRESH_TOKEN_COOKIE_NAME'),
            value: $refreshToken->token
        );
        $refreshTokenCookie->setExpirationDays(Environment::get('REFRESH_TOKEN_EXPIRATION_DAYS'));

        return $this->response
            ->setCookie($refreshTokenCookie)
            ->ok([
                'access_token' => $accessToken
            ]);
    }

    #[Route('register')]
    public function register(): Response
    {
        if (!$this->request->isPost()) {
            throw new InvalidRoute('Method not allowed');
        }

        $username = $this->request->get('username');
        $password = $this->request->get('password');


        if (!$username || !$password) {
            $this->response->addError('Username and password are required');
            return $this->response->badRequest();
        }

        $user = new User();
        if (!$user->setPassword($password)) {
            $this->response->addError('Password does not meet requirements');
            return $this->response->badRequest();
        }

        $user->username = $username;

        if (!$user->validateUsername()) {
            $this->response->addError('Username does not meet requirements');
            return $this->response->badRequest();
        }

        if ($this->userRepository->getUserByUsername($username)) {
            $this->response->addError('Username already exists');
            return $this->response->badRequest();
        }

        $this->userRepository->createUser($user);

        return $this->response->created(
            url: "user/{$user->id}",
            data: ['user' => $user],
        );
    }

    #[Route('login')]
    public function login(): Response
    {
        if (!$this->request->isPost()) {
            throw new InvalidRoute('Method not allowed');
        }

        $username = $this->request->get('username');
        $password = $this->request->get('password');

        if (!$username || !$password) {
            $this->response->addError('Username and password are required');
            return $this->response->badRequest();
        }

        $user = $this->userRepository->getUserByUsername($username, false);

        if (!$user) {
            return $this->response->unauthorized([
                'message' => 'Invalid username or password'
            ]);
        }

        if (!$user->verifyPassword($password)) {
            return $this->response->unauthorized([
                'message' => 'Invalid username or password'
            ]);
        }

        $refreshToken = $this->authRepository->createNewRefreshToken($user->id);

        $refreshCookie = new Cookie(
            name: Environment::get('REFRESH_TOKEN_COOKIE_NAME'),
            value: $refreshToken->token
        );
        $refreshCookie->setExpirationDays(Environment::get('REFRESH_TOKEN_EXPIRATION_DAYS'));

        $this->response->setCookie($refreshCookie);

        $accessToken = $this->authRepository->generateAccessToken($user->id);
        unset($user->password);

        return $this->response->ok([
            'access_token' => $accessToken,
            'user' => $user
        ]);
    }

    #[Route('logout')]
    public function logout(): Response
    {
        if (!$this->request->isPost()) {
            throw new InvalidRoute('Method not allowed');
        }

        $refreshToken = $this->request->cookie(Environment::get('REFRESH_TOKEN_COOKIE_NAME'));

        if (!$refreshToken) {
            return $this->response->unauthorized([
                'message' => 'No refresh token found'
            ]);
        }

        $this->authRepository->deleteRefreshToken($refreshToken);

        $refreshCookie = new Cookie(
            name: Environment::get('REFRESH_TOKEN_COOKIE_NAME')
        );

        $this->response->deleteCookie($refreshCookie);

        return $this->response->ok([
            'message' => 'Logged out'
        ]);
    }

}