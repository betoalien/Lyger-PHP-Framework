<?php

declare(strict_types=1);

namespace App\Controllers;

use Lyger\Http\Request;
use Lyger\Http\Response;

class Test
{
    public function index(): Response
    {
        return Response::json(['message' => 'Test controller']);
    }
}
