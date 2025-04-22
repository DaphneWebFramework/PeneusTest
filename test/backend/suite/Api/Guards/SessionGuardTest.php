<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Guards\SessionGuard;

use \Peneus\Model\Account;
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

    #endregion Verify
}
