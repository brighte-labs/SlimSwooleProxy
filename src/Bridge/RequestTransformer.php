<?php

declare(strict_types = 1);

namespace SwooleProxy\Bridge;

use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\UploadedFile;
use Swoole\Http\Request as SwooleRequest;

/**
 * @codeCoverageIgnore
 */
class RequestTransformer implements \SwooleProxy\Bridge\IRequestTransformer
{

    private const DEFAULT_SCHEMA = 'http';

    /**
     * @param \Swoole\Http\Request $request
     * @return \Slim\Http\Request
     * @todo Handle HTTPS requests
     */
    public function toSlim(SwooleRequest $request): Request
    {
        $slimRequest = Request::createFromEnvironment(
            new Environment([
                'SERVER_PROTOCOL' => $request->server['server_protocol'],
                'REQUEST_METHOD' => $request->server['request_method'],
                'REQUEST_SCHEME' => self::DEFAULT_SCHEMA,
                'REQUEST_URI' => $request->server['request_uri'],
                'QUERY_STRING' => $request->server['query_string'] ?? '',
                'SERVER_PORT' => $request->server['server_port'],
                'REMOTE_ADDR' => $request->server['remote_addr'],
                'REQUEST_TIME' => $request->server['request_time'],
                'REQUEST_TIME_FLOAT' => $request->server['request_time_float'],
            ])
        );

        $slimRequest = $this->copyHeaders($request, $slimRequest);

        if ($this->isMultiPartFormData($request) || $this->isXWwwFormUrlEncoded($request)) {
            $slimRequest = $this->handlePostData($request, $slimRequest);
        }

        if ($this->isMultiPartFormData($request)) {
            $slimRequest = $this->handleUploadedFiles($request, $slimRequest);
        }

        return $this->copyBody($request, $slimRequest);
    }

    private function copyBody(SwooleRequest $request, Request $slimRequest): Request
    {
        if (strlen($request->rawContent()) === 0) {
            return $slimRequest;
        }

        $body = $slimRequest->getBody();
        $body->write($request->rawContent());
        $body->rewind();

        return $slimRequest->withBody($body);
    }

    /**
     * Copy headers from swoole request to Slim request
     *
     * @param  \Swoole\Http\Request $request Swoole http request
     * @param  \Slim\Http\Request $slimRequest Slim request
     * @return \Slim\Http\Request Slim request
     */
    private function copyHeaders(SwooleRequest $request, Request $slimRequest): Request
    {
        foreach ($request->header as $key => $val) {
            $slimRequest = $slimRequest->withHeader($key, $val);
        }

        return $slimRequest;
    }

    private function isMultiPartFormData(SwooleRequest $request): bool
    {
        return isset($request->header['content-type'])
            && stripos($request->header['content-type'], 'multipart/form-data') !== false;
    }

    private function isXWwwFormUrlEncoded(SwooleRequest $request): bool
    {
        return isset($request->header['content-type'])
            && stripos($request->header['content-type'], 'application/x-www-form-urlencoded') !== false;
    }


    private function handleUploadedFiles(SwooleRequest $request, Request $slimRequest): Request
    {
        if (!is_array($request->files) || count($request->files) === 0) {
            return $slimRequest;
        }

        $uploadedFiles = [];

        foreach ($request->files as $key => $file) {
            $uploadedFiles[$key] = new UploadedFile(
                $file['tmp_name'],
                $file['name'],
                $file['type'],
                $file['size'],
                $file['error']
            );
        }

        return $slimRequest->withUploadedFiles($uploadedFiles);
    }

    private function handlePostData(SwooleRequest $swooleRequest, Request $slimRequest): Request
    {
        if (!is_array($swooleRequest->post) || count($swooleRequest->post) === 0) {
            return $slimRequest;
        }

        return $slimRequest->withParsedBody($swooleRequest->post);
    }

}
