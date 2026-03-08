<?php

declare(strict_types=1);

use Lyger\Routing\Route;
use Lyger\Http\Response;
use App\Controllers\EngineController;

Route::get('/', function () {
    $htmlPath = dirname(__DIR__) . '/frontend/index.html';

    if (file_exists($htmlPath)) {
        return Response::html(file_get_contents($htmlPath));
    }

    return Response::json([
        'name' => 'Lyger Framework v0.1',
        'status' => 'running',
        'message' => 'Welcome to Lyger - PHP on steroids with Rust FFI'
    ]);
});

Route::get('/api/hello', [EngineController::class, 'hello']);
Route::get('/api/benchmark', [EngineController::class, 'benchmark']);
Route::get('/api/system', [EngineController::class, 'systemInfo']);
