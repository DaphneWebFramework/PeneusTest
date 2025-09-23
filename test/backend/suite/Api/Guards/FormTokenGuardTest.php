<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Guards\FormTokenGuard;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Services\CookieService;
use \TestToolkit\AccessHelper;

#[CoversClass(FormTokenGuard::class)]
class FormTokenGuardTest extends TestCase
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

    #region __construct --------------------------------------------------------

    function testConstructor()
    {
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $cookieService = CookieService::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('GetOrDefault')
            ->with(FormTokenGuard::CSRF_FIELD_NAME, '')
            ->willReturn('token-value');
        $cookieService->expects($this->once())
            ->method('CsrfCookieName')
            ->willReturn('cookie-name');

        $guard = new FormTokenGuard();

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
