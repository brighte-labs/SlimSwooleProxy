<?php

declare(strict_types = 1);

namespace Tests;

use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;
use SwooleBridge\Bridge\RequestTransformer;
use SwooleBridge\SwooleProxy;

abstract class BaseSwooleTestCase extends \Tests\BaseFunctionalTestCase
{

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->requestTransformer = new RequestTransformer;
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
        unset($this->requestTransformer);
        parent::tearDown();
    }


    protected function createApplication(): void
    {
        parent::createApplication();
        $this->app = new SwooleProxy($this->app);
    }

    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri    the request URI
     * @param string[]|int[]|object|null $requestData   the request data
     *
     * @param String[] $headers
     * @return \Psr\Http\Message\ResponseInterface|\Slim\Http\Response
     */
    public function runApp(string $requestMethod, string $requestUri, $requestData = null, array $headers = [])
    {
        // Create a mock environment for testing with
        $environment = Environment::mock(
            array_merge(
                [
                    'REQUEST_METHOD' => $requestMethod,
                    'REQUEST_URI' => getenv('URL_PREFIX') . $requestUri,
                    'Content_Type' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
                $headers,
            ),
        );

        // Set up a request object based on the environment
        $request = Request::createFromEnvironment($environment);

        // Add request data, if it exists
        if (isset($requestData)) {
            $request = $request->withParsedBody($requestData);
        }

        // Set up a response object
        $response = new Response;

        // Process the application and Return the response
        return $this->app->process($request, $response);
    }

}
