<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Dispatcher;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\Response;
use \Harmonia\Http\StatusCode;
use \Harmonia\Shutdown\ShutdownHandler;
use \Peneus\Api\HandlerRegistry;
use \Peneus\Api\Handlers\Handler;
use \TestToolkit\AccessHelper;

#[CoversClass(Dispatcher::class)]
class DispatcherTest extends TestCase
{
    private ?ShutdownHandler $originalShutdownHandler = null;
    private ?Request $originalRequest = null;
    private ?HandlerRegistry $originalHandlerRegistry = null;
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalShutdownHandler = ShutdownHandler::ReplaceInstance(
            $this->createMock(ShutdownHandler::class));
        $this->originalRequest = Request::ReplaceInstance(
            $this->createMock(Request::class));
        $this->originalHandlerRegistry = HandlerRegistry::ReplaceInstance(
            $this->createMock(HandlerRegistry::class));
        $this->originalConfig = Config::ReplaceInstance(
            $this->createMock(Config::class));
    }

    protected function tearDown(): void
    {
        ShutdownHandler::ReplaceInstance($this->originalShutdownHandler);
        Request::ReplaceInstance($this->originalRequest);
        HandlerRegistry::ReplaceInstance($this->originalHandlerRegistry);
        Config::ReplaceInstance($this->originalConfig);
    }

    #region DispatchRequest ----------------------------------------------------

    function testDispatchRequestWithMissingHandlerQueryParameter()
    {
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('Get')
            ->with('handler')
            ->willReturn(null);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::BadRequest)
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"Handler not specified."}');

        AccessHelper::SetProperty($dispatcher, 'response', $response); // Inject
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithMissingActionQueryParameter()
    {
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match ($key) {
                    'handler' => 'handler1',
                    'action' => null
                };
            });
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::BadRequest)
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"Action not specified."}');

        AccessHelper::SetProperty($dispatcher, 'response', $response); // Inject
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithHandlerNotFound()
    {
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match ($key) {
                    'handler' => 'handler1',
                    'action' => 'action1'
                };
            });
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn(null);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::NotFound)
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"Handler not found: handler1"}');

        AccessHelper::SetProperty($dispatcher, 'response', $response); // Inject
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionReturnsNull()
    {
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $handler = $this->createMock(Handler::class);
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match ($key) {
                    'handler' => 'handler1',
                    'action' => 'action1'
                };
            });
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn($handler);
        $handler->expects($this->once())
            ->method('HandleAction')
            ->with('action1')
            ->willReturn(null);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::NoContent);

        AccessHelper::SetProperty($dispatcher, 'response', $response); // Inject
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionReturnsResponseObject()
    {
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $handler = $this->createMock(Handler::class);
        $resultResponse = $this->createStub(Response::class);
        $dispatcher = new Dispatcher();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match ($key) {
                    'handler' => 'handler1',
                    'action' => 'action1'
                };
            });
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn($handler);
        $handler->expects($this->once())
            ->method('HandleAction')
            ->with('action1')
            ->willReturn($resultResponse);

        $dispatcher->DispatchRequest();

        $this->assertSame(
            $resultResponse,
            AccessHelper::GetProperty($dispatcher, 'response')
        );
    }

    function testDispatchRequestWithHandleActionReturnsOtherResult()
    {
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $handler = $this->createMock(Handler::class);
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match ($key) {
                    'handler' => 'handler1',
                    'action' => 'action1'
                };
            });
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn($handler);
        $handler->expects($this->once())
            ->method('HandleAction')
            ->with('action1')
            ->willReturn([
                'question' => 'What is the meaning of life?',
                'answer' => 42
            ]);
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"question":"What is the meaning of life?","answer":42}');

        AccessHelper::SetProperty($dispatcher, 'response', $response); // Inject
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionThrowsWhenExceptionCodeNotSet()
    {
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $handler = $this->createMock(Handler::class);
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match ($key) {
                    'handler' => 'handler1',
                    'action' => 'action1'
                };
            });
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn($handler);
        $handler->expects($this->once())
            ->method('HandleAction')
            ->with('action1')
            ->willThrowException(new \Exception('Sample error message.'));
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::InternalServerError) // Default (500)
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"Sample error message."}');

        AccessHelper::SetProperty($dispatcher, 'response', $response); // Inject
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionThrowsWhenExceptionCodeSet()
    {
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $handler = $this->createMock(Handler::class);
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match ($key) {
                    'handler' => 'handler1',
                    'action' => 'action1'
                };
            });
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn($handler);
        $handler->expects($this->once())
            ->method('HandleAction')
            ->with('action1')
            ->willThrowException(new \Exception('File is too large.', 413));
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::PayloadTooLarge) // 413
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"File is too large."}');

        AccessHelper::SetProperty($dispatcher, 'response', $response); // Inject
        $dispatcher->DispatchRequest();
    }

    #endregion DispatchRequest

    #region OnShutdown ---------------------------------------------------------

    function testOnShutdownWithNoError()
    {
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $response->expects($this->once())
            ->method('Send');

        AccessHelper::SetProperty($dispatcher, 'response', $response); // Inject
        $dispatcher->OnShutdown(null);
    }

    function testOnShutdownWithErrorInDebugMode()
    {
        $config = Config::Instance();
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $config->expects($this->once())
            ->method('Option')
            ->with('IsDebug')
            ->willReturn(true);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::InternalServerError)
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"E_NOTICE: Something went wrong in \'file.php\' on line 123."}')
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('Send');

        AccessHelper::SetProperty($dispatcher, 'response', $response); // Inject
        $dispatcher->OnShutdown("E_NOTICE: Something went wrong in 'file.php' on line 123.");
    }

    function testOnShutdownWithErrorInLiveMode()
    {
        $config = Config::Instance();
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $config->expects($this->once())
            ->method('Option')
            ->with('IsDebug')
            ->willReturn(false);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::InternalServerError)
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"An unexpected error occurred."}')
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('Send');

        AccessHelper::SetProperty($dispatcher, 'response', $response); // Inject
        $dispatcher->OnShutdown("E_NOTICE: Something went wrong in 'file.php' on line 123.");
    }

    #endregion OnShutdown
}
