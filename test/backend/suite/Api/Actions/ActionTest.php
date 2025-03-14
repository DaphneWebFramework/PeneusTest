<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Action;

use \Peneus\Api\Guards\IGuard;
use \Peneus\Translation;

class DummyAction extends Action {
    protected function onExecute(): mixed { return 42; }
}

#[CoversClass(Action::class)]
class ActionTest extends TestCase
{
    private ?Translation $originalTranslation = null;

    protected function setUp(): void
    {
        $this->originalTranslation =
            Translation::ReplaceInstance($this->createMock(Translation::class));
    }

    protected function tearDown(): void
    {
        Translation::ReplaceInstance($this->originalTranslation);
    }

    #region Execute ------------------------------------------------------------

    function testExecuteWithoutGuards()
    {
        $action = new DummyAction();
        $this->assertSame(42, $action->Execute());
    }

    function testExecuteWithGuardsWhenFirstGuardDoesNotVerify()
    {
        $action = new DummyAction();
        $guard1 = $this->createMock(IGuard::class);
        $guard2 = $this->createMock(IGuard::class);
        $translation = Translation::Instance();

        $guard1->expects($this->once())
            ->method('Verify')
            ->willReturn(false);
        $guard2->expects($this->never())
            ->method('Verify');
        $action->AddGuard($guard1)
               ->AddGuard($guard2);
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_no_permission_for_action')
            ->willReturn('You do not have permission to perform this action.');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'You do not have permission to perform this action.');
        $this->expectExceptionCode(401);
        $action->Execute();
    }

    function testExecuteWithGuardsWhenSecondGuardDoesNotVerify()
    {
        $guard1 = $this->createMock(IGuard::class);
        $guard2 = $this->createMock(IGuard::class);
        $action = new DummyAction();
        $translation = Translation::Instance();

        $guard1->expects($this->once())
            ->method('Verify')
            ->willReturn(true);
        $guard2->expects($this->once())
            ->method('Verify')
            ->willReturn(false);
        $action->AddGuard($guard1)
               ->AddGuard($guard2);
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_no_permission_for_action')
            ->willReturn('You do not have permission to perform this action.');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'You do not have permission to perform this action.');
        $this->expectExceptionCode(401);
        $action->Execute();
    }

    function testExecuteWithGuardsWhenAllGuardsVerify()
    {
        $guard1 = $this->createMock(IGuard::class);
        $guard2 = $this->createMock(IGuard::class);
        $action = new DummyAction();

        $guard1->expects($this->once())
            ->method('Verify')
            ->willReturn(true);
        $guard2->expects($this->once())
            ->method('Verify')
            ->willReturn(true);
        $action->AddGuard($guard1)
               ->AddGuard($guard2);

        $this->assertSame(42, $action->Execute());
    }

    #endregion Execute
}
