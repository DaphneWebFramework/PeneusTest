<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Account\DeleteAction;

use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Api\Hooks\IAccountDeletionHook;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;

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
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'findAccount');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoDeleteFails()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'findAccount',
            'doDelete');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
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
        $this->expectExceptionMessage("Failed to delete account.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'findAccount',
            'doDelete', 'logOut');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doDelete')
            ->with($account);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });
        $sut->expects($this->once())
            ->method('logOut');

        $this->assertNull(ah::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute

    #region ensureLoggedIn -----------------------------------------------------

    function testEnsureLoggedInThrowsIfUserIsNotLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "You do not have permission to perform this action.");
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        ah::CallMethod($sut, 'ensureLoggedIn');
    }

    function testEnsureLoggedInSucceedsIfUserIsLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn($accountView);

        $this->assertSame($accountView, ah::CallMethod($sut, 'ensureLoggedIn'));
    }

    #endregion ensureLoggedIn

    #region findAccount --------------------------------------------------------

    function testFindAccountThrowsIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: null,
            times: 1
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Account not found.");
        $this->expectExceptionCode(StatusCode::NotFound->value);
        ah::CallMethod($sut, 'findAccount', [42]);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountReturnsEntityIfRecordFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: [[
                'id' => 42,
                'email' => 'john@example.com',
                'passwordHash' => 'hash1234',
                'displayName' => 'John',
                'timeActivated' => '2024-01-01 00:00:00',
                'timeLastLogin' => '2025-01-01 00:00:00'
            ]],
            times: 1
        );

        $account = ah::CallMethod($sut, 'findAccount', [42]);
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(42, $account->id);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('hash1234', $account->passwordHash);
        $this->assertSame('John', $account->displayName);
        $this->assertSame('2024-01-01 00:00:00',
                          $account->timeActivated->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-01 00:00:00',
                          $account->timeLastLogin->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findAccount

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
        ah::CallMethod($sut, 'doDelete', [$account]);
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
        ah::CallMethod($sut, 'doDelete', [$account]);
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

        ah::CallMethod($sut, 'doDelete', [$account]);
    }

    #endregion doDelete

    #region logOut -------------------------------------------------------------

    function testLogOut()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('DeleteSession');

        ah::CallMethod($sut, 'logOut');
    }

    #endregion logOut
}
