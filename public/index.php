<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Lyger\Http\Request;
use Lyger\Http\Response;
use Lyger\Routing\Router;
use Lyger\Container\Container;
use Lyger\Core\Engine;
use App\Controllers\EngineController;

$container = Container::getInstance();

$engine = Engine::getInstance();
$container->singleton(Engine::class, $engine);
$container->singleton(EngineController::class, new EngineController($engine));

$router = new Router($container);
$router->loadRoutesFromFile(__DIR__ . '/../routes/web.php');

$request = Request::capture();
$response = $router->dispatch($request);
$response->send();
