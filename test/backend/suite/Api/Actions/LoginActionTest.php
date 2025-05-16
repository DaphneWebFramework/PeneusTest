<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\LoginAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Logger;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\Security\CsrfToken;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Queries\SelectQuery;
use \Harmonia\Systems\DatabaseSystem\ResultSet;
use \Peneus\Api\Actions\LogoutAction;
use \Peneus\Model\Account;
use \Peneus\Model\Role;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(LoginAction::class)]
class LoginActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Session $originalSession = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;
    private ?AccountService $originalAccountService = null;
    private ?Config $originalConfig = null;
    private ?Logger $originalLogger = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalSession =
            Session::ReplaceInstance($this->createMock(Session::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->config());
        $this->originalLogger =
            Logger::ReplaceInstance($this->createStub(Logger::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        Session::ReplaceInstance($this->originalSession);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
        AccountService::ReplaceInstance($this->originalAccountService);
        Config::ReplaceInstance($this->originalConfig);
        Logger::ReplaceInstance($this->originalLogger);
    }

    private function config()
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(string ...$mockedMethods): LoginAction
    {
        return $this->getMockBuilder(LoginAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfAlreadyLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You are already logged in.');
        $this->expectExceptionCode(StatusCode::Conflict->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEmailIsMissing()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'password' => 'pass1234'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required field 'email' is missing.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEmailIsInvalid()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'invalid-email',
                'password' => 'pass1234'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'email' must be a valid email address.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordIsMissing()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required field 'password' is missing.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordLengthIsLessThanMinimum()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => '1234567'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'password' must have a minimum length of 8 characters.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordLengthIsGreaterThanMaximum()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => \str_repeat('a', 73)
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'password' must have a maximum length of 72 characters.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('findAccount');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Incorrect email address or password.');
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordVerificationFails()
    {
        $sut = $this->systemUnderTest('findAccount', 'verifyPassword');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Incorrect email address or password.');
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteLogsOutAndThrowsIfUpdateLastLoginTimeFails()
    {
        $sut = $this->systemUnderTest('findAccount', 'verifyPassword',
            'updateLastLoginTime', 'createLogoutAction');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $logoutAction = $this->createMock(LogoutAction::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('updateLastLoginTime')
            ->with($account)
            ->willReturn(false);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame('Failed to update last login time.',
                                      $e->getMessage());
                    return false;
                }
            });
        $sut->expects($this->once())
            ->method('createLogoutAction')
            ->willReturn($logoutAction);
        $logoutAction->expects($this->once())
            ->method('Execute');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Login failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteLogsOutAndThrowsIfEstablishSessionIntegrityFails()
    {
        $sut = $this->systemUnderTest('findAccount', 'verifyPassword',
            'updateLastLoginTime', 'establishSessionIntegrity',
            'createLogoutAction');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $logoutAction = $this->createMock(LogoutAction::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('updateLastLoginTime')
            ->with($account)
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('establishSessionIntegrity')
            ->with($account)
            ->willReturn(false);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame('Failed to establish session integrity.',
                                      $e->getMessage());
                    return false;
                }
            });
        $sut->expects($this->once())
            ->method('createLogoutAction')
            ->willReturn($logoutAction);
        $logoutAction->expects($this->once())
            ->method('Execute');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Login failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteLogsOutAndThrowsIfDeleteCsrfCookieFails()
    {
        $sut = $this->systemUnderTest('findAccount', 'verifyPassword',
            'updateLastLoginTime', 'establishSessionIntegrity',
            'createLogoutAction');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $logoutAction = $this->createMock(LogoutAction::class);
        $cookieService = CookieService::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('updateLastLoginTime')
            ->with($account)
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('establishSessionIntegrity')
            ->with($account)
            ->willReturn(true);
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie')
            ->willThrowException(new \RuntimeException);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    return false;
                }
            });
        $sut->expects($this->once())
            ->method('createLogoutAction')
            ->willReturn($logoutAction);
        $logoutAction->expects($this->once())
            ->method('Execute');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Login failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceedsIfDatabaseTransactionSucceeds()
    {
        $sut = $this->systemUnderTest('findAccount', 'verifyPassword',
            'updateLastLoginTime', 'establishSessionIntegrity',
            'createLogoutAction');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('updateLastLoginTime')
            ->with($account)
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('establishSessionIntegrity')
            ->with($account)
            ->willReturn(true);
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });
        $sut->expects($this->never())
            ->method('createLogoutAction');

        $this->assertNull(AccessHelper::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute

    #region findAccount --------------------------------------------------------

    function testFindAccountReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $database = Database::Instance();
        $resultSet = $this->createMock(ResultSet::class);

        $database->expects($this->once())
            ->method('Execute')
            ->willReturn($resultSet);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn(null);

        $account = AccessHelper::CallMethod(
            $sut,
            'findAccount',
            ['john@example.com']
        );
        $this->assertNull($account);
    }

    function testFindAccountReturnsAccountWhenFound()
    {
        $sut = $this->systemUnderTest();
        $database = Database::Instance();
        $resultSet = $this->createMock(ResultSet::class);

        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame('account', AccessHelper::GetProperty($query, 'table'));
                $this->assertSame('*', AccessHelper::GetProperty($query, 'columns'));
                $this->assertSame('email = :email', AccessHelper::GetProperty($query, 'condition'));
                $this->assertNull(AccessHelper::GetProperty($query, 'orderBy'));
                $this->assertSame('1', AccessHelper::GetProperty($query, 'limit'));
                $this->assertSame(['email' => 'john@example.com'], $query->Bindings());
                return true;
            }))
            ->willReturn($resultSet);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn([
                'id' => 23,
                'email' => 'john@example.com',
                'passwordHash' => 'password-hash',
                'displayName' => 'John',
                'timeActivated' => '2024-01-01 00:00:00',
                'timeLastLogin' => '2025-01-01 00:00:00'
            ]);

        $account = AccessHelper::CallMethod(
            $sut,
            'findAccount',
            ['john@example.com']
        );
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(23, $account->id);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('password-hash', $account->passwordHash);
        $this->assertSame('John', $account->displayName);
        $this->assertSame('2024-01-01 00:00:00', $account->timeActivated->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-01 00:00:00', $account->timeLastLogin->format('Y-m-d H:i:s'));
    }

    #endregion findAccount

    #region findAccountRole ----------------------------------------------------

    function testFindAccountRoleReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $database = Database::Instance();
        $resultSet = $this->createMock(ResultSet::class);

        $database->expects($this->once())
            ->method('Execute')
            ->willReturn($resultSet);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn(null);

        $role = AccessHelper::CallMethod(
            $sut,
            'findAccountRole',
            [42]
        );
        $this->assertNull($role);
    }

    function testFindAccountRoleReturnsNullForInvalidEnumValue()
    {
        $sut = $this->systemUnderTest();
        $database = Database::Instance();
        $resultSet = $this->createMock(ResultSet::class);

        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame('accountrole', AccessHelper::GetProperty($query, 'table'));
                $this->assertSame('*', AccessHelper::GetProperty($query, 'columns'));
                $this->assertSame('accountId = :accountId', AccessHelper::GetProperty($query, 'condition'));
                $this->assertNull(AccessHelper::GetProperty($query, 'orderBy'));
                $this->assertSame('1', AccessHelper::GetProperty($query, 'limit'));
                $this->assertSame(['accountId' => 42], $query->Bindings());
                return true;
            }))
            ->willReturn($resultSet);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn([
                'accountId' => 42,
                'role' => 999 // invalid enum value
            ]);

        $role = AccessHelper::CallMethod(
            $sut,
            'findAccountRole',
            [42]
        );
        $this->assertNull($role);
    }

    function testFindAccountRoleReturnsRoleWhenFound()
    {
        $sut = $this->systemUnderTest();
        $database = Database::Instance();
        $resultSet = $this->createMock(ResultSet::class);

        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame('accountrole', AccessHelper::GetProperty($query, 'table'));
                $this->assertSame('*', AccessHelper::GetProperty($query, 'columns'));
                $this->assertSame('accountId = :accountId', AccessHelper::GetProperty($query, 'condition'));
                $this->assertNull(AccessHelper::GetProperty($query, 'orderBy'));
                $this->assertSame('1', AccessHelper::GetProperty($query, 'limit'));
                $this->assertSame(['accountId' => 42], $query->Bindings());
                return true;
            }))
            ->willReturn($resultSet);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn([
                'accountId' => 42,
                'role' => Role::Editor->value
            ]);

        $role = AccessHelper::CallMethod(
            $sut,
            'findAccountRole',
            [42]
        );
        $this->assertInstanceOf(Role::class, $role);
        $this->assertSame(Role::Editor, $role);
    }

    #endregion findAccountRole

    #region verifyPassword -----------------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testVerifyPassword($returnValue)
    {
        $sut = $this->systemUnderTest();
        $account = new Account(['passwordHash' => 'password-hash']);
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('plain-password', 'password-hash')
            ->willReturn($returnValue);

        $this->assertSame(
            $returnValue,
            AccessHelper::CallMethod(
                $sut,
                'verifyPassword',
                [$account, 'plain-password']
            )
        );
    }

    #endregion verifyPassword

    #region updateLastLoginTime ------------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testUpdateLastLoginTime($returnValue)
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);

        $account->expects($this->once())
            ->method('Save')
            ->willReturn($returnValue);

        $this->assertSame(
            $returnValue,
            AccessHelper::CallMethod(
                $sut,
                'updateLastLoginTime',
                [$account]
            )
        );
        $timeLastLogin = $account->timeLastLogin;
        $this->assertInstanceOf(\DateTime::class, $timeLastLogin);
        $this->assertEqualsWithDelta(time(), $timeLastLogin->getTimestamp(), 1);
    }

    #endregion updateLastLoginTime

    #region establishSessionIntegrity ------------------------------------------

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
            'establishSessionIntegrity',
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
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Clear')
            ->willThrowException(new \RuntimeException);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'establishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegrityFailsIfSessionCloseThrows()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $session = Session::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken');
        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Clear')
            ->willReturn($session);
        $session->expects($this->exactly(2))
            ->method('Set')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Close')
            ->willThrowException(new \RuntimeException);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'establishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegrityFailsIfSetCookieThrows()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $session = Session::Instance();
        $cookieService = CookieService::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken');
        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Clear')
            ->willReturn($session);
        $session->expects($this->exactly(2))
            ->method('Set')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Close');
        $cookieService->expects($this->once())
            ->method('SetCookie')
            ->willThrowException(new \RuntimeException);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'establishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegritySucceedsWithoutAccountRole()
    {
        $sut = $this->systemUnderTest('findAccountRole');
        $account = new Account(['id' => 23]);
        $securityService = SecurityService::Instance();
        $csrfToken = $this->createMock(CsrfToken::class);
        $session = Session::Instance();
        $accountService = AccountService::Instance();
        $cookieService = CookieService::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken')
            ->willReturn($csrfToken);
        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Clear')
            ->willReturn($session);
        $csrfToken->expects($this->once())
            ->method('Token')
            ->willReturn('integrity-token');
        $sut->expects($this->once())
            ->method('findAccountRole')
            ->with(23)
            ->willReturn(null); // no role explicitly set in the database
        $session->expects($this->exactly(2))
            ->method('Set')
            ->with($this->callback(function(...$args) {
                [$key, $value] = $args;
                return match ($key) {
                    AccountService::INTEGRITY_TOKEN_SESSION_KEY =>
                        $value === 'integrity-token',
                    AccountService::ACCOUNT_ID_SESSION_KEY =>
                        $value === 23,
                    default => false
                };
            }))
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Close');
        $accountService->expects($this->once())
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
            'establishSessionIntegrity',
            [$account]
        ));
    }

    function testEstablishSessionIntegritySucceedsWithAccountRole()
    {
        $sut = $this->systemUnderTest('findAccountRole');
        $account = new Account(['id' => 23]);
        $securityService = SecurityService::Instance();
        $csrfToken = $this->createMock(CsrfToken::class);
        $session = Session::Instance();
        $accountService = AccountService::Instance();
        $cookieService = CookieService::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken')
            ->willReturn($csrfToken);
        $session->expects($this->once())
            ->method('Start')
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Clear')
            ->willReturn($session);
        $csrfToken->expects($this->once())
            ->method('Token')
            ->willReturn('integrity-token');
        $sut->expects($this->once())
            ->method('findAccountRole')
            ->with(23)
            ->willReturn(Role::Editor);
        $session->expects($this->exactly(3))
            ->method('Set')
            ->with($this->callback(function(...$args) {
                [$key, $value] = $args;
                return match ($key) {
                    AccountService::INTEGRITY_TOKEN_SESSION_KEY =>
                        $value === 'integrity-token',
                    AccountService::ACCOUNT_ID_SESSION_KEY =>
                        $value === 23,
                    AccountService::ACCOUNT_ROLE_SESSION_KEY =>
                        $value === Role::Editor->value,
                    default => false
                };
            }))
            ->willReturn($session);
        $session->expects($this->once())
            ->method('Close');
        $accountService->expects($this->once())
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
            'establishSessionIntegrity',
            [$account]
        ));
    }

    #endregion establishSessionIntegrity

    #region createLogoutAction -------------------------------------------------

    function testCreateLogoutAction()
    {
        $sut = $this->systemUnderTest();

        $this->assertInstanceOf(
            LogoutAction::class,
            AccessHelper::CallMethod($sut, 'createLogoutAction')
        );
    }

    #endregion createLogoutAction
}
