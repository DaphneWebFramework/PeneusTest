<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Action;

use \Peneus\Api\Guards\IGuard;

class DummyAction extends Action {
    protected function perform(): mixed { return 42; }
}

#[CoversClass(Action::class)]
class ActionTest extends TestCase
{
    function testRunWithoutGuards()
    {
        $action = new DummyAction();
        $this->assertSame(42, $action->Run());
    }

    function testRunWithGuardsWhenFirstGuardDoesNotAuthorize()
    {
        $guard1 = $this->createMock(IGuard::class);
        $guard1->expects($this->once())
            ->method('Authorize')
            ->willReturn(false);
        $guard2 = $this->createMock(IGuard::class);
        $guard2->expects($this->never())
            ->method('Authorize');
        $action = new DummyAction();
        $action->AddGuard($guard1)
               ->AddGuard($guard2);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You do not have permission to perform this action.');
        $this->expectExceptionCode(401);
        $action->Run();
    }

    function testRunWithGuardsWhenSecondGuardDoesNotAuthorize()
    {
        $guard1 = $this->createMock(IGuard::class);
        $guard1->expects($this->once())
            ->method('Authorize')
            ->willReturn(true);
        $guard2 = $this->createMock(IGuard::class);
        $guard2->expects($this->once())
            ->method('Authorize')
            ->willReturn(false);
        $action = new DummyAction();
        $action->AddGuard($guard1)
               ->AddGuard($guard2);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You do not have permission to perform this action.');
        $this->expectExceptionCode(401);
        $action->Run();
    }

    function testRunWithGuardsWhenAllGuardsAuthorize()
    {
        $guard1 = $this->createMock(IGuard::class);
        $guard1->expects($this->once())
            ->method('Authorize')
            ->willReturn(true);
        $guard2 = $this->createMock(IGuard::class);
        $guard2->expects($this->once())
            ->method('Authorize')
            ->willReturn(true);
        $action = new DummyAction();
        $action->AddGuard($guard1)
               ->AddGuard($guard2);
        $this->assertSame(42, $action->Run());
    }
}
