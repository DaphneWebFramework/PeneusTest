<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Services\AccountService;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Api\Hooks\IAccountDeletionHook;
use \Peneus\Model\AccountView;
use \Peneus\Services\PersistentLoginManager;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    private ?PersistentLoginManager $plm = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;
    private ?Session $originalSession = null;
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;

    protected function setUp(): void
    {
        $this->plm = $this->createMock(PersistentLoginManager::class);
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
        $this->originalSession =
            Session::ReplaceInstance($this->createMock(Session::class));
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance(new FakeDatabase());
    }

    protected function tearDown(): void
    {
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
        Session::ReplaceInstance($this->originalSession);
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
    }

    private function systemUnderTest(string ...$mockedMethods): AccountService
    {
        $mock = $this->getMockBuilder(AccountService::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
        return ah::CallConstructor($mock, [$this->plm]);
    }

    #region CreateSession ------------------------------------------------------

    #[TestWith([true])]
    #[TestWith([false])]
    function testCreateSession($persistent)
    {
        $sut = $this->systemUnderTest('sessionBindingCookieName');
        $securityService = SecurityService::Instance();
        $session = Session::Instance();
        $cookieService = CookieService::Instance();
        $accountId = 42;

        $securityService->expects($this->once())
            ->method('GenerateCsrfPair')
            ->willReturn(['token-value', 'cookie-value']);
        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Clear')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('RenewId')
            ->willReturnSelf();
        $session->expects($this->exactly(2))
            ->method('Set')
            ->with($this->callback(function(...$args) use($accountId) {
                [$key, $value] = $args;
                return match ($key) {
                    'BINDING_TOKEN' => $value === 'token-value',
                    'ACCOUNT_ID'    => $value === $accountId
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
        if ($persistent) {
            $this->plm->expects($this->once())
                ->method('Create')
                ->with($accountId);
        } else {
            $this->plm->expects($this->never())
                ->method('Create');
        }

        $sut->CreateSession($accountId, $persistent);
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
        $this->plm->expects($this->once())
            ->method('Delete');

        $sut->DeleteSession();
    }

    #endregion DeleteSession

    #region LoggedInAccount ----------------------------------------------------

    function testLoggedInAccountWhenSessionExists()
    {
        $sut = $this->systemUnderTest(
            'accountFromSession',
            'rotatePersistentLoginIfNeeded'
        );
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;

        $sut->expects($this->once())
            ->method('accountFromSession')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('rotatePersistentLoginIfNeeded')
            ->with($accountView->id);

        $this->assertSame($accountView, $sut->LoggedInAccount());
    }

    function testLoggedInAccountWhenSessionDoesNotExistAndPersistentLoginExists()
    {
        $sut = $this->systemUnderTest(
            'accountFromSession',
            'rotatePersistentLoginIfNeeded',
            'tryPersistentLogin'
        );
        $accountView = $this->createStub(AccountView::class);

        $sut->expects($this->once())
            ->method('accountFromSession')
            ->willReturn(null);
        $sut->expects($this->never())
            ->method('rotatePersistentLoginIfNeeded');
        $sut->expects($this->once())
            ->method('tryPersistentLogin')
            ->willReturn($accountView);

        $this->assertSame($accountView, $sut->LoggedInAccount());
    }

    function testLoggedInAccountWhenSessionAndPersistentLoginDoNotExist()
    {
        $sut = $this->systemUnderTest(
            'accountFromSession',
            'rotatePersistentLoginIfNeeded',
            'tryPersistentLogin'
        );

        $sut->expects($this->once())
            ->method('accountFromSession')
            ->willReturn(null);
        $sut->expects($this->never())
            ->method('rotatePersistentLoginIfNeeded');
        $sut->expects($this->once())
            ->method('tryPersistentLogin')
            ->willReturn(null);

        $this->assertNull($sut->LoggedInAccount());
    }

    #endregion LoggedInAccount

    #region RegisterDeletionHook -----------------------------------------------

    function testRegisterDeletionHook()
    {
        $sut = $this->systemUnderTest();
        $hook = $this->createStub(IAccountDeletionHook::class);

        $sut->RegisterDeletionHook($hook);

        $hooks = ah::GetMockProperty(
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

        ah::SetMockProperty(
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
        $expected = 'APP_SB';

        $cookieService->expects($this->once())
            ->method('AppSpecificCookieName')
            ->with('SB')
            ->willReturn($expected);

        $actual = ah::CallMethod($sut, 'sessionBindingCookieName');
        $this->assertSame($expected, $actual);
    }

    #endregion sessionBindingCookieName

    #region findAccountView ----------------------------------------------------

    function testFindAccountViewReturnsNullIfNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountview` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: null,
            times: 1
        );

        $accountView = ah::CallMethod(
            $sut,
            'findAccountView',
            [42]
        );
        $this->assertNull($accountView);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountViewReturnsEntityIfFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountview` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: [[
                'id' => 42,
                'email' => 'john@example.com',
                'displayName' => 'John',
                'timeActivated' => '2024-01-01 00:00:00',
                'timeLastLogin' => '2025-01-01 00:00:00',
                'role' => null
            ]],
            times: 1
        );

        $accountView = ah::CallMethod(
            $sut,
            'findAccountView',
            [42]
        );
        $this->assertInstanceOf(AccountView::class, $accountView);
        $this->assertSame(42, $accountView->id);
        $this->assertSame('john@example.com', $accountView->email);
        $this->assertSame('John', $accountView->displayName);
        $this->assertSame('2024-01-01 00:00:00',
                          $accountView->timeActivated->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-01 00:00:00',
                          $accountView->timeLastLogin->format('Y-m-d H:i:s'));
        $this->assertNull($accountView->role);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findAccountView

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

        $this->assertNull(ah::CallMethod($sut, 'accountFromSession'));
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

        $this->assertNull(ah::CallMethod($sut, 'accountFromSession'));
    }

    function testAccountFromSessionReturnsEntityOnSuccess()
    {
        $sut = $this->systemUnderTest(
            'validateSession',
            'resolveAccountFromSession'
        );
        $session = Session::Instance();
        $accountView = $this->createStub(AccountView::class);

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
            ->willReturn($accountView);

        $this->assertSame(
            $accountView,
            ah::CallMethod($sut, 'accountFromSession')
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

        $this->assertFalse(ah::CallMethod($sut, 'validateSession'));
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

        $this->assertFalse(ah::CallMethod($sut, 'validateSession'));
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
            ah::CallMethod($sut, 'validateSession')
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

        $this->assertNull(ah::CallMethod($sut, 'resolveAccountFromSession'));
    }

    function testResolveAccountFromSessionReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest('findAccountView');
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with('ACCOUNT_ID')
            ->willReturn(42);
        $sut->expects($this->once())
            ->method('findAccountView')
            ->with(42)
            ->willReturn(null);

        $this->assertNull(ah::CallMethod($sut, 'resolveAccountFromSession'));
    }

    function testResolveAccountFromSessionReturnsEntityOnSuccess()
    {
        $sut = $this->systemUnderTest('findAccountView');
        $session = Session::Instance();
        $accountView = $this->createStub(AccountView::class);

        $session->expects($this->once())
            ->method('Get')
            ->with('ACCOUNT_ID')
            ->willReturn(42);
        $sut->expects($this->once())
            ->method('findAccountView')
            ->with(42)
            ->willReturn($accountView);

        $this->assertSame(
            $accountView,
            ah::CallMethod($sut, 'resolveAccountFromSession')
        );
    }

    #endregion resolveAccountFromSession

    #region tryPersistentLogin -------------------------------------------------

    function testTryPersistentLoginReturnsNullIfPersistentLoginCannotBeResolved()
    {
        $sut = $this->systemUnderTest();

        $this->plm->expects($this->once())
            ->method('Resolve')
            ->willReturn(null);

        $this->assertNull(ah::CallMethod($sut, 'tryPersistentLogin'));
    }

    function testTryPersistentLoginReturnsNullIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('findAccountView');

        $this->plm->expects($this->once())
            ->method('Resolve')
            ->willReturn(42);
        $sut->expects($this->once())
            ->method('findAccountView')
            ->with(42)
            ->willReturn(null);

        $this->assertNull(ah::CallMethod($sut, 'tryPersistentLogin'));
    }

    function testTryPersistentLoginReturnsAccountOnSuccess()
    {
        $sut = $this->systemUnderTest('findAccountView', 'CreateSession');
        $accountView = $this->createStub(AccountView::class);
        $session = Session::Instance();

        $this->plm->expects($this->once())
            ->method('Resolve')
            ->willReturn(42);
        $sut->expects($this->once())
            ->method('findAccountView')
            ->with(42)
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('CreateSession')
            ->with(42);
        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Set')
            ->with('PL_ROTATE_NEEDED', true)
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close');

        $this->assertSame(
            $accountView,
            ah::CallMethod($sut, 'tryPersistentLogin')
        );
    }

    #endregion tryPersistentLogin

    #region rotatePersistentLoginIfNeeded --------------------------------------

    function testRotatePersistentLoginIfNeededWhenSessionVariableDoesNotExist()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start');
        $session->expects($this->once())
            ->method('Has')
            ->with('PL_ROTATE_NEEDED')
            ->willReturn(false);
        $this->plm->expects($this->never())
            ->method('Rotate');
        $session->expects($this->never())
            ->method('Remove');
        $session->expects($this->once())
            ->method('Close');

        ah::CallMethod($sut, 'rotatePersistentLoginIfNeeded', [42]);
    }

    function testRotatePersistentLoginIfNeededWhenSessionVariableExists()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start');
        $session->expects($this->once())
            ->method('Has')
            ->with('PL_ROTATE_NEEDED')
            ->willReturn(true);
        $this->plm->expects($this->once())
            ->method('Rotate')
            ->with(42);
        $session->expects($this->once())
            ->method('Remove')
            ->with('PL_ROTATE_NEEDED');
        $session->expects($this->once())
            ->method('Close');

        ah::CallMethod($sut, 'rotatePersistentLoginIfNeeded', [42]);
    }

    #endregion rotatePersistentLoginIfNeeded
}
