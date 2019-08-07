<?php

declare(strict_types = 1);

namespace SwooleProxy\Bridge;

use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Swoole\Http\Response;

/**
 * @codeCoverageIgnore
 */
class ResponseMerger implements \SwooleProxy\Bridge\IResponseMerger
{

    /** @var \Slim\App */
    private $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function mergeToSwoole(
        ResponseInterface $response,
        Response $swooleResponse
    ): Response
	{
        $container = $this->app->getContainer();

        $settings = $container->get('settings');

        if (isset($settings['addContentLengthHeader']) && $settings['addContentLengthHeader'] === true) {
            $size = $response->getBody()->getSize();

            if ($size !== null) {
                $swooleResponse->header('Content-Length', (string) $size);
            }
        }

        if (count($response->getHeaders()) > 0) {
            foreach ($response->getHeaders() as $key => $headerArray) {
                $swooleResponse->header($key, implode('; ', $headerArray));
            }
        }

        $swooleResponse->status($response->getStatusCode());

        if ($response->getBody()->getSize() > 0) {
            if ($response->getBody()->isSeekable()) {
                $response->getBody()->rewind();
            }

            $swooleResponse->write($response->getBody()->getContents());
        }

        return $swooleResponse;
    }

}
