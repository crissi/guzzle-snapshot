<?php

declare(strict_types=1);

namespace EmanueleMinotto\GuzzleSnapshot;

use EmanueleMinotto\GuzzleSnapshot\Strategy\ReadableFileStrategy;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \EmanueleMinotto\GuzzleSnapshot\SnapshotMiddleware
 */
class SnapshotMiddlewareTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    private $fileSystem;

    /**
     * @var SnapshotMiddleware
     */
    private $middleware;

    /**
     * @var HandlerStack
     */
    private $stack;

    /**
     * @var Client
     */
    private $client;

    protected function setUp(): void
    {
        $this->fileSystem = vfsStream::setup();

        $strategy = new ReadableFileStrategy($this->fileSystem->url());
        $this->middleware = new SnapshotMiddleware($strategy);

        $this->stack = new HandlerStack();
        $this->stack->setHandler(new CurlHandler());
        $this->stack->push($this->middleware);

        $this->client = new Client([
            'handler' => $this->stack,
        ]);
    }

    public function testExecutionWithRequestStorage()
    {
        $this->assertFalse($this->fileSystem->hasChildren());

        $this->middleware->setRequestStorage(true);
        $this->client->get('https://httpbin.org/get');
        $this->assertTrue($this->fileSystem->hasChildren());
        $this->assertCount(2, $this->fileSystem->getChildren());
    }

    public function testExecution()
    {
        $this->assertFalse($this->fileSystem->hasChildren());

        $this->client->get('https://httpbin.org/get');
        $this->assertTrue($this->fileSystem->hasChildren());
        $this->assertCount(1, $this->fileSystem->getChildren());

        $handler = $this->createMock(CurlHandler::class);
        $handler
            ->expects($this->never())
            ->method('__invoke')
        ;

        $this->stack->setHandler($handler);

        $this->client->get('https://httpbin.org/get');

        $this->assertCount(1, $this->fileSystem->getChildren());
    }

    public function testWithException()
    {
        try {
            $this->expectException(RequestException::class);
            $this->client->get('test://www.example.net/');
        } finally {
            $this->assertFalse($this->fileSystem->hasChildren());
        }
    }
}
