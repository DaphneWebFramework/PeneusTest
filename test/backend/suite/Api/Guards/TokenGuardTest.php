<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Guards\TokenGuard;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Peneus\Services\SecurityService;

#[CoversClass(TokenGuard::class)]
class TokenGuardTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?SecurityService $originalSecurityService = null;

    protected function setUp(): void
    {
        $this->originalRequest = Request::ReplaceInstance(
            $this->createMock(Request::class));
        $this->originalSecurityService = SecurityService::ReplaceInstance(
            $this->createMock(SecurityService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        SecurityService::ReplaceInstance($this->originalSecurityService);
    }

    #region Verify -------------------------------------------------------------

    function testVerifyWithMissingCookie()
    {
        $request = Request::Instance();
        $requestCookies = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();

        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($requestCookies);
        $requestCookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn(null);
        $securityService->expects($this->never())
            ->method('VerifyCsrfToken');

        $tokenGuard = new TokenGuard('token-value', 'cookie-name');
        $this->assertFalse($tokenGuard->Verify());
    }

    function testVerifyWithInvalidCookie()
    {
        $request = Request::Instance();
        $requestCookies = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();

        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($requestCookies);
        $requestCookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $securityService->expects($this->once())
            ->method('VerifyCsrfToken')
            ->with($this->callback(function($csrfToken) {
                return $csrfToken->Token() === 'token-value'
                    && $csrfToken->CookieValue() === 'cookie-value';
            }))
            ->willReturn(false);

        $tokenGuard = new TokenGuard('token-value', 'cookie-name');
        $this->assertFalse($tokenGuard->Verify());
    }

    function testVerifyWithValidCookie()
    {
        $request = Request::Instance();
        $requestCookies = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();

        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($requestCookies);
        $requestCookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $securityService->expects($this->once())
            ->method('VerifyCsrfToken')
            ->with($this->callback(function($csrfToken) {
                return $csrfToken->Token() === 'token-value'
                    && $csrfToken->CookieValue() === 'cookie-value';
            }))
            ->willReturn(true);

        $tokenGuard = new TokenGuard('token-value', 'cookie-name');
        $this->assertTrue($tokenGuard->Verify());
    }

    #endregion Verify
}
