<?php

declare(strict_types=1);

namespace Lyger\Middleware;

use Lyger\Http\Request;
use Lyger\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}

abstract class Middleware implements MiddlewareInterface
{
    protected ?self $next = null;

    public function setNext(self $middleware): self
    {
        $this->next = $middleware;
        return $this;
    }

    public function process(Request $request, callable $handler): Response
    {
        return $this->handle($request, function (Request $req) use ($handler) {
            if ($this->next !== null) {
                return $this->next->process($req, $handler);
            }
            return $handler($req);
        });
    }
}
