<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Api\Actions\Account\LoginAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(LoginAction::class)]
class LoginActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?AccountService $originalAccountService = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
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
        AccountService::ReplaceInstance($this->originalAccountService);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): LoginAction
    {
        return $this->getMockBuilder(LoginAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfUserIsLoggedIn()
    {
        $sut = $this->systemUnderTest('ensureNotLoggedIn');

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfRequestValidationFails()
    {
        $sut = $this->systemUnderTest(
            'ensureNotLoggedIn',
            'validateRequest'
        );

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountAuthenticationFails()
    {
        $sut = $this->systemUnderTest(
            'ensureNotLoggedIn',
            'validateRequest',
            'findAndAuthenticateAccount'
        );

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'keepLoggedIn' => false
            ]);
        $sut->expects($this->once())
            ->method('findAndAuthenticateAccount')
            ->with('john@example.com', 'pass1234')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoLogInFails()
    {
        $sut = $this->systemUnderTest(
            'ensureNotLoggedIn',
            'validateRequest',
            'findAndAuthenticateAccount',
            'doLogIn',
            'logOut'
        );
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'keepLoggedIn' => false
            ]);
        $sut->expects($this->once())
            ->method('findAndAuthenticateAccount')
            ->with('john@example.com', 'pass1234')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doLogIn')
            ->with($account, false)
            ->willThrowException(new \RuntimeException());
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });
        $sut->expects($this->once())
            ->method('logOut');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Login failed.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'ensureNotLoggedIn',
            'validateRequest',
            'findAndAuthenticateAccount',
            'doLogIn',
            'logOut'
        );
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'keepLoggedIn' => true
            ]);
        $sut->expects($this->once())
            ->method('findAndAuthenticateAccount')
            ->with('john@example.com', 'pass1234')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doLogIn')
            ->with($account, true);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });
        $sut->expects($this->never())
            ->method('logOut');
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');

        $this->assertNull(ah::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute

    #region ensureNotLoggedIn --------------------------------------------------

    function testEnsureNotLoggedInThrowsIfUserIsLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn($accountView);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("You are already logged in.");
        $this->expectExceptionCode(StatusCode::Conflict->value);
        ah::CallMethod($sut, 'ensureNotLoggedIn');
    }

    function testEnsureNotLoggedInSucceedsIfUserIsNotLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn(null);

        ah::CallMethod($sut, 'ensureNotLoggedIn');
    }

    #endregion ensureNotLoggedIn

    #region validateRequest ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
    function testValidateRequestThrows(
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
        ah::CallMethod($sut, 'validateRequest');
    }

    #[DataProvider('validPayloadProvider')]
    function testValidateRequest($expected, $data)
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($data);

        $this->assertEquals($expected, ah::CallMethod($sut, 'validateRequest'));
    }

    #endregion validateRequest

    #region findAndAuthenticateAccount -----------------------------------------

    function testFindAndAuthenticateAccountThrowsIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('findAccount');

        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Incorrect email address or password.");
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        ah::CallMethod($sut, 'findAndAuthenticateAccount', [
            'john@example.com',
            'pass1234'
        ]);
    }

    function testFindAndAuthenticateAccountThrowsIfPasswordVerificationFails()
    {
        $sut = $this->systemUnderTest('findAccount');
        $account = $this->createStub(Account::class);
        $account->passwordHash = 'hash1234';
        $securityService = SecurityService::Instance();

        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('pass1234', 'hash1234')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Incorrect email address or password.");
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        ah::CallMethod($sut, 'findAndAuthenticateAccount', [
            'john@example.com',
            'pass1234'
        ]);
    }

    function testFindAndAuthenticateAccountReturnsAccountOnSuccess()
    {
        $sut = $this->systemUnderTest('findAccount');
        $account = $this->createStub(Account::class);
        $account->passwordHash = 'hash1234';
        $securityService = SecurityService::Instance();

        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('pass1234', 'hash1234')
            ->willReturn(true);

        $this->assertSame(
            $account,
            ah::CallMethod($sut, 'findAndAuthenticateAccount', [
                'john@example.com',
                'pass1234'
            ])
        );
    }

    #endregion findAndAuthenticateAccount

    #region findAccount --------------------------------------------------------

    function testFindAccountReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE email = :email LIMIT 1',
            bindings: ['email' => 'john@example.com'],
            result: null,
            times: 1
        );

        $account = ah::CallMethod($sut, 'findAccount', ['john@example.com']);
        $this->assertNull($account);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountReturnsEntityIfRecordFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE email = :email LIMIT 1',
            bindings: ['email' => 'john@example.com'],
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

        $account = ah::CallMethod($sut, 'findAccount', ['john@example.com']);
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

    #region doLogIn ------------------------------------------------------------

    function testDoLogInThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $keepLoggedIn = false;
        $accountService = AccountService::Instance();

        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);
        $accountService->expects($this->never())
            ->method('CreateSession');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        ah::CallMethod($sut, 'doLogIn', [$account, $keepLoggedIn]);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    function testDoLogInSucceeds($keepLoggedIn)
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $account->id = 42;
        $accountService = AccountService::Instance();

        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $accountService->expects($this->once())
            ->method('CreateSession', $keepLoggedIn)
            ->with($account->id);

        ah::CallMethod($sut, 'doLogIn', [$account, $keepLoggedIn]);
        $this->assertEqualsWithDelta(
            \time(),
            $account->timeLastLogin->getTimestamp(),
            1
        );
    }

    #endregion doLogIn

    #region logOut -------------------------------------------------------------

    function testLogOut()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('DeleteSession');

        ah::CallMethod($sut, 'logOut');
    }

    #endregion logOut

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
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
            'keepLoggedIn not a string' => [
                'data' => [
                    'email' => 'john@example.com',
                    'password' => 'pass1234',
                    'keepLoggedIn' => 42
                ],
                'exceptionMessage' => "Field 'keepLoggedIn' must be a string."
            ],
            'keepLoggedIn invalid' => [
                'data' => [
                    'email' => 'john@example.com',
                    'password' => 'pass1234',
                    'keepLoggedIn' => 'invalid-value'
                ],
                'exceptionMessage' => "Field 'keepLoggedIn' failed custom validation."
            ],
        ];
    }

    static function validPayloadProvider()
    {
        return [
            'without keepLoggedIn' => [
                'expected' => (object)[
                    'email' => 'john@example.com',
                    'password' => 'pass1234',
                    'keepLoggedIn' => false
                ],
                'data' => [
                    'email' => 'john@example.com',
                    'password' => 'pass1234'
                ]
            ],
            'with keepLoggedIn' => [
                'expected' => (object)[
                    'email' => 'john@example.com',
                    'password' => 'pass1234',
                    'keepLoggedIn' => true
                ],
                'data' => [
                    'email' => 'john@example.com',
                    'password' => 'pass1234',
                    'keepLoggedIn' => 'on'
                ]
            ]
        ];
    }

    #endregion Data Providers
}
