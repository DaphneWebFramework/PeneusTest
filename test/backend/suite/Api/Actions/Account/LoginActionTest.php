<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\Account\LoginAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\Security\CsrfToken;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Harmonia\Systems\ValidationSystem\DataAccessor;
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
    private ?AccountService $originalAccountService = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalSession =
            Session::ReplaceInstance($this->createMock(Session::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        Session::ReplaceInstance($this->originalSession);
        AccountService::ReplaceInstance($this->originalAccountService);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): LoginAction
    {
        return $this->getMockBuilder(LoginAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfAccountAlreadyLoggedIn()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn'
        );

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("You are already logged in.");
        $this->expectExceptionCode(StatusCode::Conflict->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfValidateRequestFails()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest'
        );

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willThrowException(new \RuntimeException('Placeholder message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Placeholder message.');
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountNotFound()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'findAccount'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->exactly(2))
            ->method('GetField')
            ->willReturnMap([
                ['email', 'john@example.com'],
                ['password', 'pass1234']
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Incorrect email address or password.");
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordVerificationFails()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'findAccount',
            'verifyPassword'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->exactly(2))
            ->method('GetField')
            ->willReturnMap([
                ['email', 'john@example.com'],
                ['password', 'wrongpass']
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'wrongpass')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Incorrect email address or password.");
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'findAccount',
            'verifyPassword',
            'logOut'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $database = Database::Instance();
        $account = $this->createMock(Account::class);

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->exactly(2))
            ->method('GetField')
            ->willReturnMap([
                ['email', 'john@example.com'],
                ['password', 'pass1234']
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame(
                        'Failed to save account.',
                        $e->getMessage()
                    );
                    return false;
                }
            });
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('logOut');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Login failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEstablishSessionIntegrityFails()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'findAccount',
            'verifyPassword',
            'logOut'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $database = Database::Instance();
        $account = $this->createMock(Account::class);
        $accountService = AccountService::Instance();

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->exactly(2))
            ->method('GetField')
            ->willReturnMap([
                ['email', 'john@example.com'],
                ['password', 'pass1234']
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame(
                        'Failed to establish session integrity.',
                        $e->getMessage()
                    );
                    return false;
                }
            });
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $accountService->expects($this->once())
            ->method('EstablishSessionIntegrity')
            ->with($account)
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('logOut');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Login failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDeleteCsrfCookieFails()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'findAccount',
            'verifyPassword',
            'deleteCsrfCookie',
            'logOut'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $database = Database::Instance();
        $account = $this->createMock(Account::class);
        $accountService = AccountService::Instance();

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->exactly(2))
            ->method('GetField')
            ->willReturnMap([
                ['email', 'john@example.com'],
                ['password', 'pass1234']
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame(
                        'Placeholder message.',
                        $e->getMessage()
                    );
                    return false;
                }
            });
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $accountService->expects($this->once())
            ->method('EstablishSessionIntegrity')
            ->with($account)
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('deleteCsrfCookie')
            ->willThrowException(new \RuntimeException('Placeholder message.'));
        $sut->expects($this->once())
            ->method('logOut');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Login failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'findAccount',
            'verifyPassword',
            'deleteCsrfCookie',
            'logOut'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $database = Database::Instance();
        $account = $this->createMock(Account::class);
        $accountService = AccountService::Instance();

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->exactly(2))
            ->method('GetField')
            ->willReturnMap([
                ['email', 'john@example.com'],
                ['password', 'pass1234']
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('verifyPassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $accountService->expects($this->once())
            ->method('EstablishSessionIntegrity')
            ->with($account)
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('deleteCsrfCookie');
        $sut->expects($this->never())
            ->method('logOut');

        $this->assertNull(AccessHelper::CallMethod($sut, 'onExecute'));
        $this->assertEqualsWithDelta(\time(), $account->timeLastLogin->getTimestamp(), 1);
    }

    #endregion onExecute

    #region isAccountLoggedIn --------------------------------------------------

    function testIsAccountLoggedInReturnsFalseIfAccountIsNotLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn(null);

        $result = AccessHelper::CallMethod($sut, 'isAccountLoggedIn');
        $this->assertFalse($result);
    }

    function testIsAccountLoggedInReturnsTrueIfAccountIsLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createStub(Account::class);
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);

        $result = AccessHelper::CallMethod($sut, 'isAccountLoggedIn');
        $this->assertTrue($result);
    }

    #endregion isAccountLoggedIn

    #region validateRequest ----------------------------------------------------

    #[DataProvider('invalidRequestDataProvider')]
    function testValidateRequestThrowsForInvalidRequestData(
        array $data,
        string $exceptionMessage
    ) {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($data);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        AccessHelper::CallMethod($sut, 'validateRequest');
    }

    #endregion validateRequest

    #region findAccount --------------------------------------------------------

    function testFindAccountReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE email = :email LIMIT 1',
            bindings: ['email' => 'john@example.com'],
            result: null,
            times: 1
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertNull(AccessHelper::CallMethod(
            $sut,
            'findAccount',
            ['john@example.com']
        ));
    }

    function testFindAccountReturnsEntityWhenFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE email = :email LIMIT 1',
            bindings: ['email' => 'john@example.com'],
            result: [[
                'id' => 23,
                'email' => 'john@example.com',
                'passwordHash' => 'hash1234',
                'displayName' => 'John',
                'timeActivated' => '2024-01-01 00:00:00',
                'timeLastLogin' => '2025-01-01 00:00:00'
            ]],
            times: 1
        );
        Database::ReplaceInstance($fakeDatabase);

        $account = AccessHelper::CallMethod(
            $sut,
            'findAccount',
            ['john@example.com']
        );
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(23, $account->id);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('hash1234', $account->passwordHash);
        $this->assertSame('John', $account->displayName);
        $this->assertSame('2024-01-01 00:00:00',
            $account->timeActivated->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-01 00:00:00',
            $account->timeLastLogin->format('Y-m-d H:i:s'));
    }

    #endregion findAccount

    #region verifyPassword -----------------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testVerifyPassword($returnValue)
    {
        $sut = $this->systemUnderTest();
        $account = $this->createStub(Account::class);
        $account->passwordHash = 'hash1234';
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('pass1234', 'hash1234')
            ->willReturn($returnValue);

        $this->assertSame(
            $returnValue,
            AccessHelper::CallMethod(
                $sut,
                'verifyPassword',
                [$account, 'pass1234']
            )
        );
    }

    #endregion verifyPassword

    #region deleteCsrfCookie ---------------------------------------------------

    function testDeleteCsrfCookie()
    {
        $sut = $this->systemUnderTest();
        $cookieService = CookieService::Instance();

        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');

        AccessHelper::CallMethod($sut, 'deleteCsrfCookie');
    }

    #endregion deleteCsrfCookie

    #region Data Providers -----------------------------------------------------

    static function invalidRequestDataProvider()
    {
        return [
            'email missing' => [
                'data' => [],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'email invalid' => [
                'data' => [
                    'email' => 'invalid-email'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'password missing' => [
                'data' => [
                    'email' => 'john@example.com'
                ],
                'exceptionMessage' => "Required field 'password' is missing."
            ],
            'password too short' => [
                'data' => [
                    'email' => 'john@example.com',
                    'password' => '1234567'
                ],
                'exceptionMessage' => "Field 'password' must have a minimum length of 8 characters."
            ],
            'password too long' => [
                'data' => [
                    'email' => 'john@example.com',
                    'password' => str_repeat('a', 73)
                ],
                'exceptionMessage' => "Field 'password' must have a maximum length of 72 characters."
            ],
        ];
    }

    #endregion Data Providers
}
