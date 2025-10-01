<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Services\AccountService;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Server;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Api\Hooks\IAccountDeletionHook;
use \Peneus\Model\Account;
use \Peneus\Model\PersistentLogin;
use \Peneus\Model\Role;
use \TestToolkit\AccessHelper;

#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;
    private ?Session $originalSession = null;
    private ?Request $originalRequest = null;
    private ?Server $originalServer = null;
    private ?Database $originalDatabase = null;

    protected function setUp(): void
    {
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
        $this->originalSession =
            Session::ReplaceInstance($this->createMock(Session::class));
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalServer =
            Server::ReplaceInstance($this->createMock(Server::class));
        $this->originalDatabase =
            Database::ReplaceInstance(new FakeDatabase());
    }

    protected function tearDown(): void
    {
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
        Session::ReplaceInstance($this->originalSession);
        Request::ReplaceInstance($this->originalRequest);
        Server::ReplaceInstance($this->originalServer);
        Database::ReplaceInstance($this->originalDatabase);
    }

    private function systemUnderTest(string ...$mockedMethods): AccountService
    {
        $mock = $this->getMockBuilder(AccountService::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
        return AccessHelper::CallConstructor($mock);
    }

    #region CreateSession ------------------------------------------------------

    #[TestWith([null])]
    #[TestWith([Role::Editor])]
    function testCreateSession(?Role $role)
    {
        $sut = $this->systemUnderTest(
            'findAccountRole',
            'sessionBindingCookieName'
        );
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $securityService = SecurityService::Instance();
        $session = Session::Instance();
        $cookieService = CookieService::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfPair')
            ->willReturn(['token-value', 'cookie-value']);
        $sut->expects($this->once())
            ->method('findAccountRole')
            ->with(42)
            ->willReturn($role);
        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Clear')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('RenewId')
            ->willReturnSelf();
        $session->expects($this->exactly($role === null ? 2 : 3))
            ->method('Set')
            ->with($this->callback(function(...$args) {
                [$key, $value] = $args;
                return match ($key) {
                    'BINDING_TOKEN' => $value === 'token-value',
                    'ACCOUNT_ID'    => $value === 42,
                    'ACCOUNT_ROLE'  => $value === Role::Editor->value,
                    default => false
                };
            }))
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close');
        $sut->expects($this->once())
            ->method('sessionBindingCookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('SetCookie')
            ->with('cookie-name', 'cookie-value');

        AccessHelper::CallMethod($sut, 'CreateSession', [$account]);
    }

    #endregion CreateSession

    #region DeleteSession ------------------------------------------------------

    function testDeleteSession()
    {
        $sut = $this->systemUnderTest(
            'sessionBindingCookieName'
        );
        $cookieService = CookieService::Instance();
        $session = Session::Instance();

        $sut->expects($this->once())
            ->method('sessionBindingCookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Destroy');

        AccessHelper::CallMethod($sut, 'DeleteSession');
    }

    #endregion DeleteSession

    #region CreatePersistentLogin ----------------------------------------------

    function testCreatePersistentLoginWhenRecordExists()
    {
        $sut = $this->systemUnderTest(
            'clientSignature',
            'findPersistentLoginForReuse',
            'constructPersistentLogin',
            'issuePersistentLogin'
        );
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $persistentLogin = $this->createStub(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $sut->expects($this->once())
            ->method('findPersistentLoginForReuse')
            ->with(42, 'client-signature')
            ->willReturn($persistentLogin);
        $sut->expects($this->never())
            ->method('constructPersistentLogin');
        $sut->expects($this->once())
            ->method('issuePersistentLogin')
            ->with($persistentLogin);

        AccessHelper::CallMethod($sut, 'CreatePersistentLogin', [$account]);
    }

    function testCreatePersistentLoginWhenRecordNotFound()
    {
        $sut = $this->systemUnderTest(
            'clientSignature',
            'findPersistentLoginForReuse',
            'constructPersistentLogin',
            'issuePersistentLogin'
        );
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $persistentLogin = $this->createStub(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $sut->expects($this->once())
            ->method('findPersistentLoginForReuse')
            ->with(42, 'client-signature')
            ->willReturn(null);
        $sut->expects($this->once())
            ->method('constructPersistentLogin')
            ->with(42, 'client-signature')
            ->willReturn($persistentLogin);
        $sut->expects($this->once())
            ->method('issuePersistentLogin')
            ->with($persistentLogin);

        AccessHelper::CallMethod($sut, 'CreatePersistentLogin', [$account]);
    }

    #endregion CreatePersistentLogin

    #region DeletePersistentLogin ----------------------------------------------

    function testDeletePersistentLoginWhenCookieNotFound()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName'
        );
        $cookieService = CookieService::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(false);

        AccessHelper::CallMethod($sut, 'DeletePersistentLogin');
    }

    function testDeletePersistentLoginWhenCookieValueIsInvalid()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue',
            'findPersistentLogin'
        );
        $cookieService = CookieService::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn([null, null]);
        $sut->expects($this->never())
            ->method('findPersistentLogin');

        AccessHelper::CallMethod($sut, 'DeletePersistentLogin');
    }

    function testDeletePersistentLoginWhenRecordNotFound()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue',
            'findPersistentLogin'
        );
        $cookieService = CookieService::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token']);
        $sut->expects($this->once())
            ->method('findPersistentLogin')
            ->with('lookup-key')
            ->willReturn(null);

        AccessHelper::CallMethod($sut, 'DeletePersistentLogin');
    }

    function testDeletePersistentLoginWhenRecordDeleteFails()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue',
            'findPersistentLogin'
        );
        $cookieService = CookieService::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $persistentLogin = $this->createMock(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token']);
        $sut->expects($this->once())
            ->method('findPersistentLogin')
            ->with('lookup-key')
            ->willReturn($persistentLogin);
        $persistentLogin->expects($this->once())
            ->method('Delete')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete persistent login.");
        AccessHelper::CallMethod($sut, 'DeletePersistentLogin');
    }

    function testDeletePersistentLoginWhenRecordDeleteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue',
            'findPersistentLogin'
        );
        $cookieService = CookieService::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $persistentLogin = $this->createMock(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token']);
        $sut->expects($this->once())
            ->method('findPersistentLogin')
            ->with('lookup-key')
            ->willReturn($persistentLogin);
        $persistentLogin->expects($this->once())
            ->method('Delete')
            ->willReturn(true);

        AccessHelper::CallMethod($sut, 'DeletePersistentLogin');
    }

    #endregion DeletePersistentLogin

    #region LoggedInAccount ----------------------------------------------------

    function testLoggedInAccountWhenSessionExists()
    {
        $sut = $this->systemUnderTest(
            'accountFromSession'
        );
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('accountFromSession')
            ->willReturn($account);

        $this->assertSame(
            $account,
            AccessHelper::CallMethod($sut, 'LoggedInAccount')
        );
    }

    function testLoggedInAccountWhenSessionDoesNotExist()
    {
        $sut = $this->systemUnderTest(
            'accountFromSession',
            'tryPersistentLogin'
        );
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('accountFromSession')
            ->willReturn(null);
        $sut->expects($this->once())
            ->method('tryPersistentLogin')
            ->willReturn($account);

        $this->assertSame(
            $account,
            AccessHelper::CallMethod($sut, 'LoggedInAccount')
        );
    }

    #endregion LoggedInAccount

    #region LoggedInAccountRole ------------------------------------------------

    function testLoggedInAccountRoleWhenSessionVariableIsMissing()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close');
        $session->expects($this->once())
            ->method('Get')
            ->with('ACCOUNT_ROLE')
            ->willReturn(null);

        $this->assertNull(AccessHelper::CallMethod($sut, 'LoggedInAccountRole'));
    }

    function testLoggedInAccountRoleWhenSessionVariableIsNotAValidEnumValue()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close');
        $session->expects($this->once())
            ->method('Get')
            ->with('ACCOUNT_ROLE')
            ->willReturn(999); // invalid

        $this->assertNull(AccessHelper::CallMethod($sut, 'LoggedInAccountRole'));
    }

    function testLoggedInAccountRoleWhenSessionVariableIsAValidEnumValue()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close');
        $session->expects($this->once())
            ->method('Get')
            ->with('ACCOUNT_ROLE')
            ->willReturn(Role::Admin->value);

        $this->assertSame(
            Role::Admin,
            AccessHelper::CallMethod($sut, 'LoggedInAccountRole')
        );
    }

    #endregion LoggedInAccountRole

    #region RegisterDeletionHook -----------------------------------------------

    function testRegisterDeletionHook()
    {
        $sut = $this->systemUnderTest();
        $hook = $this->createStub(IAccountDeletionHook::class);

        $sut->RegisterDeletionHook($hook);

        $hooks = AccessHelper::GetMockProperty(
            AccountService::class,
            $sut,
            'deletionHooks'
        );

        $this->assertCount(1, $hooks);
        $this->assertSame($hook, $hooks[0]);
    }

    #endregion RegisterDeletionHook

    #region DeletionHooks ------------------------------------------------------

    function testDeletionHooks()
    {
        $sut = $this->systemUnderTest();
        $hook1 = $this->createStub(IAccountDeletionHook::class);
        $hook2 = $this->createStub(IAccountDeletionHook::class);

        AccessHelper::SetMockProperty(
            AccountService::class,
            $sut,
            'deletionHooks',
            [$hook1, $hook2]
        );

        $hooks = $sut->DeletionHooks();

        $this->assertCount(2, $hooks);
        $this->assertSame($hook1, $hooks[0]);
        $this->assertSame($hook2, $hooks[1]);
    }

    #endregion DeletionHooks

    #region sessionBindingCookieName -------------------------------------------

    function testSessionBindingCookieName()
    {
        $sut = $this->systemUnderTest();
        $cookieService = CookieService::Instance();

        $cookieService->expects($this->once())
            ->method('AppSpecificCookieName')
            ->with('SB')
            ->willReturn('APP_SB');

        $this->assertSame(
            'APP_SB',
            AccessHelper::CallMethod($sut, 'sessionBindingCookieName')
        );
    }

    #endregion sessionBindingCookieName

    #region persistentLoginCookieName ------------------------------------------

    function testPersistentLoginCookieName()
    {
        $sut = $this->systemUnderTest();
        $cookieService = CookieService::Instance();

        $cookieService->expects($this->once())
            ->method('AppSpecificCookieName')
            ->with('PL')
            ->willReturn('APP_PL');

        $this->assertSame(
            'APP_PL',
            AccessHelper::CallMethod($sut, 'persistentLoginCookieName')
        );
    }

    #endregion persistentLoginCookieName

    #region findAccountRole ----------------------------------------------------

    function testFindAccountRoleReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole`'
               . ' WHERE accountId = :accountId LIMIT 1',
            bindings: ['accountId' => 42],
            result: null,
            times: 1
        );

        $role = AccessHelper::CallMethod($sut, 'findAccountRole', [42]);

        $this->assertNull($role);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountRoleReturnsNullIfRoleIsNotAValidEnumValue()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole`'
               . ' WHERE accountId = :accountId LIMIT 1',
            bindings: ['accountId' => 42],
            result: [[
                'accountId' => 42,
                'role' => 999 // invalid
            ]],
            times: 1
        );

        $role = AccessHelper::CallMethod($sut, 'findAccountRole', [42]);

        $this->assertNull($role);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountRoleReturnsRoleOnSuccess()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole`'
               . ' WHERE accountId = :accountId LIMIT 1',
            bindings: ['accountId' => 42],
            result: [[
                'accountId' => 42,
                'role' => Role::Editor->value
            ]],
            times: 1
        );

        $role = AccessHelper::CallMethod($sut, 'findAccountRole', [42]);
        $this->assertInstanceOf(Role::class, $role);
        $this->assertSame(Role::Editor, $role);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findAccountRole

    #region accountFromSession -------------------------------------------------

    function testAccountFromSessionReturnsNullIfSessionValidationFails()
    {
        $sut = $this->systemUnderTest(
            'validateSession',
            'resolveAccountFromSession'
        );
        $session = Session::Instance();

        $session->expects($this->exactly(2)) // 2nd call is before Destroy
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close');
        $sut->expects($this->once())
            ->method('validateSession')
            ->willReturn(false);
        $session->expects($this->once())
            ->method('Destroy');
        $sut->expects($this->never())
            ->method('resolveAccountFromSession');

        $this->assertNull(AccessHelper::CallMethod($sut, 'accountFromSession'));
    }

    function testAccountFromSessionReturnsNullIfAccountCannotBeResolvedFromSession()
    {
        $sut = $this->systemUnderTest(
            'validateSession',
            'resolveAccountFromSession'
        );
        $session = Session::Instance();

        $session->expects($this->exactly(2)) // 2nd call is before Destroy
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close');
        $sut->expects($this->once())
            ->method('validateSession')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('resolveAccountFromSession')
            ->willReturn(null);
        $session->expects($this->once())
            ->method('Destroy');

        $this->assertNull(AccessHelper::CallMethod($sut, 'accountFromSession'));
    }

    function testAccountFromSessionReturnsEntityOnSuccess()
    {
        $sut = $this->systemUnderTest(
            'validateSession',
            'resolveAccountFromSession'
        );
        $session = Session::Instance();
        $account = $this->createStub(Account::class);

        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close');
        $sut->expects($this->once())
            ->method('validateSession')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('resolveAccountFromSession')
            ->willReturn($account);

        $this->assertSame(
            $account,
            AccessHelper::CallMethod($sut, 'accountFromSession')
        );
    }

    #endregion accountFromSession

    #region validateSession ----------------------------------------------------

    function testValidateSessionReturnsFalseIfTokenNotFound()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with('BINDING_TOKEN')
            ->willReturn(null);

        $this->assertFalse(AccessHelper::CallMethod($sut, 'validateSession'));
    }

    function testValidateSessionReturnsFalseIfCookieNotFound()
    {
        $sut = $this->systemUnderTest(
            'sessionBindingCookieName'
        );
        $session = Session::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $session->expects($this->once())
            ->method('Get')
            ->with('BINDING_TOKEN')
            ->willReturn('token-value');
        $sut->expects($this->once())
            ->method('sessionBindingCookieName')
            ->willReturn('cookie-name');
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(false);

        $this->assertFalse(AccessHelper::CallMethod($sut, 'validateSession'));
    }

    #[TestWith([true])]
    #[TestWith([false])]
    function testValidateSessionDelegatesToSecurityServiceVerifyCsrfPair($returnValue)
    {
        $sut = $this->systemUnderTest(
            'sessionBindingCookieName'
        );
        $session = Session::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with('BINDING_TOKEN')
            ->willReturn('token-value');
        $sut->expects($this->once())
            ->method('sessionBindingCookieName')
            ->willReturn('cookie-name');
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
            ->with('token-value', 'cookie-value')
            ->willReturn($returnValue);

        $this->assertSame(
            $returnValue,
            AccessHelper::CallMethod($sut, 'validateSession')
        );
    }

    #endregion validateSession

    #region resolveAccountFromSession ------------------------------------------

    function testResolveAccountFromSessionReturnsNullIfSessionVariableIsMissing()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with('ACCOUNT_ID')
            ->willReturn(null);

        $this->assertNull(AccessHelper::CallMethod($sut, 'resolveAccountFromSession'));
    }

    function testResolveAccountFromSessionReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest('findAccount');
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with('ACCOUNT_ID')
            ->willReturn(42);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with(42)
            ->willReturn(null);

        $this->assertNull(AccessHelper::CallMethod($sut, 'resolveAccountFromSession'));
    }

    function testResolveAccountFromSessionReturnsEntityOnSuccess()
    {
        $sut = $this->systemUnderTest('findAccount');
        $session = Session::Instance();
        $account = $this->createStub(Account::class);

        $session->expects($this->once())
            ->method('Get')
            ->with('ACCOUNT_ID')
            ->willReturn(42);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with(42)
            ->willReturn($account);

        $this->assertSame(
            $account,
            AccessHelper::CallMethod($sut, 'resolveAccountFromSession')
        );
    }

    #endregion resolveAccountFromSession

    #region findAccount --------------------------------------------------------

    function testFindAccountReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: null,
            times: 1
        );

        $this->assertNull(AccessHelper::CallMethod($sut, 'findAccount', [42]));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountReturnsEntityIfRecordFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: [[
                'id' => 42,
                'email' => 'john@example.com',
                'passwordHash' => 'hash1234',
                'displayName' => 'John',
                'timeActivated' => '2024-01-01 00:00:00',
                'timeLastLogin' => '2025-01-01 00:00:00'
            ]],
            times: 1
        );

        $account = AccessHelper::CallMethod($sut, 'findAccount', [42]);
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(42, $account->id);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('hash1234', $account->passwordHash);
        $this->assertSame('John', $account->displayName);
        $this->assertSame('2024-01-01 00:00:00',
            $account->timeActivated->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-01 00:00:00',
            $account->timeLastLogin->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findAccount

    #region tryPersistentLogin -------------------------------------------------

    function testTryPersistentLoginReturnsNullIfCookieNotFound()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(false);

        $this->assertNull(AccessHelper::CallMethod($sut, 'tryPersistentLogin'));
    }

    #[TestWith([null, null])]
    #[TestWith(['lookup-key', null])]
    #[TestWith([null, 'token-value'])]
    function testTryPersistentLoginReturnsNullIfCookieValueIsInvalid(
        ?string $lookupKey,
        ?string $token
    ) {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn([$lookupKey, $token]);

        $this->assertNull(AccessHelper::CallMethod($sut, 'tryPersistentLogin'));
    }

    function testTryPersistentLoginReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue',
            'findPersistentLogin'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findPersistentLogin')
            ->with('lookup-key')
            ->willReturn(null);

        $this->assertNull(AccessHelper::CallMethod($sut, 'tryPersistentLogin'));
    }

    function testTryPersistentLoginReturnsNullIfClientSignatureDoesNotMatch()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue',
            'findPersistentLogin',
            'clientSignature'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $persistentLogin = $this->createStub(PersistentLogin::class);
        $persistentLogin->clientSignature = 'different-client-signature';

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findPersistentLogin')
            ->with('lookup-key')
            ->willReturn($persistentLogin);
        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');

        $this->assertNull(AccessHelper::CallMethod($sut, 'tryPersistentLogin'));
    }

    function testTryPersistentLoginReturnsNullIfTokenDoesNotMatch()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue',
            'findPersistentLogin',
            'clientSignature'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $persistentLogin = $this->createStub(PersistentLogin::class);
        $persistentLogin->clientSignature = 'client-signature';
        $persistentLogin->tokenHash = 'different-token-hash';
        $securityService = SecurityService::Instance();

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findPersistentLogin')
            ->with('lookup-key')
            ->willReturn($persistentLogin);
        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('token-value', 'different-token-hash')
            ->willReturn(false);

        $this->assertNull(AccessHelper::CallMethod($sut, 'tryPersistentLogin'));
    }

    function testTryPersistentLoginReturnsNullIfRecordIsExpired()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue',
            'findPersistentLogin',
            'clientSignature',
            'currentTime'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $persistentLogin = $this->createStub(PersistentLogin::class);
        $persistentLogin->clientSignature = 'client-signature';
        $persistentLogin->tokenHash = 'token-hash';
        $persistentLogin->timeExpires = new \DateTime('2024-12-31 23:59:59');
        $securityService = SecurityService::Instance();

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findPersistentLogin')
            ->with('lookup-key')
            ->willReturn($persistentLogin);
        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('token-value', 'token-hash')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('currentTime')
            ->willReturn(new \DateTime('2025-01-01 00:00:00'));

        $this->assertNull(AccessHelper::CallMethod($sut, 'tryPersistentLogin'));
    }

    function testTryPersistentLoginReturnsNullIfAccountNotFound()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue',
            'findPersistentLogin',
            'clientSignature',
            'currentTime',
            'findAccount'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $persistentLogin = $this->createStub(PersistentLogin::class);
        $persistentLogin->accountId = 42;
        $persistentLogin->clientSignature = 'client-signature';
        $persistentLogin->tokenHash = 'token-hash';
        $persistentLogin->timeExpires = new \DateTime('2025-01-01 00:00:00');
        $securityService = SecurityService::Instance();

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findPersistentLogin')
            ->with('lookup-key')
            ->willReturn($persistentLogin);
        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('token-value', 'token-hash')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('currentTime')
            ->willReturn(new \DateTime('2025-01-01 00:00:00'));
        $sut->expects($this->once())
            ->method('findAccount')
            ->with(42)
            ->willReturn(null);

        $this->assertNull(AccessHelper::CallMethod($sut, 'tryPersistentLogin'));
    }

    function testTryPersistentLoginReturnsAccountOnSuccess()
    {
        $sut = $this->systemUnderTest(
            'persistentLoginCookieName',
            'parsePersistentLoginCookieValue',
            'findPersistentLogin',
            'clientSignature',
            'currentTime',
            'findAccount',
            'CreateSession',
            'issuePersistentLogin'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $persistentLogin = $this->createStub(PersistentLogin::class);
        $persistentLogin->accountId = 42;
        $persistentLogin->clientSignature = 'client-signature';
        $persistentLogin->tokenHash = 'token-hash';
        $persistentLogin->timeExpires = new \DateTime('2025-01-01 00:00:00');
        $securityService = SecurityService::Instance();
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
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
        $sut->expects($this->once())
            ->method('parsePersistentLoginCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findPersistentLogin')
            ->with('lookup-key')
            ->willReturn($persistentLogin);
        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('token-value', 'token-hash')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('currentTime')
            ->willReturn(new \DateTime('2025-01-01 00:00:00'));
        $sut->expects($this->once())
            ->method('findAccount')
            ->with(42)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('CreateSession')
            ->with($account);
        $sut->expects($this->once())
            ->method('issuePersistentLogin')
            ->with($persistentLogin);

        $this->assertSame(
            $account,
            AccessHelper::CallMethod($sut, 'tryPersistentLogin')
        );
    }

    #endregion tryPersistentLogin

    #region findPersistentLogin ------------------------------------------------

    function testFindPersistentLoginReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `persistentlogin` WHERE'
               . ' lookupKey = :lookupKey LIMIT 1',
            bindings: ['lookupKey' => 'lookup-key'],
            result: null,
            times: 1
        );

        $pl = AccessHelper::CallMethod(
            $sut,
            'findPersistentLogin',
            ['lookup-key']
        );

        $this->assertNull($pl);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindPersistentLoginReturnsEntityIfRecordFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `persistentlogin` WHERE'
               . ' lookupKey = :lookupKey LIMIT 1',
            bindings: ['lookupKey' => 'lookup-key'],
            result: [[
                'id' => 17,
                'accountId' => 42,
                'clientSignature' => 'client-signature',
                'lookupKey' => 'lookup-key',
                'tokenHash' => 'token-hash',
                'timeExpires' => '2024-01-01 00:00:00'
            ]],
            times: 1
        );

        $pl = AccessHelper::CallMethod(
            $sut,
            'findPersistentLogin',
            ['lookup-key']
        );
        $this->assertInstanceOf(PersistentLogin::class, $pl);
        $this->assertSame(17, $pl->id);
        $this->assertSame(42, $pl->accountId);
        $this->assertSame('client-signature', $pl->clientSignature);
        $this->assertSame('lookup-key', $pl->lookupKey);
        $this->assertSame('token-hash', $pl->tokenHash);
        $this->assertSame('2024-01-01 00:00:00',
            $pl->timeExpires->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findPersistentLogin

    #region findPersistentLoginForReuse ----------------------------------------

    function testFindPersistentLoginForReuseReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `persistentlogin` WHERE'
               . ' accountId = :accountId AND'
               . ' clientSignature = :clientSignature LIMIT 1',
            bindings: [
                'accountId' => 42,
                'clientSignature' => 'client-signature'
            ],
            result: null,
            times: 1
        );

        $pl = AccessHelper::CallMethod(
            $sut,
            'findPersistentLoginForReuse',
            [42, 'client-signature']
        );

        $this->assertNull($pl);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindPersistentLoginForReuseReturnsEntityIfRecordFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `persistentlogin` WHERE'
               . ' accountId = :accountId AND'
               . ' clientSignature = :clientSignature LIMIT 1',
            bindings: [
                'accountId' => 42,
                'clientSignature' => 'client-signature'
            ],
            result: [[
                'id' => 17,
                'accountId' => 42,
                'clientSignature' => 'client-signature',
                'lookupKey' => 'lookup-key',
                'tokenHash' => 'token-hash',
                'timeExpires' => '2024-01-01 00:00:00'
            ]],
            times: 1
        );

        $pl = AccessHelper::CallMethod(
            $sut,
            'findPersistentLoginForReuse',
            [42, 'client-signature']
        );
        $this->assertInstanceOf(PersistentLogin::class, $pl);
        $this->assertSame(17, $pl->id);
        $this->assertSame(42, $pl->accountId);
        $this->assertSame('client-signature', $pl->clientSignature);
        $this->assertSame('lookup-key', $pl->lookupKey);
        $this->assertSame('token-hash', $pl->tokenHash);
        $this->assertSame('2024-01-01 00:00:00',
            $pl->timeExpires->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findPersistentLoginForReuse

    #region constructPersistentLogin -------------------------------------------

    function testConstructPersistentLogin()
    {
        $sut = $this->systemUnderTest();

        $pl = AccessHelper::CallMethod(
            $sut,
            'constructPersistentLogin',
            [42, 'client-signature']
        );
        $this->assertInstanceOf(PersistentLogin::class, $pl);
        $this->assertSame(42, $pl->accountId);
        $this->assertSame('client-signature', $pl->clientSignature);
    }

    #endregion constructPersistentLogin

    #region issuePersistentLogin -----------------------------------------------

    function testIssuePersistentLoginThrowsIfRecordCannotBeSaved()
    {
        $sut = $this->systemUnderTest();
        $persistentLogin = $this->createMock(PersistentLogin::class);
        $securityService = SecurityService::Instance();

        $securityService->expects($this->exactly(2))
            ->method('GenerateToken')
            ->willReturnMap([
                [32, 'token-value'],
                [8, 'lookup-key']
            ]);
        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('token-value')
            ->willReturn('token-hash');
        $persistentLogin->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save persistent login.");
        AccessHelper::CallMethod($sut, 'issuePersistentLogin', [$persistentLogin]);
    }

    function testIssuePersistentLoginSucceeds()
    {
        $sut = $this->systemUnderTest(
            'expiryTime',
            'persistentLoginCookieName',
            'makePersistentLoginCookieValue'
        );
        $persistentLogin = $this->createMock(PersistentLogin::class);
        $securityService = SecurityService::Instance();
        $cookieService = CookieService::Instance();
        $expiryTime = new \DateTime('2026-01-01 00:00:00');

        $securityService->expects($this->exactly(2))
            ->method('GenerateToken')
            ->willReturnMap([
                [32, 'token-value'],
                [8, 'lookup-key']
            ]);
        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('token-value')
            ->willReturn('token-hash');
        $sut->expects($this->once())
            ->method('expiryTime')
            ->willReturn($expiryTime);
        $persistentLogin->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('persistentLoginCookieName')
            ->willReturn('cookie-name');
        $sut->expects($this->once())
            ->method('makePersistentLoginCookieValue')
            ->with('lookup-key', 'token-value')
            ->willReturn('cookie-value');
        $cookieService->expects($this->once())
            ->method('SetCookie')
            ->with('cookie-name', 'cookie-value', $expiryTime->getTimestamp());

        AccessHelper::CallMethod($sut, 'issuePersistentLogin', [$persistentLogin]);
    }

    #endregion issuePersistentLogin

    #region makePersistentLoginCookieValue -------------------------------------

    function testMakePersistentLoginCookieValue()
    {
        $sut = $this->systemUnderTest();

        $actual = AccessHelper::CallMethod(
            $sut,
            'makePersistentLoginCookieValue',
            ['lookup-key', 'token-value']
        );

        $this->assertSame(
            'lookup-key.token-value',
            $actual
        );
    }

    #endregion makePersistentLoginCookieValue

    #region parsePersistentLoginCookieValue ------------------------------------

    #[TestWith([[null , null     ], ''           ])]
    #[TestWith([[null , null     ], '.'          ])]
    #[TestWith([[null , 'bar'    ], '.bar'       ])]
    #[TestWith([[null , 'bar.baz'], '.bar.baz'   ])]
    #[TestWith([['foo', null     ], 'foo'        ])]
    #[TestWith([['foo', null     ], 'foo.'       ])]
    #[TestWith([['foo', 'bar'    ], 'foo.bar'    ])]
    #[TestWith([['foo', 'bar.'   ], 'foo.bar.'   ])]
    #[TestWith([['foo', 'bar.baz'], 'foo.bar.baz'])]
    function testParsePersistentLoginCookieValue(
        array $expected,
        string $cookieValue
    ) {
        $sut = $this->systemUnderTest();

        $actual = AccessHelper::CallMethod(
            $sut,
            'parsePersistentLoginCookieValue',
            [$cookieValue]
        );

        $this->assertSame($expected, $actual);
    }

    #endregion parsePersistentLoginCookieValue

    #region clientSignature ----------------------------------------------------

    function testClientSignature()
    {
        $sut = $this->systemUnderTest();
        $server = Server::Instance();
        $request = Request::Instance();
        $headers = $this->createMock(CArray::class);
        $clientAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';

        $server->expects($this->once())
            ->method('ClientAddress')
            ->willReturn($clientAddress);
        $request->expects($this->once())
            ->method('Headers')
            ->willReturn($headers);
        $headers->expects($this->once())
            ->method('GetOrDefault')
            ->with('user-agent', '')
            ->willReturn($userAgent);

        $expected = \rtrim(
            \base64_encode(
                \hash('md5', "{$clientAddress}\0{$userAgent}", true)
            ),
            '='
        );

        $this->assertSame(
            $expected,
            AccessHelper::CallMethod($sut, 'clientSignature')
        );
    }

    #endregion clientSignature

    #region currentTime --------------------------------------------------------

    function testCurrentTime()
    {
        $sut = $this->systemUnderTest();

        $actual = AccessHelper::CallMethod($sut, 'currentTime');
        $this->assertInstanceOf(\DateTime::class, $actual);
        $this->assertEqualsWithDelta(\time(), $actual->getTimestamp(), 1);
    }

    #endregion currentTime

    #region expiryTime ---------------------------------------------------------

    function testExpiryTime()
    {
        $sut = $this->systemUnderTest();

        $expected = new \DateTime('+1 month');
        $actual = AccessHelper::CallMethod($sut, 'expiryTime');
        $this->assertInstanceOf(\DateTime::class, $actual);
        $this->assertEqualsWithDelta(
            $expected->getTimestamp(),
            $actual->getTimestamp(),
            1
        );
    }

    #endregion expiryTime
}
