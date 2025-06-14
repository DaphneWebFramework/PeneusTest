<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Services\LanguageService;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\Security\CsrfToken;
use \Harmonia\Services\SecurityService;
use \Peneus\Api\Guards\TokenGuard;
use \TestToolkit\AccessHelper;

#[CoversClass(LanguageService::class)]
class LanguageServiceTest extends TestCase
{
    private ?Config $originalConfig = null;
    private ?Request $originalRequest = null;
    private ?CookieService $originalCookieService = null;
    private ?SecurityService $originalSecurityService = null;

    protected function setUp(): void
    {
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
    }

    protected function tearDown(): void
    {
        Config::ReplaceInstance($this->originalConfig);
        Request::ReplaceInstance($this->originalRequest);
        CookieService::ReplaceInstance($this->originalCookieService);
        SecurityService::ReplaceInstance($this->originalSecurityService);
    }

    private function systemUnderTest(string ...$mockedMethods): LanguageService
    {
        $sut = $this->getMockBuilder(LanguageService::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
        return AccessHelper::CallConstructor($sut);
    }

    #region CurrentLanguage ----------------------------------------------------

    function testCurrentLanguage()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('Language', 'en')
            ->willReturn('fr');

        $this->assertSame('fr', $sut->CurrentLanguage());
    }

    #endregion CurrentLanguage

    #region Languages ----------------------------------------------------------

    function testLanguages()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $expected = ['English' => 'en', 'Français' => 'fr'];

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('Languages', [])
            ->willReturn($expected);

