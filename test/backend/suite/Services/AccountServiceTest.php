<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Services\AccountService;

use \Harmonia\Services\CookieService;
use \Harmonia\Services\Security\CsrfToken;
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

    #region EstablishSessionIntegrity ------------------------------------------

    function testEstablishSessionIntegrityFailsIfSessionStartThrows()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $session = Session::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken');
        $session->expects($this->once())
            ->method('Start')
            ->willThrowException(new \RuntimeException);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'EstablishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegrityFailsIfSessionClearThrows()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $session = Session::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken');
        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Clear')
            ->willThrowException(new \RuntimeException);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'EstablishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegrityFailsIfSessionRenewIdThrows()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $session = Session::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken');
        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Clear')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('RenewId')
            ->willThrowException(new \RuntimeException);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'EstablishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegrityFailsIfSessionCloseThrows()
    {
        $sut = $this->systemUnderTest('findAccountRole');
        $securityService = SecurityService::Instance();
        $session = Session::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken');
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
            ->willReturnSelf();
        $sut->expects($this->once())
            ->method('findAccountRole');
        $session->expects($this->once())
            ->method('Close')
            ->willThrowException(new \RuntimeException);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'EstablishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegrityFailsIfSetCookieThrows()
    {
        $sut = $this->systemUnderTest('findAccountRole');
        $securityService = SecurityService::Instance();
        $session = Session::Instance();
        $cookieService = CookieService::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken');
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
            ->willReturnSelf();
        $sut->expects($this->once())
            ->method('findAccountRole');
        $session->expects($this->once())
            ->method('Close');
        $cookieService->expects($this->once())
            ->method('SetCookie')
            ->willThrowException(new \RuntimeException);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'EstablishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegritySucceedsWithoutAccountRole()
    {
        $sut = $this->systemUnderTest(
            'IntegrityCookieName',
            'findAccountRole'
        );
        $securityService = SecurityService::Instance();
        $csrfToken = $this->createMock(CsrfToken::class);
        $session = Session::Instance();
        $cookieService = CookieService::Instance();
        $account = new Account(['id' => 23]);

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken')
            ->willReturn($csrfToken);
        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Clear')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('RenewId')
            ->willReturnSelf();
        $csrfToken->expects($this->once())
            ->method('Token')
            ->willReturn('integrity-token');
        $session->expects($this->exactly(2))
            ->method('Set')
            ->with($this->callback(function(...$args) {
                [$key, $value] = $args;
                return match ($key) {
                    'INTEGRITY_TOKEN' =>
                        $value === 'integrity-token',
                    'ACCOUNT_ID' =>
                        $value === 23,
                    default => false
                };
            }))
            ->willReturnSelf();
        $sut->expects($this->once())
            ->method('findAccountRole')
            ->with(23)
            ->willReturn(null); // no role explicitly set in the database
        $session->expects($this->once())
            ->method('Close');
        $sut->expects($this->once())
            ->method('IntegrityCookieName')
            ->willReturn('integrity-cookie-name');
        $csrfToken->expects($this->once())
            ->method('CookieValue')
            ->willReturn('integrity-cookie-value');
        $cookieService->expects($this->once())
            ->method('SetCookie')
            ->with('integrity-cookie-name', 'integrity-cookie-value');

        $this->assertTrue(AccessHelper::CallMethod(
            $sut,
            'EstablishSessionIntegrity',
            [$account]
        ));
    }

    function testEstablishSessionIntegritySucceedsWithAccountRole()
    {
        $sut = $this->systemUnderTest(
            'IntegrityCookieName',
            'findAccountRole'
        );
        $securityService = SecurityService::Instance();
        $csrfToken = $this->createMock(CsrfToken::class);
        $session = Session::Instance();
        $cookieService = CookieService::Instance();
        $account = new Account(['id' => 23]);

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken')
            ->willReturn($csrfToken);
        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Clear')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('RenewId')
            ->willReturnSelf();
        $csrfToken->expects($this->once())
            ->method('Token')
            ->willReturn('integrity-token');
        $session->expects($this->exactly(3))
            ->method('Set')
            ->with($this->callback(function(...$args) {
                [$key, $value] = $args;
                return match ($key) {
                    'INTEGRITY_TOKEN' =>
                        $value === 'integrity-token',
                    'ACCOUNT_ID' =>
                        $value === 23,
                    'ACCOUNT_ROLE' =>
                        $value === Role::Editor->value,
                    default => false
                };
            }))
            ->willReturnSelf();
        $sut->expects($this->once())
            ->method('findAccountRole')
            ->with(23)
            ->willReturn(Role::Editor);
        $session->expects($this->once())
            ->method('Close');
        $sut->expects($this->once())
            ->method('IntegrityCookieName')
            ->willReturn('integrity-cookie-name');
        $csrfToken->expects($this->once())
            ->method('CookieValue')
            ->willReturn('integrity-cookie-value');
        $cookieService->expects($this->once())
            ->method('SetCookie')
            ->with('integrity-cookie-name', 'integrity-cookie-value');

        $this->assertTrue(AccessHelper::CallMethod(
            $sut,
            'EstablishSessionIntegrity',
            [$account]
        ));
    }

    #endregion EstablishSessionIntegrity

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
            ->willReturnSelf();
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
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close')
            ->willReturnSelf();
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
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close')
            ->willReturnSelf();
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
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close')
            ->willReturnSelf();
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
            ->willReturnSelf();
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
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Get')
            ->with('ACCOUNT_ROLE')
            ->willReturn(null);

        $this->assertNull($sut->LoggedInAccountRole());
    }

    function testLoggedInAccountRoleReturnsRoleIfSessionGetReturnsRole()
    {
        $sut = $this->systemUnderTest();
        $session = Session::Instance();

        $session->expects($this->once())
            ->method('Start')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Close')
            ->willReturnSelf();
        $session->expects($this->once())
            ->method('Get')
            ->with('ACCOUNT_ROLE')
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

    #region findAccountRole ----------------------------------------------------

    function testFindAccountRoleReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole` WHERE accountId = :accountId LIMIT 1',
            bindings: ['accountId' => 42],
            result: null,
            times: 1
        );

        $role = AccessHelper::CallMethod($sut, 'findAccountRole', [42]);

        $this->assertNull($role);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountRoleReturnsNullForInvalidEnumValue()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole` WHERE accountId = :accountId LIMIT 1',
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

    function testFindAccountRoleReturnsEntityWhenFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole` WHERE accountId = :accountId LIMIT 1',
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
            ->with('INTEGRITY_TOKEN')
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
            ->with('INTEGRITY_TOKEN')
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
            ->with('ACCOUNT_ID')
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
            ->with('ACCOUNT_ID')
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
            ->with('ACCOUNT_ID')
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
