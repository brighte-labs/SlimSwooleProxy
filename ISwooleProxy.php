<?php

declare(strict_types = 1);

namespace SwooleBridge;

use Swoole\Http\Request;
use Swoole\Http\Response;

interface ISwooleProxy
{

    public function processing(
        Request $swooleRequest,
        Response $swooleResponse
    ): Response;

    public function run(): void;

}
