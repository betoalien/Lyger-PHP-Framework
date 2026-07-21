<?php

declare(strict_types=1);

namespace App\Controllers;

use Lyger\Http\Request;
use Lyger\Http\Response;
use Lyger\Core\Engine;

class EngineController
{
    private Engine $engine;

    public function __construct(Engine $engine)
    {
        $this->engine = $engine;
    }

    public function hello(): Response
    {
        $message = $this->engine->helloWorld();
        return Response::json(['message' => $message]);
    }

    public function benchmark(): Response
    {
        $start = microtime(true);
        $result = $this->engine->heavyComputation(5000000);
        $end = microtime(true);

        return Response::json([
            'result' => $result,
            'time_ms' => round(($end - $start) * 1000, 2),
            'iterations' => 5000000
        ]);
    }

    public function systemInfo(): Response
    {
        $info = $this->engine->systemInfo();
        return Response::json(json_decode($info, true));
    }
}
