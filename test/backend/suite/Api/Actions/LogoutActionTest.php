<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\LogoutAction;

use \Harmonia\Services\CookieService;
use \Harmonia\Session;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper;

#[CoversClass(LogoutAction::class)]
class LogoutActionTest extends TestCase
{
    private ?AccountService $originalAccountService = null;
    private ?CookieService $originalCookieService = null;
    private ?Session $originalSession = null;

    protected function setUp(): void
    {
        $this->originalAccountService = AccountService::ReplaceInstance(
            $this->createMock(AccountService::class));
        $this->originalCookieService = CookieService::ReplaceInstance(
            $this->createMock(CookieService::class));
        $this->originalSession = Session::ReplaceInstance(
            $this->createMock(Session::class));
    }

    protected function tearDown(): void
    {
        AccountService::ReplaceInstance($this->originalAccountService);
        CookieService::ReplaceInstance($this->originalCookieService);
        Session::ReplaceInstance($this->originalSession);
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfDeleteCookieReturnsFalse()
    {
        $accountService = AccountService::Instance();
        $cookieService = CookieService::Instance();
        $session = Session::Instance();
        $logoutAction = new LogoutAction;

        $accountService->expects($this->once())
            ->method('IntegrityCookieName')
            ->willReturn('MYAPP_INTEGRITY');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('MYAPP_INTEGRITY')
            ->willReturn(false);
        $session->expects($this->never())
            ->method('Start');
        $session->expects($this->never())
            ->method('Destroy');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to delete integrity cookie.');
        AccessHelper::CallMethod($logoutAction, 'onExecute');
    }

    function testOnExecuteThrowsIfSessionStartThrows()
    {
        $accountService = AccountService::Instance();
        $cookieService = CookieService::Instance();
        $session = Session::Instance();
        $logoutAction = new LogoutAction;

        $accountService->expects($this->once())
            ->method('IntegrityCookieName')
            ->willReturn('MYAPP_INTEGRITY');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('MYAPP_INTEGRITY')
            ->willReturn(true);
        $session->expects($this->once())
            ->method('Start')
            ->willThrowException(new \RuntimeException);
        $session->expects($this->never())
            ->method('Destroy');

        $this->expectException(\RuntimeException::class);
        AccessHelper::CallMethod($logoutAction, 'onExecute');
    }

    function testOnExecuteThrowsIfSessionDestroyThrows()
    {
        $accountService = AccountService::Instance();
        $cookieService = CookieService::Instance();
        $session = Session::Instance();
        $logoutAction = new LogoutAction;

        $accountService->expects($this->once())
            ->method('IntegrityCookieName')
            ->willReturn('MYAPP_INTEGRITY');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('MYAPP_INTEGRITY')
            ->willReturn(true);
        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Destroy')
            ->willThrowException(new \RuntimeException);

        $this->expectException(\RuntimeException::class);
        AccessHelper::CallMethod($logoutAction, 'onExecute');
    }

    function testOnExecuteReturnsNullOnSuccess()
    {
        $accountService = AccountService::Instance();
        $cookieService = CookieService::Instance();
        $session = Session::Instance();
        $logoutAction = new LogoutAction;

        $accountService->expects($this->once())
            ->method('IntegrityCookieName')
            ->willReturn('MYAPP_INTEGRITY');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('MYAPP_INTEGRITY')
            ->willReturn(true);
        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Destroy');

        $this->assertNull(AccessHelper::CallMethod($logoutAction, 'onExecute'));
    }

    #endregion onExecute
}
