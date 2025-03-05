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
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    private ?AccountService $originalAccountService = null;
    private ?CookieService $originalCookieService = null;
    private ?Session $originalSession = null;
    private ?Request $originalRequest = null;
    private ?SecurityService $originalSecurityService = null;

    protected function setUp(): void
    {
        $this->originalAccountService = AccountService::ReplaceInstance(null);
        $this->originalCookieService = CookieService::ReplaceInstance(
            $this->createMock(CookieService::class));
        $this->originalSession = Session::ReplaceInstance(
            $this->createMock(Session::class));
        $this->originalRequest = Request::ReplaceInstance(
            $this->createMock(Request::class));
        $this->originalSecurityService = SecurityService::ReplaceInstance(
            $this->createMock(SecurityService::class));
    }

    protected function tearDown(): void
    {
        AccountService::ReplaceInstance($this->originalAccountService);
        CookieService::ReplaceInstance($this->originalCookieService);
        Session::ReplaceInstance($this->originalSession);
        Request::ReplaceInstance($this->originalRequest);
        SecurityService::ReplaceInstance($this->originalSecurityService);
    }

    #region IntegrityCookieName ------------------------------------------------

    function testIntegrityCookieNameCallsAppSpecificCookieName()
    {
        $cookieService = CookieService::Instance();
        $accountService = AccountService::Instance();

        $cookieService->expects($this->once())
            ->method('AppSpecificCookieName')
            ->with('INTEGRITY')
            ->willReturn('MYAPP_INTEGRITY');

        $this->assertSame('MYAPP_INTEGRITY', $accountService->IntegrityCookieName());
    }

    #endregion IntegrityCookieName

    #region GetAuthenticatedAccount --------------------------------------------

    function testGetAuthenticatedAccountThrowsIfSessionStartThrows()
    {
        $session = Session::Instance();
        $accountService = AccountService::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willThrowException(new \RuntimeException());

        $this->expectException(\RuntimeException::class);
        $accountService->GetAuthenticatedAccount();
    }

    function testGetAuthenticatedAccountReturnsNullIfVerifySessionIntegrityReturnsFalse()
    {
        $session = Session::Instance();
        $accountService = $this->getMockBuilder(AccountService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['verifySessionIntegrity', 'retrieveAuthenticatedAccount'])
            ->getMock();

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $accountService->expects($this->once())
            ->method('verifySessionIntegrity')
            ->willReturn(false);
        $accountService->expects($this->never())
            ->method('retrieveAuthenticatedAccount');
        $session->expects($this->once())
            ->method('Destroy');

        $this->assertNull($accountService->GetAuthenticatedAccount());
    }

    function testGetAuthenticatedAccountReturnsNullIfRetrieveAuthenticatedAccountReturnsNull()
    {
        $session = Session::Instance();
        $accountService = $this->getMockBuilder(AccountService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['verifySessionIntegrity', 'retrieveAuthenticatedAccount'])
            ->getMock();

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $accountService->expects($this->once())
            ->method('verifySessionIntegrity')
            ->willReturn(true);
        $accountService->expects($this->once())
            ->method('retrieveAuthenticatedAccount')
            ->willReturn(null);
        $session->expects($this->once())
            ->method('Destroy');

        $this->assertNull($accountService->GetAuthenticatedAccount());
    }

    function testGetAuthenticatedAccountReturnsAccountIfRetrieveAuthenticatedAccountReturnsAccount()
    {
        $session = Session::Instance();
        $accountService = $this->getMockBuilder(AccountService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['verifySessionIntegrity', 'retrieveAuthenticatedAccount'])
            ->getMock();
        $account = $this->createMock(Account::class);

        $session->expects($this->once())
            ->method('Start');
        $accountService->expects($this->once())
            ->method('verifySessionIntegrity')
            ->willReturn(true);
        $accountService->expects($this->once())
            ->method('retrieveAuthenticatedAccount')
            ->willReturn($account);

        $this->assertSame($account, $accountService->GetAuthenticatedAccount());
    }

    #endregion GetAuthenticatedAccount

    #region verifySessionIntegrity ---------------------------------------------

    function testVerifySessionIntegrityReturnsFalseIfIntegrityTokenIsMissing()
    {
        $session = Session::Instance();
        $accountService = AccountService::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::INTEGRITY_TOKEN_SESSION_KEY)
            ->willReturn(null);

        $this->assertFalse(AccessHelper::CallMethod(
            $accountService,
            'verifySessionIntegrity'
        ));
    }

    function testVerifySessionIntegrityReturnsFalseIfIntegrityCookieIsMissing()
    {
        $session = Session::Instance();
        $accountService = $this->getMockBuilder(AccountService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['IntegrityCookieName'])
            ->getMock();
        $requestCookies = $this->createMock(CArray::class);
        $request = Request::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::INTEGRITY_TOKEN_SESSION_KEY)
            ->willReturn('integrity-token-value');
        $accountService->expects($this->once())
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
            $accountService,
            'verifySessionIntegrity'
        ));
    }

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testVerifySessionIntegrityReturnsWhateverVerifyCsrfTokenReturns($returnValue)
    {
        $session = Session::Instance();
        $accountService = $this->getMockBuilder(AccountService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['IntegrityCookieName'])
            ->getMock();
        $requestCookies = $this->createMock(CArray::class);
        $request = Request::Instance();
        $securityService = SecurityService::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::INTEGRITY_TOKEN_SESSION_KEY)
            ->willReturn('integrity-token-value');
        $accountService->expects($this->once())
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
            $accountService,
            'verifySessionIntegrity'
        ));
    }

    #endregion verifySessionIntegrity

    #region retrieveAuthenticatedAccount ---------------------------------------

    function testRetrieveAuthenticatedAccountReturnsNullIfAccountIdIsMissing()
    {
        $session = Session::Instance();
        $accountService = AccountService::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ID_SESSION_KEY)
            ->willReturn(null);

        $this->assertNull(AccessHelper::CallMethod(
            $accountService,
            'retrieveAuthenticatedAccount'
        ));
    }

    #[DataProvider('nullOrAccountDataProvider')]
    function testRetrieveAuthenticatedAccountReturnsWhateverFindAccountByIdReturns($returnValue)
    {
        $session = Session::Instance();
        $accountService = $this->getMockBuilder(AccountService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAccountById'])
            ->getMock();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ID_SESSION_KEY)
            ->willReturn(123);
        $accountService->expects($this->once())
            ->method('findAccountById')
            ->with(123)
            ->willReturn($returnValue);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $accountService,
            'retrieveAuthenticatedAccount'
        ));
    }

    #endregion retrieveAuthenticatedAccount

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