        $this->assertSame($expected, $sut->Languages());
    }

    #endregion Languages

    #region IsSupported --------------------------------------------------------

    function testIsSupportedReturnsTrueIfLanguageCodeIsSupported()
    {
        $sut = $this->systemUnderTest('Languages');

        $sut->expects($this->once())
            ->method('Languages')
            ->willReturn(['English' => 'en', 'Français' => 'fr']);

        $this->assertTrue($sut->IsSupported('fr'));
    }

    function testIsSupportedReturnsFalseIfLanguageCodeIsNotSupported()
    {
        $sut = $this->systemUnderTest('Languages');

        $sut->expects($this->once())
            ->method('Languages')
            ->willReturn(['English' => 'en', 'Français' => 'fr']);

        $this->assertFalse($sut->IsSupported('xx'));
    }

    #endregion IsSupported

    #region CsrfTokenValue ----------------------------------------------------

    function testCsrfTokenValue()
    {
        $sut = $this->systemUnderTest('csrfCookieName');
        $securityService = SecurityService::Instance();
        $cookieService = CookieService::Instance();
        $csrfToken = $this->createMock(CsrfToken::class);

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken')
            ->willReturn($csrfToken);
        $sut->expects($this->once())
            ->method('csrfCookieName')
            ->willReturn('MYAPP_LANG_CSRF');
        $csrfToken->expects($this->once())
            ->method('CookieValue')
            ->willReturn('csrf-cookie');
        $cookieService->expects($this->once())
            ->method('SetCookie')
            ->with('MYAPP_LANG_CSRF', 'csrf-cookie');
        $csrfToken->expects($this->once())
            ->method('Token')
            ->willReturn('csrf-token');

        $this->assertSame('csrf-token', $sut->CsrfTokenValue());
    }

    #endregion CsrfTokenValue

    #region CreateTokenGuard --------------------------------------------------

    function testCreateTokenGuard()
    {
        $sut = $this->systemUnderTest('csrfCookieName');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('GetOrDefault')
            ->with('csrfToken', '')
            ->willReturn('csrf-token');
        $sut->expects($this->once())
            ->method('csrfCookieName')
            ->willReturn('MYAPP_LANG_CSRF');

        $tokenGuard = $sut->CreateTokenGuard();
        $this->assertInstanceOf(TokenGuard::class, $tokenGuard);
        $this->assertSame('csrf-token', AccessHelper::GetProperty($tokenGuard, 'token'));
        $this->assertSame('MYAPP_LANG_CSRF', AccessHelper::GetProperty($tokenGuard, 'cookieName'));
    }

    #endregion CreateTokenGuard

    #region ReadFromCookie ----------------------------------------------------

    function testReadFromCookieReturnsEarlyIfCookieIsNull()
    {
        $sut = $this->systemUnderTest('cookieName');
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('MYAPP_LANG');
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('MYAPP_LANG')
            ->willReturn(null);

        $sut->ReadFromCookie(function($languageCode) {
            $this->fail('Callback should not be called');
        });
    }

    function testReadFromCookieDeletesCookieAndReturnsEarlyIfLanguageCodeIsUnsupported()
    {
        $sut = $this->systemUnderTest('cookieName', 'IsSupported');
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('MYAPP_LANG');
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('MYAPP_LANG')
            ->willReturn('xx');
        $sut->expects($this->once())
            ->method('IsSupported')
            ->with('xx')
            ->willReturn(false);
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('MYAPP_LANG');

        $sut->ReadFromCookie(function($languageCode) {
            $this->fail('Callback should not be called');
        });
    }

    function testReadFromCookieCallsCallbackIfLanguageCodeIsSupported()
    {
        $sut = $this->systemUnderTest('cookieName', 'IsSupported');
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('MYAPP_LANG');
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('MYAPP_LANG')
            ->willReturn('fr');
        $sut->expects($this->once())
            ->method('IsSupported')
            ->with('fr')
            ->willReturn(true);

        $captured = null;
        $sut->ReadFromCookie(function($languageCode) use(&$captured) {
            $captured = $languageCode;
        });
        $this->assertSame('fr', $captured);
    }

    #endregion ReadFromCookie

    #region WriteToCookie ------------------------------------------------------

    function testWriteToCookieThrowsIfStrictAndLanguageCodeIsUnsupported()
    {
        $sut = $this->systemUnderTest('cookieName', 'IsSupported');

        $sut->expects($this->once())
            ->method('IsSupported')
            ->with('xx')
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported language code: xx');
        $sut->WriteToCookie('xx', true);
    }

    function testWriteToCookieSetsCookieIfLanguageCodeIsSupported()
    {
        $sut = $this->systemUnderTest('cookieName', 'IsSupported');
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('IsSupported')
            ->with('fr')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('MYAPP_LANG');
        $cookieService->expects($this->once())
            ->method('SetCookie')
            ->with('MYAPP_LANG', 'fr');

        $sut->WriteToCookie('fr', true);
    }

    function testWriteToCookieSkipsValidationIfStrictIsFalse()
    {
        $sut = $this->systemUnderTest('cookieName', 'IsSupported');
        $cookieService = CookieService::Instance();

        $sut->expects($this->never())
            ->method('IsSupported');
        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('MYAPP_LANG');
        $cookieService->expects($this->once())
            ->method('SetCookie')
            ->with('MYAPP_LANG', 'xx');

        $sut->WriteToCookie('xx', false);
    }

    #endregion WriteToCookie

    #region DeleteCsrfCookie --------------------------------------------------

    function testDeleteCsrfCookieDeletesCookie()
    {
        $sut = $this->systemUnderTest('csrfCookieName');
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('csrfCookieName')
            ->willReturn('MYAPP_LANG_CSRF');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('MYAPP_LANG_CSRF');

        $sut->DeleteCsrfCookie();
    }

    #endregion DeleteCsrfCookie

    #region cookieName ---------------------------------------------------------

    function testCookieName()
    {
        $sut = $this->systemUnderTest();
        $cookieService = CookieService::Instance();

        $cookieService->expects($this->once())
            ->method('AppSpecificCookieName')
            ->with('LANG')
            ->willReturn('MYAPP_LANG');

        $result = AccessHelper::CallMethod($sut, 'cookieName');
        $this->assertSame('MYAPP_LANG', $result);
    }

    #endregion cookieName

    #region csrfCookieName -----------------------------------------------------

    function testCsrfCookieName()
    {
        $sut = $this->systemUnderTest();
        $cookieService = CookieService::Instance();

        $cookieService->expects($this->once())
            ->method('AppSpecificCookieName')
            ->with('LANG_CSRF')
            ->willReturn('MYAPP_LANG_CSRF');

        $result = AccessHelper::CallMethod($sut, 'csrfCookieName');
        $this->assertSame('MYAPP_LANG_CSRF', $result);
    }

    #endregion csrfCookieName
}
