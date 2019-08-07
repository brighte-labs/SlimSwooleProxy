<?php

declare(strict_types = 1);

namespace Tests\SwooleBridge;

use Psr\Log\LoggerInterface;

class SwooleProxyTest extends \Tests\BaseSwooleTestCase
{

    /**
     * @runInSeparateProcess
     */
    public function testHealth(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->app->getContainer()->set('logger', $logger);
        $logger->expects(self::once())->method('info');
        $response = $this->runApp('GET', '/healthcheck');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Everything is OK!', (string) $response->getBody());
        $this->assertStringNotContainsString('error', (string) $response->getBody());
    }

    /**
     * @runInSeparateProcess
     */
    public function testDocs(): void
    {
        $response = $this->runApp('GET', '/specification');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Provides swagger docs', (string) $response->getBody());
        $this->assertStringNotContainsString('error', (string) $response->getBody());
    }

}
