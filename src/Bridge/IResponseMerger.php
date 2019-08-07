<?php

declare(strict_types = 1);

namespace SwooleProxy\Bridge;

use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;

interface IResponseMerger
{

    public function mergeToSwoole(
        ResponseInterface $response,
        Response $swooleResponse
    ): Response;

}
