<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Account\DeleteAction;

use \Harmonia\Config;
use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Api\Hooks\IAccountDeletionHook;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper;

#[CoversClass(DeleteAction::class)]
class DeleteActionTest extends TestCase
{
    private ?Database $originalDatabase = null;
    private ?Config $originalConfig = null;
    private ?AccountService $originalAccountService = null;

    protected function setUp(): void
    {
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->createConfig());
    }

    protected function tearDown(): void
    {
        Database::ReplaceInstance($this->originalDatabase);
        AccountService::ReplaceInstance($this->originalAccountService);
        Config::ReplaceInstance($this->originalConfig);
    }

    private function createConfig(): Config
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(string ...$mockedMethods): DeleteAction
    {
        return $this->getMockBuilder(DeleteAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfUserNotLoggedIn()
    {
        $sut = $this->systemUnderTest('logOut');
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'You do not have permission to perform this action.');
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfHookFails()
    {
        $sut = $this->systemUnderTest('logOut');
        $accountService = AccountService::Instance();
        $account = $this->createMock(Account::class);
        $hook1 = $this->createMock(IAccountDeletionHook::class);
        $hook2 = $this->createMock(IAccountDeletionHook::class);
        $hook3 = $this->createMock(IAccountDeletionHook::class);
        $database = Database::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);
        $accountService->expects($this->once())
            ->method('DeletionHooks')
            ->willReturn([$hook1, $hook2, $hook3]);
        $hook1->expects($this->once())
            ->method('OnDeleteAccount')
            ->with($account);
        $hook2->expects($this->once())
            ->method('OnDeleteAccount')
            ->with($account)
            ->willThrowException(new \RuntimeException('hook error'));
        $hook3->expects($this->never())
            ->method('OnDeleteAccount');
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    $callback();
                } catch (\Throwable $e) {
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Account deletion failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountDeletionFails()
    {
        $sut = $this->systemUnderTest('logOut');
        $accountService = AccountService::Instance();
        $account = $this->createMock(Account::class);
        $hook = $this->createMock(IAccountDeletionHook::class);
        $database = Database::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);
        $accountService->expects($this->once())
            ->method('DeletionHooks')
            ->willReturn([$hook]);
        $hook->expects($this->once())
            ->method('OnDeleteAccount')
            ->with($account);
        $account->expects($this->once())
            ->method('Delete')
            ->willReturn(false);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    $callback();
                } catch (\Throwable $e) {
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Account deletion failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('logOut');
        $accountService = AccountService::Instance();
        $account = $this->createMock(Account::class);
        $hook = $this->createMock(IAccountDeletionHook::class);
        $database = Database::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);
        $accountService->expects($this->once())
            ->method('DeletionHooks')
            ->willReturn([$hook]);
        $hook->expects($this->once())
            ->method('OnDeleteAccount')
            ->with($account);
        $account->expects($this->once())
            ->method('Delete')
            ->willReturn(true);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });
        $sut->expects($this->once())
            ->method('logOut');

        $this->assertNull(AccessHelper::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute
}
