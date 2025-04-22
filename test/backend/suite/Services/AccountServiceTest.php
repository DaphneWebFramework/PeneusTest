<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Services\AccountService;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\Security\CsrfToken;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Peneus\Model\Account;
use \Peneus\Model\Role;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    private ?CookieService $originalCookieService = null;
    private ?Session $originalSession = null;
    private ?Request $originalRequest = null;
    private ?SecurityService $originalSecurityService = null;

    protected function setUp(): void
    {
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
        $this->originalSession =
            Session::ReplaceInstance($this->createMock(Session::class));
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
    }

    protected function tearDown(): void
    {
        CookieService::ReplaceInstance($this->originalCookieService);
        Session::ReplaceInstance($this->originalSession);
        Request::ReplaceInstance($this->originalRequest);
        SecurityService::ReplaceInstance($this->originalSecurityService);
    }

    private function systemUnderTest(string ...$mockedMethods): AccountService
    {
        return $this->getMockBuilder(AccountService::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region IntegrityCookieName ------------------------------------------------

    function testIntegrityCookieNameCallsAppSpecificCookieName()
    {
        $sut = $this->systemUnderTest();
        $cookieService = CookieService::Instance();

        $cookieService->expects($this->once())
            ->method('AppSpecificCookieName')
            ->with('INTEGRITY')
            ->willReturn('MYAPP_INTEGRITY');

        $this->assertSame('MYAPP_INTEGRITY', $sut->IntegrityCookieName());
    }

    #endregion IntegrityCookieName

    #region LoggedInAccount ----------------------------------------------------

    function testLoggedInAccountThrowsIfSessionStartThrows()
    {
        $sut = $this->systemUnderTest(
            'verifySessionIntegrity',
            'retrieveLoggedInAccount'
        );
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willThrowException(new \RuntimeException());
        $sut->expects($this->never())
            ->method('verifySessionIntegrity');
        $sut->expects($this->never())
            ->method('retrieveLoggedInAccount');
        $session->expects($this->never())
            ->method('Destroy');
        $session->expects($this->never())
            ->method('Close');

        $this->expectException(\RuntimeException::class);
        $sut->LoggedInAccount();
    }

    function testLoggedInAccountReturnsNullIfVerifySessionIntegrityReturnsFalse()
    {
        $sut = $this->systemUnderTest(
            'verifySessionIntegrity',
            'retrieveLoggedInAccount'
        );
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $sut->expects($this->once())
            ->method('verifySessionIntegrity')
            ->with($session)
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('retrieveLoggedInAccount');
        $session->expects($this->once())
            ->method('Destroy');
        $session->expects($this->never())
            ->method('Close');

        $this->assertNull($sut->LoggedInAccount());
    }

    function testLoggedInAccountReturnsNullIfRetrieveLoggedInAccountReturnsNull()
    {
        $sut = $this->systemUnderTest(
            'verifySessionIntegrity',
            'retrieveLoggedInAccount'
        );
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $sut->expects($this->once())
            ->method('verifySessionIntegrity')
            ->with($session)
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('retrieveLoggedInAccount')
            ->with($session)
            ->willReturn(null);
        $session->expects($this->once())
            ->method('Destroy');
        $session->expects($this->never())
            ->method('Close');

        $this->assertNull($sut->LoggedInAccount());
    }

    function testLoggedInAccountReturnsAccountIfRetrieveLoggedInAccountReturnsAccount()
    {
        $sut = $this->systemUnderTest(
            'verifySessionIntegrity',
            'retrieveLoggedInAccount'
        );
        $session = Session::Instance();
        $account = $this->createStub(Account::class);

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $sut->expects($this->once())
            ->method('verifySessionIntegrity')
            ->with($session)
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('retrieveLoggedInAccount')
            ->with($session)
            ->willReturn($account);
        $session->expects($this->once())
            ->method('Close');

        $this->assertSame($account, $sut->LoggedInAccount());
    }

    #endregion LoggedInAccount

    #region LoggedInAccountRole ------------------------------------------------

    function testLoggedInAccountRoleThrowsIfSessionStartThrows()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willThrowException(new \RuntimeException());
        $session->expects($this->never())
            ->method('Get');
        $session->expects($this->never())
            ->method('Close');

        $this->expectException(\RuntimeException::class);
        $sut->LoggedInAccountRole();
    }

    function testLoggedInAccountRoleThrowsIfSessionCloseThrows()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ROLE_SESSION_KEY)
            ->willReturn(20); // Role::Admin
        $session->expects($this->once())
            ->method('Close')
            ->willThrowException(new \RuntimeException());

        $this->expectException(\RuntimeException::class);
        $sut->LoggedInAccountRole();
    }

    function testLoggedInAccountRoleReturnsNullIfNotSetInSession()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ROLE_SESSION_KEY)
            ->willReturn(null);
        $session->expects($this->once())
            ->method('Close');

        $this->assertNull($sut->LoggedInAccountRole());
    }

    function testLoggedInAccountRoleReturnsRoleIfSetInSession()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ROLE_SESSION_KEY)
            ->willReturn(20); // Role::Admin
        $session->expects($this->once())
            ->method('Close');

        $this->assertSame(Role::Admin, $sut->LoggedInAccountRole());
    }

    #endregion LoggedInAccountRole

    #region verifySessionIntegrity ---------------------------------------------

    function testVerifySessionIntegrityReturnsFalseIfIntegrityTokenIsMissing()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::INTEGRITY_TOKEN_SESSION_KEY)
            ->willReturn(null);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'verifySessionIntegrity',
            [$session]
        ));
    }

    function testVerifySessionIntegrityReturnsFalseIfIntegrityCookieIsMissing()
    {
        $sut = $this->systemUnderTest('IntegrityCookieName');
        $session = Session::Instance();
        $requestCookies = $this->createMock(CArray::class);
        $request = Request::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::INTEGRITY_TOKEN_SESSION_KEY)
            ->willReturn('integrity-token-value');
        $sut->expects($this->once())
            ->method('IntegrityCookieName')
            ->willReturn('MYAPP_INTEGRITY');
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($requestCookies);
        $requestCookies->expects($this->once())
            ->method('Get')
            ->with('MYAPP_INTEGRITY')
            ->willReturn(null);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'verifySessionIntegrity',
            [$session]
        ));
    }

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testVerifySessionIntegrityReturnsWhateverVerifyCsrfTokenReturns($returnValue)
    {
        $sut = $this->systemUnderTest('IntegrityCookieName');
        $session = Session::Instance();
        $requestCookies = $this->createMock(CArray::class);
        $request = Request::Instance();
        $securityService = SecurityService::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::INTEGRITY_TOKEN_SESSION_KEY)
            ->willReturn('integrity-token-value');
        $sut->expects($this->once())
            ->method('IntegrityCookieName')
            ->willReturn('MYAPP_INTEGRITY');
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($requestCookies);
        $requestCookies->expects($this->once())
            ->method('Get')
            ->with('MYAPP_INTEGRITY')
            ->willReturn('integrity-cookie-value');
        $securityService->expects($this->once())
            ->method('VerifyCsrfToken')
            ->with($this->callback(function(CsrfToken $csrfToken) {
                return $csrfToken->Token() === 'integrity-token-value'
                    && $csrfToken->CookieValue() === 'integrity-cookie-value';
            }))
            ->willReturn($returnValue);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'verifySessionIntegrity',
            [$session]
        ));
    }

    #endregion verifySessionIntegrity

    #region retrieveLoggedInAccount --------------------------------------------

    function testRetrieveLoggedInAccountReturnsNullIfAccountIdIsMissing()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ID_SESSION_KEY)
            ->willReturn(null);

        $this->assertNull(AccessHelper::CallMethod(
            $sut,
            'retrieveLoggedInAccount',
            [$session]
        ));
    }

    #[DataProvider('nullOrAccountDataProvider')]
    function testRetrieveLoggedInAccountReturnsWhateverFindAccountByIdReturns($returnValue)
    {
        $sut = $this->systemUnderTest('findAccountById');
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ID_SESSION_KEY)
            ->willReturn(123);
        $sut->expects($this->once())
            ->method('findAccountById')
            ->with(123)
            ->willReturn($returnValue);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'retrieveLoggedInAccount',
            [$session]
        ));
    }

    #endregion retrieveLoggedInAccount

    #region Data Providers -----------------------------------------------------

    static function nullOrAccountDataProvider()
    {
        return [
            'null' => [null],
            'account' => [self::createStub(Account::class)]
        ];
    }

    #endregion Data Providers
}
