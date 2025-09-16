<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Action;

use \Peneus\Api\Guards\IGuard;

class DummyAction extends Action {
    protected function onExecute(): mixed { return 42; }
}

#[CoversClass(Action::class)]
class ActionTest extends TestCase
{
    #region Execute ------------------------------------------------------------

    function testExecuteWithoutGuards()
    {
        $sut = new DummyAction();
        $this->assertSame(42, $sut->Execute());
    }

    function testExecuteWithGuardsWhenFirstGuardDoesNotVerify()
    {
        $sut = new DummyAction();
        $guard1 = $this->createMock(IGuard::class);
        $guard2 = $this->createMock(IGuard::class);

        $guard1->expects($this->once())
            ->method('Verify')
            ->willReturn(false);
        $guard2->expects($this->never())
            ->method('Verify');
        $sut->AddGuard($guard1)
            ->AddGuard($guard2);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'You do not have permission to perform this action.');
        $this->expectExceptionCode(401);
        $sut->Execute();
    }

    function testExecuteWithGuardsWhenSecondGuardDoesNotVerify()
    {
        $guard1 = $this->createMock(IGuard::class);
        $guard2 = $this->createMock(IGuard::class);
        $sut = new DummyAction();

        $guard1->expects($this->once())
            ->method('Verify')
            ->willReturn(true);
        $guard2->expects($this->once())
            ->method('Verify')
            ->willReturn(false);
        $sut->AddGuard($guard1)
            ->AddGuard($guard2);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'You do not have permission to perform this action.');
        $this->expectExceptionCode(401);
        $sut->Execute();
    }

    function testExecuteWithGuardsWhenAllGuardsVerify()
    {
        $guard1 = $this->createMock(IGuard::class);
        $guard2 = $this->createMock(IGuard::class);
        $sut = new DummyAction();

        $guard1->expects($this->once())
            ->method('Verify')
            ->willReturn(true);
        $guard2->expects($this->once())
            ->method('Verify')
            ->willReturn(true);
        $sut->AddGuard($guard1)
            ->AddGuard($guard2);

        $this->assertSame(42, $sut->Execute());
    }

    #endregion Execute
}
