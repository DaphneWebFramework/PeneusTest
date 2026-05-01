<?php declare(strict_types=1);
namespace suite\Api\Actions\Account;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Account\DeleteAction;

use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Api\Hooks\IAccountDeletionHook;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

#[CoversClass(DeleteAction::class)]
class DeleteActionTest extends TestCase
{
    private ?Database $originalDatabase = null;
    private ?AccountService $originalAccountService = null;

    protected function setUp(): void
    {
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
    }

    protected function tearDown(): void
    {
        Database::ReplaceInstance($this->originalDatabase);
        AccountService::ReplaceInstance($this->originalAccountService);
    }

    private function systemUnderTest(string ...$mockedMethods): DeleteAction
    {
        return $this->getMockBuilder(DeleteAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    private function contextForOnExecute(
        bool $ensureLoggedInSucceeds = true,
        bool $findAccountSucceeds = true,
        bool $doTransactionSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'ensureLoggedIn',
            'findAccount',
            'doTransaction'
        );
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 17;
        $account = $this->createStub(Account::class);

        $ctx->sut->expects($ctx->chain())
            ->method('ensureLoggedIn')
            ->willReturnCallback(fn() => $ensureLoggedInSucceeds
                ? $accountView
                : throw new \RuntimeException('ENSURE_LOGGED_IN_FAILED'));
        $ctx->sut->expects($ctx->chainIf($ensureLoggedInSucceeds))
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturnCallback(fn() => $findAccountSucceeds
                ? $account
                : throw new \RuntimeException('FIND_ACCOUNT_FAILED'));
        $ctx->sut->expects($ctx->chainIf($findAccountSucceeds))
            ->method('doTransaction')
            ->with($account)
            ->willReturnCallback(fn() => $doTransactionSucceeds
                ? null
                : throw new \RuntimeException('DO_TRANSACTION_FAILED'));

        return $ctx;
    }

    function testOnExecuteFailsIfEnsureLoggedInFails()
    {
        $ctx = $this->contextForOnExecute(ensureLoggedInSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ENSURE_LOGGED_IN_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfFindAccountFails()
    {
        $ctx = $this->contextForOnExecute(findAccountSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FIND_ACCOUNT_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfDoTransactionFails()
    {
        $ctx = $this->contextForOnExecute(doTransactionSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DO_TRANSACTION_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $ctx = $this->contextForOnExecute();
        $actual = ah::CallMethod($ctx->sut, 'onExecute');
        $this->assertNull($actual);
    }

    #endregion onExecute

    #region doTransaction ------------------------------------------------------

    private function contextForDoTransaction(
        bool $triggerDeletionHooksSucceeds = true,
        bool $accountDeleteSucceeds = true,
        bool $sessionDeleteSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest('triggerDeletionHooks');
        $ctx->account = $this->createMock(Account::class);
        $database = Database::Instance();
        $accountService = AccountService::Instance();

        $database->expects($ctx->chain())
             ->method('WithTransaction')
             ->willReturnCallback(fn($callback) => $callback());
        $ctx->sut->expects($ctx->chain())
            ->method('triggerDeletionHooks')
            ->with($ctx->account)
            ->willReturnCallback(fn() => $triggerDeletionHooksSucceeds
                ? null
                : throw new \RuntimeException('TRIGGER_DELETION_HOOKS_FAILED'));
        $ctx->account->expects($ctx->chainIf($triggerDeletionHooksSucceeds))
            ->method('Delete')
            ->willReturn($accountDeleteSucceeds);
        $accountService->expects($ctx->chainIf($accountDeleteSucceeds))
            ->method('DeleteSession')
            ->willReturnCallback(fn() => $sessionDeleteSucceeds
                ? null
                : throw new \RuntimeException('SESSION_DELETE_FAILED'));

        return $ctx;
    }

    function testDoTransactionFailsIfTriggerDeletionHooksFails()
    {
        $ctx = $this->contextForDoTransaction(triggerDeletionHooksSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TRIGGER_DELETION_HOOKS_FAILED');
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->account]);
    }

    function testDoTransactionFailsIfAccountDeleteFails()
    {
        $ctx = $this->contextForDoTransaction(accountDeleteSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete account.");
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->account]);
    }

    function testDoTransactionFailsIfSessionDeleteFails()
    {
        $ctx = $this->contextForDoTransaction(sessionDeleteSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SESSION_DELETE_FAILED');
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->account]);
    }

    function testDoTransactionSucceeds()
    {
        $ctx = $this->contextForDoTransaction();
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->account]);
    }

    #endregion doTransaction

    #region triggerDeletionHooks -----------------------------------------------

    private function contextForTriggerDeletionHooks(
        bool $firstHookSucceeds = true,
        bool $secondHookSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest();
        $ctx->account = $this->createStub(Account::class);
        $accountService = AccountService::Instance();
        $hooks = [
            $this->createMock(IAccountDeletionHook::class),
            $this->createMock(IAccountDeletionHook::class)
        ];

        $accountService->expects($ctx->chain())
            ->method('DeletionHooks')
            ->willReturn($hooks);
        $hooks[0]->expects($ctx->chain())
            ->method('OnDeleteAccount')
            ->with($ctx->account)
            ->willReturnCallback(fn() => $firstHookSucceeds
                ? null
                : throw new \RuntimeException('FIRST_HOOK_FAILED'));
        $hooks[1]->expects($ctx->chainIf($firstHookSucceeds))
            ->method('OnDeleteAccount')
            ->with($ctx->account)
            ->willReturnCallback(fn() => $secondHookSucceeds
                ? null
                : throw new \RuntimeException('SECOND_HOOK_FAILED'));

        return $ctx;
    }

    function testTriggerDeletionHooksFailsIfFirstHookFails()
    {
        $ctx = $this->contextForTriggerDeletionHooks(firstHookSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FIRST_HOOK_FAILED');
        ah::CallMethod($ctx->sut, 'triggerDeletionHooks', [$ctx->account]);
    }

    function testTriggerDeletionHooksFailsIfSecondHookFails()
    {
        $ctx = $this->contextForTriggerDeletionHooks(secondHookSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SECOND_HOOK_FAILED');
        ah::CallMethod($ctx->sut, 'triggerDeletionHooks', [$ctx->account]);
    }

    function testTriggerDeletionHooksSucceeds()
    {
        $ctx = $this->contextForTriggerDeletionHooks();
        ah::CallMethod($ctx->sut, 'triggerDeletionHooks', [$ctx->account]);
    }

    #endregion triggerDeletionHooks
}
