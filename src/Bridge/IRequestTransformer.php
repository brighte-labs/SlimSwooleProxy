<?php

declare(strict_types = 1);

namespace SwooleProxy\Bridge;

use Slim\Http\Request;
use Swoole\Http\Request as SwooleRequest;

interface IRequestTransformer
{

    public function toSlim(SwooleRequest $request): Request;

}
