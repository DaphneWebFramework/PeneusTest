<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Api\Actions\Account\LogInAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(LogInAction::class)]
class LogInActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): LogInAction
    {
        return $this->getMockBuilder(LogInAction::class)
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

    function testOnExecuteThrowsIfPayloadValidationFails()
    {
        $sut = $this->systemUnderTest('ensureNotLoggedIn', 'validatePayload');

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validatePayload')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountAuthenticationFails()
    {
        $sut = $this->systemUnderTest('ensureNotLoggedIn', 'validatePayload',
            'findAndAuthenticateAccount');
        $payload = (object)[
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'keepLoggedIn' => false
        ];

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
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
        $sut = $this->systemUnderTest('ensureNotLoggedIn', 'validatePayload',
            'findAndAuthenticateAccount', 'doLogIn', 'logOut');
        $payload = (object)[
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'keepLoggedIn' => false
        ];
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
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
        $sut = $this->systemUnderTest('ensureNotLoggedIn', 'validatePayload',
            'findAndAuthenticateAccount', 'doLogIn', 'logOut');
        $payload = (object)[
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'keepLoggedIn' => true
        ];
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
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

    #region validatePayload ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
    function testValidatePayloadThrows(array $payload, string $exceptionMessage)
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($sut, 'validatePayload');
    }

    #[DataProvider('validPayloadProvider')]
    function testValidatePayloadSucceeds(object $expected, array $payload)
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $this->assertEquals($expected, ah::CallMethod($sut, 'validatePayload'));
    }

    #endregion validatePayload

    #region findAndAuthenticateAccount -----------------------------------------

    function testFindAndAuthenticateAccountThrowsIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('tryFindAccountByEmail');

        $sut->expects($this->once())
            ->method('tryFindAccountByEmail')
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
        $sut = $this->systemUnderTest('tryFindAccountByEmail');
        $account = $this->createStub(Account::class);
        $account->passwordHash = 'hash1234';
        $securityService = SecurityService::Instance();

        $sut->expects($this->once())
            ->method('tryFindAccountByEmail')
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
        $sut = $this->systemUnderTest('tryFindAccountByEmail');
        $account = $this->createStub(Account::class);
        $account->passwordHash = 'hash1234';
        $securityService = SecurityService::Instance();

        $sut->expects($this->once())
            ->method('tryFindAccountByEmail')
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
        $this->assertEqualsWithDelta(\time(), $account->timeLastLogin->getTimestamp(), 1);
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

    /**
     * @return array<string, array{
     *   payload: array<string, mixed>,
     *   exceptionMessage: string
     * }>
     */
    static function invalidPayloadProvider()
    {
        return [
            'email missing' => [
                'payload' => [],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'email invalid' => [
                'payload' => [
                    'email' => 'invalid-email'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'password missing' => [
                'payload' => [
                    'email' => 'john@example.com'
                ],
                'exceptionMessage' => "Required field 'password' is missing."
            ],
            'password too short' => [
                'payload' => [
                    'email' => 'john@example.com',
                    'password' => '1234567'
                ],
                'exceptionMessage' => "Field 'password' must have a minimum length of 8 characters."
            ],
            'password too long' => [
                'payload' => [
                    'email' => 'john@example.com',
                    'password' => str_repeat('a', 73)
                ],
                'exceptionMessage' => "Field 'password' must have a maximum length of 72 characters."
            ],
            'keepLoggedIn not a string' => [
                'payload' => [
                    'email' => 'john@example.com',
                    'password' => 'pass1234',
                    'keepLoggedIn' => 42
                ],
                'exceptionMessage' => "Field 'keepLoggedIn' must be a string."
            ],
            'keepLoggedIn invalid' => [
                'payload' => [
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
                'payload' => [
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
                'payload' => [
                    'email' => 'john@example.com',
                    'password' => 'pass1234',
                    'keepLoggedIn' => 'on'
                ]
            ]
        ];
    }

    #endregion Data Providers
}
