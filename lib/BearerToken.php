<?php

namespace RW;

use Psr\Http\Message\RequestInterface;

class BearerToken
{
    private $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function __invoke(callable $handler)
    {
        return function ($request, array $options) use ($handler) {
            $request = $this->onBefore($request);
            return $handler($request, $options);
        };
    }

    private function onBefore(RequestInterface $request)
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }
}
