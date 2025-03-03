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
use \Peneus\Api\Handlers\IHandler;
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

    function testDispatchRequestWithMissingHandlerParameter()
    {
        $queryParams = $this->createMock(CArray::class);
        $request = Request::Instance();
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $queryParams->expects($this->once())
            ->method('Get')
            ->with('handler')
            ->willReturn(null);
        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::BadRequest)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"Handler not specified."}')
            ->willReturn($response);

        AccessHelper::SetProperty($dispatcher, 'response', $response);
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithMissingActionParameter()
    {
        $queryParams = $this->createMock(CArray::class);
        $request = Request::Instance();
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match($key) {
                    'handler' => 'handler1',
                    'action' => null,
                    default => null,
                };
            });
        $request->expects($this->exactly(2))
            ->method('QueryParams')
            ->willReturn($queryParams);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::BadRequest)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"Action not specified."}')
            ->willReturn($response);

        AccessHelper::SetProperty($dispatcher, 'response', $response);
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithHandlerNotFound()
    {
        $queryParams = $this->createMock(CArray::class);
        $request = Request::Instance();
        $handlerRegistry = HandlerRegistry::Instance();
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match($key) {
                    'handler' => 'handler1',
                    'action' => 'action1',
                    default => null,
                };
            });
        $request->expects($this->exactly(2))
            ->method('QueryParams')
            ->willReturn($queryParams);
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn(null);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::NotFound)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"Handler not found: handler1"}')
            ->willReturn($response);

        AccessHelper::SetProperty($dispatcher, 'response', $response);
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionReturningNull()
    {
        $queryParams = $this->createMock(CArray::class);
        $request = Request::Instance();
        $handler = $this->createMock(IHandler::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match($key) {
                    'handler' => 'handler1',
                    'action' => 'action1',
                    default => null,
                };
            });
        $request->expects($this->exactly(2))
            ->method('QueryParams')
            ->willReturn($queryParams);
        $handler->expects($this->once())
            ->method('HandleAction')
            ->with('action1')
            ->willReturn(null);
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn($handler);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::NoContent)
            ->willReturn($response);

        AccessHelper::SetProperty($dispatcher, 'response', $response);
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionReturningResponseObject()
    {
        $queryParams = $this->createMock(CArray::class);
        $request = Request::Instance();
        $handler = $this->createMock(IHandler::class);
        $resultResponse = $this->createMock(Response::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $dispatcher = new Dispatcher();

        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match($key) {
                    'handler' => 'handler1',
                    'action' => 'action1',
                    default => null,
                };
            });
        $request->expects($this->exactly(2))
            ->method('QueryParams')
            ->willReturn($queryParams);
        $handler->expects($this->once())
            ->method('HandleAction')
            ->with('action1')
            ->willReturn($resultResponse);
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn($handler);

        $dispatcher->DispatchRequest();

        $this->assertSame($resultResponse,
            AccessHelper::GetProperty($dispatcher, 'response'));
    }

    function testDispatchRequestWithHandleActionReturningOtherResult()
    {
        $queryParams = $this->createMock(CArray::class);
        $request = Request::Instance();
        $handler = $this->createMock(IHandler::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match($key) {
                    'handler' => 'handler1',
                    'action' => 'action1',
                    default => null,
                };
            });
        $request->expects($this->exactly(2))
            ->method('QueryParams')
            ->willReturn($queryParams);
        $handler->expects($this->once())
            ->method('HandleAction')
            ->with('action1')
            ->willReturn(['question' => 'What is the meaning of life?',
                          'answer' => 42]);
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn($handler);
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"question":"What is the meaning of life?","answer":42}')
            ->willReturn($response);

        AccessHelper::SetProperty($dispatcher, 'response', $response);
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionThrowingExceptionWithCodeNotSet()
    {
        $queryParams = $this->createMock(CArray::class);
        $request = Request::Instance();
        $handler = $this->createMock(IHandler::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match($key) {
                    'handler' => 'handler1',
                    'action' => 'action1',
                    default => null,
                };
            });
        $request->expects($this->exactly(2))
            ->method('QueryParams')
            ->willReturn($queryParams);
        $handler->expects($this->once())
            ->method('HandleAction')
            ->with('action1')
            ->willThrowException(new \Exception('Sample error message.'));
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn($handler);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::InternalServerError)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"Sample error message."}')
            ->willReturn($response);

        AccessHelper::SetProperty($dispatcher, 'response', $response);
        $dispatcher->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionThrowingExceptionWithCodeSet()
    {
        $queryParams = $this->createMock(CArray::class);
        $request = Request::Instance();
        $handler = $this->createMock(IHandler::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $response = $this->createMock(Response::class);
        $dispatcher = new Dispatcher();

        $queryParams->expects($this->exactly(2))
            ->method('Get')
            ->willReturnCallback(function($key) {
                return match($key) {
                    'handler' => 'handler1',
                    'action' => 'action1',
                    default => null,
                };
            });
        $request->expects($this->exactly(2))
            ->method('QueryParams')
            ->willReturn($queryParams);
        $handler->expects($this->once())
            ->method('HandleAction')
            ->with('action1')
            ->willThrowException(new \Exception('File is too large.', 413));
        $handlerRegistry->expects($this->once())
            ->method('FindHandler')
            ->with('handler1')
            ->willReturn($handler);
        $response->expects($this->once())
            ->method('SetStatusCode')
            ->with(StatusCode::PayloadTooLarge)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"File is too large."}')
            ->willReturn($response);

        AccessHelper::SetProperty($dispatcher, 'response', $response);
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

        AccessHelper::SetProperty($dispatcher, 'response', $response);
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
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"E_NOTICE: Something went wrong in \'file.php\' on line 123."}')
            ->willReturn($response);
        $response->expects($this->once())
            ->method('Send');

        AccessHelper::SetProperty($dispatcher, 'response', $response);
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
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response);
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"error":"An unexpected error occurred."}')
            ->willReturn($response);
        $response->expects($this->once())
            ->method('Send');

        AccessHelper::SetProperty($dispatcher, 'response', $response);
        $dispatcher->OnShutdown("E_NOTICE: Something went wrong in 'file.php' on line 123.");
    }

    #endregion OnShutdown
}
