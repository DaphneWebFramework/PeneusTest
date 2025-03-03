<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Handlers\Handler;

use \Peneus\Api\Actions\Action;

#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private function createHandler(): Handler
    {
        return $this->getMockBuilder(Handler::class)
            ->onlyMethods(['createAction'])
            ->getMock();
    }

    #region HandleAction -------------------------------------------------------

    function testHandleActionWhenActionIsUnknown()
    {
        $handler = $this->createHandler();

        $handler->expects($this->once())
            ->method('createAction')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown action: unknown');
        $this->expectExceptionCode(404);
        $handler->HandleAction('unknown');
    }

    function testHandleActionWithCaseInsensitiveActionName()
    {
        $handler = $this->createHandler();
        $action = $this->createMock(Action::class);

        $handler->expects($this->once())
            ->method('createAction')
            ->with('test')
            ->willReturn($action);
        $action->expects($this->once())
            ->method('Execute')
            ->willReturn(42);

        $this->assertSame(42, $handler->HandleAction('TEST'));
    }

    function testHandleActionWithActionNameWithSurroundingWhitespace()
    {
        $handler = $this->createHandler();
        $action = $this->createMock(Action::class);

        $handler->expects($this->once())
            ->method('createAction')
            ->with('test')
            ->willReturn($action);
        $action->expects($this->once())
            ->method('Execute')
            ->willReturn(42);

        $this->assertSame(42, $handler->HandleAction('  test  '));
    }

    #endregion HandleAction
}
