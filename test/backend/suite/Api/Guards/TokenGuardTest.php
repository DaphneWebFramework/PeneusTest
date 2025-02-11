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

    #region Authorize ----------------------------------------------------------

    function testAuthorizeWithMissingCookie()
    {
        $cookies = $this->createMock(CArray::class);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('my_cookie')
            ->willReturn(null);
        $request = Request::Instance();
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $securityService = SecurityService::Instance();
        $securityService->expects($this->never())
            ->method('VerifyCsrfToken');
        $tokenGuard = new TokenGuard('token', 'my_cookie');
        $this->assertFalse($tokenGuard->Authorize());
    }

    function testAuthorizeWithInvalidCookie()
    {
        $cookies = $this->createMock(CArray::class);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('my_cookie')
            ->willReturn('invalid');
        $request = Request::Instance();
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $securityService = SecurityService::Instance();
        $securityService->expects($this->once())
            ->method('VerifyCsrfToken')
            ->with($this->callback(function($csrfToken) {
                return $csrfToken->Token() === '123456'
                    && $csrfToken->CookieValue() === 'invalid';
            }))
            ->willReturn(false);
        $tokenGuard = new TokenGuard('123456', 'my_cookie');
        $this->assertFalse($tokenGuard->Authorize());
    }

    function testAuthorizeWithValidCookie()
    {
        $cookies = $this->createMock(CArray::class);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('my_cookie')
            ->willReturn('valid');
        $request = Request::Instance();
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $securityService = SecurityService::Instance();
        $securityService->expects($this->once())
            ->method('VerifyCsrfToken')
            ->with($this->callback(function($csrfToken) {
                return $csrfToken->Token() === '123456'
                    && $csrfToken->CookieValue() === 'valid';
            }))
            ->willReturn(true);
        $tokenGuard = new TokenGuard('123456', 'my_cookie');
        $this->assertTrue($tokenGuard->Authorize());
    }

    #endregion Authorize
}
