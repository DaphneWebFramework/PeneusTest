<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Guards\SessionGuard;

use \Peneus\Model\Account;
use \Peneus\Model\Role;
use \Peneus\Services\AccountService;

#[CoversClass(SessionGuard::class)]
class SessionGuardTest extends TestCase
{
    private ?AccountService $originalAccountService = null;

    protected function setUp(): void
    {
        $this->originalAccountService = AccountService::ReplaceInstance(
            $this->createMock(AccountService::class));
    }

    protected function tearDown(): void
    {
        AccountService::ReplaceInstance($this->originalAccountService);
    }

    #region Verify -------------------------------------------------------------

    function testVerifyWithLoggedInAccount()
    {
        $accountService = AccountService::Instance();
        $sessionGuard = new SessionGuard;

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));

        $this->assertTrue($sessionGuard->Verify());
    }

    function testVerifyWithNotLoggedInAccount()
    {
        $accountService = AccountService::Instance();
        $sessionGuard = new SessionGuard;

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn(null);

        $this->assertFalse($sessionGuard->Verify());
    }

    function testVerifyWhenAccountRoleMissingAndMinimumRoleSet()
    {
        $accountService = AccountService::Instance();
        $sessionGuard = new SessionGuard(Role::Editor);

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(null); // No role assigned

        $this->assertFalse($sessionGuard->Verify());
    }

    function testVerifyWhenAccountRoleBelowMinimum()
    {
        $accountService = AccountService::Instance();
        $sessionGuard = new SessionGuard(Role::Editor);

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(Role::None); // None < Editor

        $this->assertFalse($sessionGuard->Verify());
    }

    function testVerifyWhenAccountRoleEqualsMinimum()
    {
        $accountService = AccountService::Instance();
        $sessionGuard = new SessionGuard(Role::Editor);

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(Role::Editor); // Editor == Editor

        $this->assertTrue($sessionGuard->Verify());
    }

    function testVerifyWhenAccountRoleExceedsMinimum()
    {
        $accountService = AccountService::Instance();
        $sessionGuard = new SessionGuard(Role::Editor);

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(Role::Admin); // Admin >= Editor

        $this->assertTrue($sessionGuard->Verify());
    }

    #endregion Verify
}
