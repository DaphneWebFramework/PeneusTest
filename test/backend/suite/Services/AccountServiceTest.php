<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Services\AccountService;

use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Api\Guards\TokenGuard;
use \Peneus\Api\Hooks\IAccountDeletionHook;
use \Peneus\Model\Account;
use \Peneus\Model\Role;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    private ?CookieService $originalCookieService = null;
    private ?SecurityService $originalSecurityService = null;
    private ?Session $originalSession = null;
    private ?Database $originalDatabase = null;

    protected function setUp(): void
    {
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalSession =
            Session::ReplaceInstance($this->createMock(Session::class));
        $this->originalDatabase =
            Database::ReplaceInstance(new FakeDatabase());
    }

    protected function tearDown(): void
    {
        CookieService::ReplaceInstance($this->originalCookieService);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        Session::ReplaceInstance($this->originalSession);
        Database::ReplaceInstance($this->originalDatabase);
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
        $session->expects($this->never())
            ->method('Close');
        $sut->expects($this->never())
            ->method('verifySessionIntegrity');
        $sut->expects($this->never())
            ->method('retrieveLoggedInAccount');
        $session->expects($this->never())
            ->method('Destroy');

        $this->expectException(\RuntimeException::class);
        $sut->LoggedInAccount();
    }

    function testLoggedInAccountThrowsIfSessionCloseThrows()
    {
        $sut = $this->systemUnderTest(
            'verifySessionIntegrity',
            'retrieveLoggedInAccount'
        );
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Close')
            ->willThrowException(new \RuntimeException());
        $sut->expects($this->never())
            ->method('verifySessionIntegrity');
        $sut->expects($this->never())
            ->method('retrieveLoggedInAccount');
        $session->expects($this->never())
            ->method('Destroy');

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

        $session->expects($this->exactly(2)) // 2nd call is before Destroy
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Close')
            ->willReturn($session);
        $sut->expects($this->once())
            ->method('verifySessionIntegrity')
            ->with($session)
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('retrieveLoggedInAccount');
        $session->expects($this->once())
            ->method('Destroy');

        $this->assertNull($sut->LoggedInAccount());
    }

    function testLoggedInAccountReturnsNullIfRetrieveLoggedInAccountReturnsNull()
    {
        $sut = $this->systemUnderTest(
            'verifySessionIntegrity',
            'retrieveLoggedInAccount'
        );
        $session = Session::Instance();

        $session->expects($this->exactly(2)) // 2nd call is before Destroy
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Close')
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
        $session->expects($this->once())
            ->method('Close')
            ->willReturn($session);
        $sut->expects($this->once())
            ->method('verifySessionIntegrity')
            ->with($session)
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('retrieveLoggedInAccount')
            ->with($session)
            ->willReturn($account);
        $session->expects($this->never())
            ->method('Destroy');

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
            ->method('Close');
        $session->expects($this->never())
            ->method('Get');

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
            ->method('Close')
            ->willThrowException(new \RuntimeException());
        $session->expects($this->never())
            ->method('Get');

        $this->expectException(\RuntimeException::class);
        $sut->LoggedInAccountRole();
    }

    function testLoggedInAccountRoleReturnsNullIfSessionGetReturnsNull()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Close')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ROLE_SESSION_KEY)
            ->willReturn(null);

        $this->assertNull($sut->LoggedInAccountRole());
    }

    function testLoggedInAccountRoleReturnsRoleIfSessionGetReturnsRole()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Close')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ROLE_SESSION_KEY)
            ->willReturn(20); // Role::Admin

        $this->assertSame(Role::Admin, $sut->LoggedInAccountRole());
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

    #region verifySessionIntegrity ---------------------------------------------

    function testVerifySessionIntegrityReturnsFalseIfIntegrityTokenIsMissing()
    {
        $sut = $this->systemUnderTest(
            'IntegrityCookieName',
            'createTokenGuard'
        );
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::INTEGRITY_TOKEN_SESSION_KEY)
            ->willReturn(null);
        $sut->expects($this->never())
            ->method('IntegrityCookieName');
        $sut->expects($this->never())
            ->method('createTokenGuard');

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'verifySessionIntegrity',
            [$session]
        ));
    }

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testVerifySessionIntegrityReturnsTokenGuardVerifyResult($returnValue)
    {
        $sut = $this->systemUnderTest(
            'IntegrityCookieName',
            'createTokenGuard'
        );
        $session = Session::Instance();
        $tokenGuard = $this->createMock(TokenGuard::class);

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::INTEGRITY_TOKEN_SESSION_KEY)
            ->willReturn('integrity-token-value');
        $sut->expects($this->once())
            ->method('IntegrityCookieName')
            ->willReturn('MYAPP_INTEGRITY');
        $sut->expects($this->once())
            ->method('createTokenGuard')
            ->with('integrity-token-value', 'MYAPP_INTEGRITY')
            ->willReturn($tokenGuard);
        $tokenGuard->expects($this->once())
            ->method('Verify')
            ->willReturn($returnValue);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'verifySessionIntegrity',
            [$session]
        ));
    }

    #endregion verifySessionIntegrity

    #region retrieveLoggedInAccount --------------------------------------------

    function testRetrieveLoggedInAccountReturnsNullWhenSessionHasNoAccountId()
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

    function testRetrieveLoggedInAccountReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();
        $fakeDatabase = Database::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ID_SESSION_KEY)
            ->willReturn(42);
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: null,
            times: 1
        );

        $this->assertNull(AccessHelper::CallMethod(
            $sut,
            'retrieveLoggedInAccount',
            [$session]
        ));

        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testRetrieveLoggedInAccountReturnsEntityWhenFound()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();
        $fakeDatabase = Database::Instance();

        $session->expects($this->once())
            ->method('Get')
            ->with(AccountService::ACCOUNT_ID_SESSION_KEY)
            ->willReturn(42);
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

        $account = AccessHelper::CallMethod(
            $sut,
            'retrieveLoggedInAccount',
            [$session]
        );

        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(42, $account->id);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('hash1234', $account->passwordHash);
        $this->assertSame('John', $account->displayName);
        $this->assertSame('2024-01-01 00:00:00', $account->timeActivated->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-01 00:00:00', $account->timeLastLogin->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion retrieveLoggedInAccount
}
