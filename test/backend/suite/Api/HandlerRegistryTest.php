<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\HandlerRegistry;

use \Peneus\Api\Handlers\IHandler;

class NotAnIHandler {
}
class DummyHandler implements IHandler {
    public function HandleAction(string $actionName): mixed {}
}

#[CoversClass(HandlerRegistry::class)]
class HandlerRegistryTest extends TestCase
{
    private ?HandlerRegistry $originalHandlerRegistry = null;

    protected function setUp(): void
    {
        $this->originalHandlerRegistry = HandlerRegistry::ReplaceInstance(null);
    }

    protected function tearDown(): void
    {
        HandlerRegistry::ReplaceInstance($this->originalHandlerRegistry);
    }

    #region RegisterHandler ----------------------------------------------------

    function testRegisterHandlerWithEmptyHandlerName()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Handler name cannot be empty.');
        $handlerRegistry->RegisterHandler('', DummyHandler::class);
    }

    function testRegisterHandlerWithWhitespaceOnlyHandlerName()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Handler name cannot be empty.');
        $handlerRegistry->RegisterHandler('   ', DummyHandler::class);
    }

    function testRegisterHandlerWithAlreadyRegisteredHandler()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $handlerRegistry->RegisterHandler('test', DummyHandler::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Handler already registered: test');
        $handlerRegistry->RegisterHandler('test', DummyHandler::class);
    }

    function testRegisterHandlerWithSameNameDifferentClass()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $handlerRegistry->RegisterHandler('test', DummyHandler::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Handler already registered: test');
        $handlerRegistry->RegisterHandler('test', NotAnIHandler::class);
    }

    function testRegisterHandlerWithCaseInsensitiveHandlerName()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $handlerRegistry->RegisterHandler('test', DummyHandler::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Handler already registered: test');
        $handlerRegistry->RegisterHandler('TEST', DummyHandler::class);
    }

    function testRegisterHandlerWithNonExistingClass()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler class not found: NonExistingClass');
        $handlerRegistry->RegisterHandler('test', 'NonExistingClass');
    }

    function testRegisterHandlerWithExistingClassButDoesNotImplementIHandler()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Handler class must implement IHandler interface: NotAnIHandler');
        $handlerRegistry->RegisterHandler('test', NotAnIHandler::class);
    }

    #endregion RegisterHandler

    #region FindHandler --------------------------------------------------------

    function testFindHandlerWhenRegistryIsEmpty()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $this->assertNull($handlerRegistry->FindHandler('test'));
    }

    function testFindHandlerWithNonExistingHandler()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $this->assertNull($handlerRegistry->FindHandler('test'));
    }

    function testFindHandlerWithExistingHandler()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $handlerRegistry->RegisterHandler('test', DummyHandler::class);
        $handler = $handlerRegistry->FindHandler('test');
        $this->assertInstanceOf(DummyHandler::class, $handler);
    }

    function testFindHandlerWithCaseInsensitiveHandlerName()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $handlerRegistry->RegisterHandler('test', DummyHandler::class);
        $handler = $handlerRegistry->FindHandler('TEST');
        $this->assertInstanceOf(DummyHandler::class, $handler);
    }

    function testFindHandlerWithHandlerNameWithSurroundingWhitespace()
    {
        $handlerRegistry = HandlerRegistry::Instance();
        $handlerRegistry->RegisterHandler('test', DummyHandler::class);
        $handler = $handlerRegistry->FindHandler('  test  ');
        $this->assertInstanceOf(DummyHandler::class, $handler);
    }

    #endregion FindHandler
}
