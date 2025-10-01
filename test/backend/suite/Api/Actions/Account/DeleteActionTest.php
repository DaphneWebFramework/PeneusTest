<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Account\DeleteAction;

use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Api\Hooks\IAccountDeletionHook;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as AH;

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

    function testOnExecuteThrowsIfUserIsNotLoggedIn()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn');

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoDeleteFails()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'doDelete');
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doDelete')
            ->with($account)
            ->willThrowException(new \RuntimeException());
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Account deletion failed.");
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'doDelete', 'logOut');
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doDelete')
            ->with($account);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });
        $sut->expects($this->once())
            ->method('logOut');

        $this->assertNull(AH::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute

    #region ensureLoggedIn -----------------------------------------------------

    function testEnsureLoggedInThrowsIfUserIsNotLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "You do not have permission to perform this action.");
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        AH::CallMethod($sut, 'ensureLoggedIn');
    }

    function testEnsureLoggedInSucceedsIfUserIsLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createStub(Account::class);
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);

        $this->assertSame($account, AH::CallMethod($sut, 'ensureLoggedIn'));
    }

    #endregion ensureLoggedIn

    #region doDelete -----------------------------------------------------------

    function testDoDeleteThrowsIfHookDeleteFails()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $accountService = AccountService::Instance();
        $hook1 = $this->createMock(IAccountDeletionHook::class);
        $hook2 = $this->createMock(IAccountDeletionHook::class);
        $hook3 = $this->createMock(IAccountDeletionHook::class);

        $accountService->expects($this->once())
            ->method('DeletionHooks')
            ->willReturn([$hook1, $hook2, $hook3]);
        $hook1->expects($this->once())
            ->method('OnDeleteAccount')
            ->with($account);
        $hook2->expects($this->once())
            ->method('OnDeleteAccount')
            ->with($account)
            ->willThrowException(new \RuntimeException('Expected message.'));
        $hook3->expects($this->never())
            ->method('OnDeleteAccount');
        $account->expects($this->never())
            ->method('Delete');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        AH::CallMethod($sut, 'doDelete', [$account]);
    }

    function testDoDeleteThrowsIfAccountDeleteFails()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $accountService = AccountService::Instance();
        $hook = $this->createMock(IAccountDeletionHook::class);

        $accountService->expects($this->once())
            ->method('DeletionHooks')
            ->willReturn([$hook]);
        $hook->expects($this->once())
            ->method('OnDeleteAccount')
            ->with($account);
        $account->expects($this->once())
            ->method('Delete')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete account.");
        AH::CallMethod($sut, 'doDelete', [$account]);
    }

    function testDoDeleteSucceeds()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $accountService = AccountService::Instance();
        $hook = $this->createMock(IAccountDeletionHook::class);

        $accountService->expects($this->once())
            ->method('DeletionHooks')
            ->willReturn([$hook]);
        $hook->expects($this->once())
            ->method('OnDeleteAccount')
            ->with($account);
        $account->expects($this->once())
            ->method('Delete')
            ->willReturn(true);

        AH::CallMethod($sut, 'doDelete', [$account]);
    }

    #endregion doDelete

    #region logOut -------------------------------------------------------------

    function testLogOut()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('DeleteSession');
        $accountService->expects($this->once())
            ->method('DeletePersistentLogin');

        AH::CallMethod($sut, 'logOut');
    }

    #endregion logOut
}
