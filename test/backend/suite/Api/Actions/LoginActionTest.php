<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\LoginAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Database\Database;
use \Harmonia\Database\Queries\SelectQuery;
use \Harmonia\Database\ResultSet;
use \Harmonia\Http\Request;
use \Harmonia\Logger;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\Security\CsrfToken;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Peneus\Api\Actions\LogoutAction;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;
use \Peneus\Translation;
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
    private ?Translation $originalTranslation = null;
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
        $this->originalTranslation =
            Translation::ReplaceInstance($this->createMock(Translation::class));
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
        Translation::ReplaceInstance($this->originalTranslation);
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
        $loginAction = $this->systemUnderTest();
        $accountService = AccountService::Instance();
        $translation = Translation::Instance();

        $accountService->expects($this->once())
            ->method('GetAuthenticatedAccount')
            ->willReturn($this->createStub(Account::class));
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_already_logged_in')
            ->willReturn('You are already logged in.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You are already logged in.');
        $this->expectExceptionCode(409);
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteThrowsIfEmailIsMissing()
    {
        $loginAction = $this->systemUnderTest();
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
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteThrowsIfEmailIsInvalid()
    {
        $loginAction = $this->systemUnderTest();
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
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordIsMissing()
    {
        $loginAction = $this->systemUnderTest();
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
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordLengthIsLessThanMinimum()
    {
        $loginAction = $this->systemUnderTest();
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
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordLengthIsGreaterThanMaximum()
    {
        $loginAction = $this->systemUnderTest();
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
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountNotFound()
    {
        $loginAction = $this->systemUnderTest('findAccount');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $translation = Translation::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $loginAction->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn(null);
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_incorrect_email_or_password')
            ->willReturn('Incorrect email address or password.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Incorrect email address or password.');
        $this->expectExceptionCode(401);
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordVerificationFails()
    {
        $loginAction = $this->systemUnderTest('findAccount', 'verifyPassword');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $translation = Translation::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $loginAction->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $loginAction->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(false);
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_incorrect_email_or_password')
            ->willReturn('Incorrect email address or password.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Incorrect email address or password.');
        $this->expectExceptionCode(401);
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteLogsOutAndThrowsIfUpdateLastLoginTimeFails()
    {
        $loginAction = $this->systemUnderTest('findAccount', 'verifyPassword',
            'updateLastLoginTime', 'createLogoutAction');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $logoutAction = $this->createMock(LogoutAction::class);
        $translation = Translation::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $loginAction->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $loginAction->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $loginAction->expects($this->once())
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
        $loginAction->expects($this->once())
            ->method('createLogoutAction')
            ->willReturn($logoutAction);
        $logoutAction->expects($this->once())
            ->method('Execute');
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_login_failed')
            ->willReturn('Login failed.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Login failed.');
        $this->expectExceptionCode(500);
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteLogsOutAndThrowsIfEstablishSessionIntegrityFails()
    {
        $loginAction = $this->systemUnderTest('findAccount', 'verifyPassword',
            'updateLastLoginTime', 'establishSessionIntegrity',
            'createLogoutAction');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $logoutAction = $this->createMock(LogoutAction::class);
        $translation = Translation::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $loginAction->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $loginAction->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $loginAction->expects($this->once())
            ->method('updateLastLoginTime')
            ->with($account)
            ->willReturn(true);
        $loginAction->expects($this->once())
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
        $loginAction->expects($this->once())
            ->method('createLogoutAction')
            ->willReturn($logoutAction);
        $logoutAction->expects($this->once())
            ->method('Execute');
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_login_failed')
            ->willReturn('Login failed.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Login failed.');
        $this->expectExceptionCode(500);
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteLogsOutAndThrowsIfDeleteCsrfCookieFails()
    {
        $loginAction = $this->systemUnderTest('findAccount', 'verifyPassword',
            'updateLastLoginTime', 'establishSessionIntegrity',
            'createLogoutAction');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $logoutAction = $this->createMock(LogoutAction::class);
        $cookieService = CookieService::Instance();
        $translation = Translation::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);
        $loginAction->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $loginAction->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $loginAction->expects($this->once())
            ->method('updateLastLoginTime')
            ->with($account)
            ->willReturn(true);
        $loginAction->expects($this->once())
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
        $loginAction->expects($this->once())
            ->method('createLogoutAction')
            ->willReturn($logoutAction);
        $logoutAction->expects($this->once())
            ->method('Execute');
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_login_failed')
            ->willReturn('Login failed.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Login failed.');
        $this->expectExceptionCode(500);
        AccessHelper::CallMethod($loginAction, 'onExecute');
    }

    function testOnExecuteSucceedsIfDatabaseTransactionSucceeds()
    {
        $loginAction = $this->systemUnderTest('findAccount', 'verifyPassword',
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
        $loginAction->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $loginAction->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $loginAction->expects($this->once())
            ->method('updateLastLoginTime')
            ->with($account)
            ->willReturn(true);
        $loginAction->expects($this->once())
            ->method('establishSessionIntegrity')
            ->with($account)
            ->willReturn(true);
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    return false;
                }
            });
        $loginAction->expects($this->never())
            ->method('createLogoutAction');

        $this->assertNull(AccessHelper::CallMethod($loginAction, 'onExecute'));
    }

    #endregion onExecute

    #region findAccount --------------------------------------------------------

    function testFindAccountReturnsNullWhenNotFound()
    {
        $loginAction = $this->systemUnderTest();
        $database = Database::Instance();
        $resultSet = $this->createMock(ResultSet::class);

        $database->expects($this->once())
            ->method('Execute')
            ->willReturn($resultSet);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn(null);

        $account = AccessHelper::CallMethod(
            $loginAction,
            'findAccount',
            ['john@example.com']
        );
        $this->assertNull($account);
    }

    function testFindAccountReturnsAccountWhenFound()
    {
        $loginAction = $this->systemUnderTest();
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
            $loginAction,
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

    #region verifyPassword -----------------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testVerifyPassword($returnValue)
    {
        $loginAction = $this->systemUnderTest();
        $account = new Account(['passwordHash' => 'password-hash']);
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('plain-password', 'password-hash')
            ->willReturn($returnValue);

        $this->assertSame(
            $returnValue,
            AccessHelper::CallMethod(
                $loginAction,
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
        $loginAction = $this->systemUnderTest();
        $account = $this->createMock(Account::class);

        $account->expects($this->once())
            ->method('Save')
            ->willReturn($returnValue);

        $this->assertSame(
            $returnValue,
            AccessHelper::CallMethod(
                $loginAction,
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
        $loginAction = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $session = Session::Instance();

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken');
        $session->expects($this->once())
            ->method('Start')
            ->willThrowException(new \RuntimeException);

        $this->assertFalse(AccessHelper::CallMethod(
            $loginAction,
            'establishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegrityFailsIfSessionClearThrows()
    {
        $loginAction = $this->systemUnderTest();
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
            $loginAction,
            'establishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegrityFailsIfSessionCloseThrows()
    {
        $loginAction = $this->systemUnderTest();
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
            $loginAction,
            'establishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegrityFailsIfSetCookieThrows()
    {
        $loginAction = $this->systemUnderTest();
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
            $loginAction,
            'establishSessionIntegrity',
            [new Account]
        ));
    }

    function testEstablishSessionIntegritySucceeds()
    {
        $loginAction = $this->systemUnderTest();
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
            $loginAction,
            'establishSessionIntegrity',
            [$account]
        ));
    }

    #endregion establishSessionIntegrity

    #region createLogoutAction -------------------------------------------------

    function testCreateLogoutAction()
    {
        $loginAction = $this->systemUnderTest();

        $this->assertInstanceOf(
            LogoutAction::class,
            AccessHelper::CallMethod($loginAction, 'createLogoutAction')
        );
    }

    #endregion createLogoutAction
}
