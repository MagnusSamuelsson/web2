<?php

use App\Services\GdImageProcessor;
use App\Services\VipsImageProcessor;
define('APP_ROOT', realpath(__DIR__ . '/../'));
require __DIR__ . '/../vendor/autoload.php';

use App\Core\Environment;
use App\Controllers\AuthController;
use App\Controllers\CommentController;
use App\Controllers\PostController;
use App\Controllers\PostImageController;
use App\Controllers\ProfileimageController;
use App\Controllers\UserController;
use App\Http\Request;
use App\Http\Response;
use App\Services\Dbh;
use App\Middlewares\AuthMiddleware;
use App\Services\ImageProcessorInterface;
use App\Services\ImagickImageProcessor;
use Rammewerk\Component\Container\Container;
use Rammewerk\Router\Router;
use Rammewerk\Router\Error\InvalidRoute;
use Rammewerk\Router\Error\RouterConfigurationException;

date_default_timezone_set('Europe/Stockholm');

Environment::load(APP_ROOT);
$container = new Container();
$container = $container->share([
    Dbh::class,
    Response::class,
    Request::class
]);


switch (Environment::get('IMAGE_PROCESSOR')) {
    case 'imagick':
        $container = $container->bind(ImageProcessorInterface::class, ImagickImageProcessor::class);
        break;
    case 'vips':
        $container = $container->bind(ImageProcessorInterface::class, VipsImageProcessor::class);
        break;
    case 'gd':
        $container = $container->bind(ImageProcessorInterface::class, GdImageProcessor::class);
        break;
    default:
        throw new Exception('Invalid image processor');
}

$router = new Router(static fn(string $class_string) => $container->create($class_string));

$router->group(function (Router $protectedRouter) {
    $protectedRouter->add('/comment', CommentController::class);
    $protectedRouter->add('/auth/check-auth', AuthController::class);
    $protectedRouter->add('/user', UserController::class);
    $protectedRouter->add('/post', PostController::class);
})->middleware([AuthMiddleware::class]);

$router->group(function (Router $publicRouter) {
    $publicRouter->add('/post/public/user/*', PostController::class)->classMethod('getByUser');
    $publicRouter->add('/post/public', PostController::class)->classMethod('getAll');
    $publicRouter->add('/auth', AuthController::class);
    $publicRouter->add('/user/public/*', UserController::class)->classMethod('getUserProfileInfo');
    $publicRouter->add('/profileimage', ProfileimageController::class);
    $publicRouter->add('/postimage', PostImageController::class);
});

$request = $container->create(Request::class);

try {
    $response = $router->dispatch(serverRequest: $request);
    $response->send();
} catch (InvalidRoute $e) {
    $response = $container->create(Response::class);
    $response->notFound(['message' => $e->getMessage()])->send();
} catch (RouterConfigurationException $e) {
    $response = $container->create(Response::class);
    $response->internalServerError(['message1' => $e->getMessage()])->send();
} catch (Throwable $e) {
    $response = $container->create(Response::class);
    $response->internalServerError(['message2' => $e->getMessage()])->send();
}