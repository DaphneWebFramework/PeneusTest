<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Guards\HeaderTokenGuard;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Services\CookieService;
use \TestToolkit\AccessHelper;

#[CoversClass(HeaderTokenGuard::class)]
class HeaderTokenGuardTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->originalRequest = Request::ReplaceInstance(
            $this->createMock(Request::class));
        $this->originalCookieService = CookieService::ReplaceInstance(
            $this->createMock(CookieService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    #region CSRF_HEADER_NAME ---------------------------------------------------

    function testHeaderNameIsLowercase()
    {
        $this->assertSame(
            HeaderTokenGuard::CSRF_HEADER_NAME,
            \strtolower(HeaderTokenGuard::CSRF_HEADER_NAME)
        );
    }

    #endregion CSRF_HEADER_NAME

    #region __construct --------------------------------------------------------

    function testConstructor()
    {
        $request = Request::Instance();
        $headers = $this->createMock(CArray::class);
        $cookieService = CookieService::Instance();

        $request->expects($this->once())
            ->method('Headers')
            ->willReturn($headers);
        $headers->expects($this->once())
            ->method('GetOrDefault')
            ->with(HeaderTokenGuard::CSRF_HEADER_NAME, '')
            ->willReturn('token-value');
        $cookieService->expects($this->once())
            ->method('CsrfCookieName')
            ->willReturn('cookie-name');

        $guard = new HeaderTokenGuard();

        $this->assertSame(
            'token-value',
            AccessHelper::GetProperty($guard, 'token')
        );
        $this->assertSame(
            'cookie-name',
            AccessHelper::GetProperty($guard, 'cookieName')
        );
    }

    #endregion __construct
}
