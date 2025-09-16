<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Dispatcher;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\Response;
use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\ShutdownSystem\ShutdownHandler;
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
        $this->originalShutdownHandler =
            ShutdownHandler::ReplaceInstance($this->createMock(ShutdownHandler::class));
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalHandlerRegistry =
            HandlerRegistry::ReplaceInstance($this->createMock(HandlerRegistry::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
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
        $sut = new Dispatcher();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $response = $this->createMock(Response::class);

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
            ->with('{"message":"Handler not specified."}');

        AccessHelper::SetProperty($sut, 'response', $response); // Inject
        $sut->DispatchRequest();
    }

    function testDispatchRequestWithMissingActionQueryParameter()
    {
        $sut = new Dispatcher();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $response = $this->createMock(Response::class);

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
            ->with('{"message":"Action not specified."}');

        AccessHelper::SetProperty($sut, 'response', $response); // Inject
        $sut->DispatchRequest();
    }

    function testDispatchRequestWithHandlerNotFound()
    {
        $sut = new Dispatcher();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $response = $this->createMock(Response::class);

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
            ->with('{"message":"Handler not found: handler1"}');

        AccessHelper::SetProperty($sut, 'response', $response); // Inject
        $sut->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionReturnsNull()
    {
        $sut = new Dispatcher();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $handler = $this->createMock(Handler::class);
        $response = $this->createMock(Response::class);

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

        AccessHelper::SetProperty($sut, 'response', $response); // Inject
        $sut->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionReturnsResponseObject()
    {
        $sut = new Dispatcher();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $handler = $this->createMock(Handler::class);
        $resultResponse = $this->createStub(Response::class);

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

        $sut->DispatchRequest();

        $this->assertSame(
            $resultResponse,
            AccessHelper::GetProperty($sut, 'response')
        );
    }

    function testDispatchRequestWithHandleActionReturnsOtherResult()
    {
        $sut = new Dispatcher();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $handler = $this->createMock(Handler::class);
        $response = $this->createMock(Response::class);

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

        AccessHelper::SetProperty($sut, 'response', $response); // Inject
        $sut->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionThrowsWhenExceptionCodeNotSet()
    {
        $sut = new Dispatcher();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $handler = $this->createMock(Handler::class);
        $response = $this->createMock(Response::class);

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
            ->with(StatusCode::BadRequest) // Default (400)
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('SetBody')
            ->with('{"message":"Sample error message."}');

        AccessHelper::SetProperty($sut, 'response', $response); // Inject
        $sut->DispatchRequest();
    }

    function testDispatchRequestWithHandleActionThrowsWhenExceptionCodeSet()
    {
        $sut = new Dispatcher();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $handlerRegistry = HandlerRegistry::Instance();
        $handler = $this->createMock(Handler::class);
        $response = $this->createMock(Response::class);

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
            ->with('{"message":"File is too large."}');

        AccessHelper::SetProperty($sut, 'response', $response); // Inject
        $sut->DispatchRequest();
    }

    #endregion DispatchRequest

    #region OnShutdown ---------------------------------------------------------

    function testOnShutdownWithNoError()
    {
        $sut = new Dispatcher();
        $response = $this->createMock(Response::class);

        $response->expects($this->once())
            ->method('Send');

        AccessHelper::SetProperty($sut, 'response', $response); // Inject
        $sut->OnShutdown(null);
    }

    function testOnShutdownWithErrorInDebugMode()
    {
        $sut = new Dispatcher();
        $config = Config::Instance();
        $response = $this->createMock(Response::class);

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
            ->with('{"message":"E_NOTICE: Something went wrong in \'file.php\' on line 123."}')
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('Send');

        AccessHelper::SetProperty($sut, 'response', $response); // Inject
        $sut->OnShutdown("E_NOTICE: Something went wrong in 'file.php' on line 123.");
    }

    function testOnShutdownWithErrorInLiveMode()
    {
        $sut = new Dispatcher();
        $config = Config::Instance();
        $response = $this->createMock(Response::class);

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
            ->with('{"message":"An unexpected error occurred."}')
            ->willReturn($response); // Chain
        $response->expects($this->once())
            ->method('Send');

        AccessHelper::SetProperty($sut, 'response', $response); // Inject
        $sut->OnShutdown("E_NOTICE: Something went wrong in 'file.php' on line 123.");
    }

    #endregion OnShutdown
}
