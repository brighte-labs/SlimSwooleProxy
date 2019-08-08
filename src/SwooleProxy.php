<?php

declare(strict_types = 1);

namespace SwooleProxy;

use Slim\App;
use Slim\Http\Response;
use Swoole\Http\Request;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server;
use Swoole\Process;
use Swoole\Runtime;
use SwooleProxy\Bridge\RequestTransformer;
use SwooleProxy\Bridge\RequestTransformerInterface;
use SwooleProxy\Bridge\ResponseMerger;
use SwooleProxy\Bridge\ResponseMergerInterface;

class SwooleProxy implements \SwooleProxy\ISwooleProxy
{

    /** @var \Slim\App */
    private $app;

    /** @var \SwooleProxy\Bridge\RequestTransformerInterface */
    private $requestTransformer;

    /** @var \SwooleBridge\Bridge\ResponseMergerInterface */
    private $responseMerger;

    /**
     * Require files
     *
     * @var string[]
     */
    private $requiredFiles = [];

    /**
     * inotify watched files
     *
     * @var string
     */
    private $sourceDir = '';

    /**
     * enable hot reload
     *
     * @var bool
     */
    private $enableHotReload = false;

    /**
     * Listener in the separate process
     *
     * @var null
     */
    private $listener = null;

    public function __construct(
        App $app,
        ?RequestTransformerInterface $requestTransformer = null,
        ?ResponseMergerInterface $responseMerger = null
    )
	{
        $this->app = $app;
        $this->requestTransformer = $requestTransformer ?: new RequestTransformer;
        $this->responseMerger = $responseMerger ?: new ResponseMerger($this->app);
    }


    /**
     * Processing request by swoole
     *
     * @param \Swoole\Http\Request $swooleRequest Http request via swoole
     * @param \Swoole\Http\Response $swooleResponse Http response via swoole
     * @return \Swoole\Http\Response Http response after process via Slim framework
     */
    public function processing(
        Request $swooleRequest,
        SwooleResponse $swooleResponse
    ): SwooleResponse
	{
        $slimRequest = $this->requestTransformer->toSlim($swooleRequest);

        $slimResponse = $this->app->process($slimRequest, new Response);

        return $this->responseMerger->mergeToSwoole($slimResponse, $swooleResponse);
    }

    /**
     * Magic function to call all App/Base/App function
     *
     * @param  string $name Function name
     * @param  string|int[] $args Arguments in array
     * @return string|int|bool Return from the function
     */
    public function __call(string $name, array $args)
    {
        return call_user_func_array([$this->app, $name], $args);
    }

    /**
     * Add required files to watch
     *
     * @param string[] $fileNames The file path needs to be required
     * @return  void
     */
    public function setRequiredFiles(array $fileNames = []): void
    {
        if (count($fileNames) <= 0) {
            return;
        }

        $this->requiredFiles = array_merge($this->requiredFiles, $fileNames);
    }

    /**
     * Set source directory to watch
     *
     * @param string $sourceDir Source code directory to watch
     * @return void
     */
    public function setSourceDir(string $sourceDir = ''): void
    {
        if (strlen($sourceDir) <= 0) {
            return;
        }

        $this->sourceDir = $sourceDir;
    }

    /**
     * Setter for enableHotrealod
     *
     * @param bool $enable [description]
     */
    public function setEnableHotReload(bool $enable = true): void
    {
        $this->enableHotReload = $enable;
    }

    public function run(): void
    {
        // Witch the PHP build-in stream, sleep, pdo, mysqli, redis
        // from blocking model to be async model with Swoole Coroutine.
        // See https://www.swoole.co.uk/article/swoole-coroutine
        Runtime::enableCoroutine(true);

        // Start the Swoole server
        $http = new Server('0.0.0.0', 80);

        if ($this->listener !== null) {
            $process = new Process(function ($process): void {
                $this->listener->listening();
            }, false, 0);

            $http->addProcess($process);
        }

        $http->set([
            'dispatch_mode' => 3,
        ]);

        // Register the on 'start' event
        $http->on('start', function (Server $server): void {
            echo sprintf('Swoole http server is started at http://%s:%s', $server->host, $server->port), PHP_EOL;

            // watch php file update for development
            if ($this->enableHotReload === false) {
                return;
            }

            $kit = new AutoReload($server->master_pid);
            $kit->watch($this->sourceDir);
            $kit->run();
        });

        // Register the on 'WorkerStart' event
        $http->on('WorkerStart', function (Server $server, $workerId): void {
            // phpcs:ignore
            $app = $this->app;

            if (count($this->requiredFiles) <= 0) {
                return;
            }

            foreach ($this->requiredFiles as $file) {
                require_once $file;
            }
        });

        // Register 'request' event, convert swoole request to slim request
        $http->on(
            'request',
            function (Request $swooleRequest, SwooleResponse $swooleResponse) {
                if ($swooleRequest->server['path_info'] === '/favicon.ico'
                    || $swooleRequest->server['request_uri'] === '/favicon.ico') {
                    return $swooleResponse->end();
                }

                $this->processing($swooleRequest, $swooleResponse)->end();
            },
        );

        $http->start();
    }

}
