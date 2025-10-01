<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Guards\TokenGuard;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Services\SecurityService;

#[CoversClass(TokenGuard::class)]
class TokenGuardTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?SecurityService $originalSecurityService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
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
        $cookies = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();

        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(false);
        $cookies->expects($this->never())
            ->method('Get');
        $securityService->expects($this->never())
            ->method('VerifyCsrfPair');

        $sut = new TokenGuard('token-value', 'cookie-name');
        $this->assertFalse($sut->Verify());
    }

    function testVerifyWithInvalidCookie()
    {
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();

        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $securityService->expects($this->once())
            ->method('VerifyCsrfPair')
            ->with($this->callback(function(...$args) {
                [$token, $cookieValue] = $args;
                return $token === 'token-value'
                    && $cookieValue === 'cookie-value';
            }))
            ->willReturn(false);

        $sut = new TokenGuard('token-value', 'cookie-name');
        $this->assertFalse($sut->Verify());
    }

    function testVerifyWithValidCookie()
    {
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();

        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $securityService->expects($this->once())
            ->method('VerifyCsrfPair')
            ->with($this->callback(function(...$args) {
                [$token, $cookieValue] = $args;
                return $token === 'token-value'
                    && $cookieValue === 'cookie-value';
            }))
            ->willReturn(true);

        $sut = new TokenGuard('token-value', 'cookie-name');
        $this->assertTrue($sut->Verify());
    }

    #endregion Verify
}
