<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Api\Actions\Account\SignInWithGoogleAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Resource;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as AH;

#[CoversClass(SignInWithGoogleAction::class)]
class SignInWithGoogleActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Config $originalConfig = null;
    private ?Resource $originalResource = null;
    private ?AccountService $originalAccountService = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        Config::ReplaceInstance($this->originalConfig);
        Resource::ReplaceInstance($this->originalResource);
        AccountService::ReplaceInstance($this->originalAccountService);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): SignInWithGoogleAction
    {
        return $this->getMockBuilder(SignInWithGoogleAction::class)
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
        AH::CallMethod($sut, 'onExecute');
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
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfCredentialDecodeAndValidationFails()
    {
        $sut = $this->systemUnderTest(
            'ensureNotLoggedIn',
            'validateRequest',
            'decodeAndValidateCredential'
        );

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'credential' => 'credential-value'
            ]);
        $sut->expects($this->once())
            ->method('decodeAndValidateCredential')
            ->with('credential-value')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoLogInFails()
    {
        $sut = $this->systemUnderTest(
            'ensureNotLoggedIn',
            'validateRequest',
            'decodeAndValidateCredential',
            'findOrConstructAccount',
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
                'credential' => 'credential-value'
            ]);
        $sut->expects($this->once())
            ->method('decodeAndValidateCredential')
            ->with('credential-value')
            ->willReturn((object)[
                'email' => 'john@example.com',
                'displayName' => 'John'
            ]);
        $sut->expects($this->once())
            ->method('findOrConstructAccount')
            ->with('john@example.com', 'John')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doLogIn')
            ->with($account)
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
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'ensureNotLoggedIn',
            'validateRequest',
            'decodeAndValidateCredential',
            'findOrConstructAccount',
            'doLogIn',
            'logOut'
        );
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();
        $redirectUrl = new CUrl('/url/to/home');
        $resource = Resource::Instance();

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'credential' => 'credential-value'
            ]);
        $sut->expects($this->once())
            ->method('decodeAndValidateCredential')
            ->with('credential-value')
            ->willReturn((object)[
                'email' => 'john@example.com',
                'displayName' => 'John'
            ]);
        $sut->expects($this->once())
            ->method('findOrConstructAccount')
            ->with('john@example.com', 'John')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doLogIn')
            ->with($account);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });
        $sut->expects($this->never())
            ->method('logOut');
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');
        $resource->expects($this->once())
            ->method('PageUrl')
            ->with('home')
            ->willReturn($redirectUrl);

        $result = AH::CallMethod($sut, 'onExecute');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('redirectUrl', $result);
        $this->assertEquals($redirectUrl, $result['redirectUrl']);
    }

    #endregion onExecute

    #region ensureNotLoggedIn --------------------------------------------------

    function testEnsureNotLoggedInThrowsIfUserIsLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createStub(Account::class);
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("You are already logged in.");
        $this->expectExceptionCode(StatusCode::Conflict->value);
        AH::CallMethod($sut, 'ensureNotLoggedIn');
    }

    function testEnsureNotLoggedInSucceedsIfUserIsNotLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn(null);

        AH::CallMethod($sut, 'ensureNotLoggedIn');
    }

    #endregion ensureNotLoggedIn

    #region validateRequest ----------------------------------------------------

    #[DataProvider('invalidRequestDataProvider')]
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
        AH::CallMethod($sut, 'validateRequest');
    }

    function testValidateRequestSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $data = [
            'credential' => 'credential-value'
        ];
        $expected = (object)$data;

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($data);

        $this->assertEquals($expected, AH::CallMethod($sut, 'validateRequest'));
    }

    #endregion validateRequest

    #region decodeAndValidateCredential ----------------------------------------

    function testDecodeAndValidateCredentialThrowsIfCredentialDecodeFails()
    {
        $sut = $this->systemUnderTest('decodeCredential');

        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with('credential')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid credential.");
        AH::CallMethod($sut, 'decodeAndValidateCredential', ['credential']);
    }

    function testDecodeAndValidateCredentialThrowsIfClaimsValidationFails()
    {
        $sut = $this->systemUnderTest('decodeCredential', 'validateClaims');
        $claims = ['key' => 'value'];

        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with('credential')
            ->willReturn($claims);
        $sut->expects($this->once())
            ->method('validateClaims')
            ->with($claims)
            ->willThrowException(new \RuntimeException());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid claims.");
        AH::CallMethod($sut, 'decodeAndValidateCredential', ['credential']);
    }

    function testDecodeAndValidateCredentialSucceeds()
    {
        $sut = $this->systemUnderTest('decodeCredential', 'validateClaims');
        $claims = ['key' => 'value'];
        $data = (object)['key' => 'value'];

        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with('credential')
            ->willReturn($claims);
        $sut->expects($this->once())
            ->method('validateClaims')
            ->with($claims)
            ->willReturn($data);

        $this->assertSame(
            $data,
            AH::CallMethod($sut, 'decodeAndValidateCredential', ['credential'])
        );
    }

    #endregion decodeAndValidateCredential

    #region validateClaims -----------------------------------------------------

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['.apps.googleusercontent.com'])]
    #[TestWith(['"!^+%.apps.googleusercontent.com'])]
    function testValidateClaimsThrowsIfConfigClientIdIsMissingOrInvalid(
        ?string $clientId
    ) {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $claims = [];

        $config->expects($this->once())
            ->method('Option')
            ->with('Google.OAuth2.ClientID')
            ->willReturn($clientId);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Missing or invalid Google OAuth 2.0 client ID.");
        AH::CallMethod($sut, 'validateClaims', [$claims]);
    }

    #[DataProvider('invalidClaimsProvider')]
    function testValidateClaimsThrows(array $claims, string $exceptionMessage)
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('Option')
            ->with('Google.OAuth2.ClientID')
            ->willReturn('1234567890.apps.googleusercontent.com');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        AH::CallMethod($sut, 'validateClaims', [$claims]);
    }

    function testValidateClaimsSucceeds()
    {
        $sut = $this->systemUnderTest('normalizeDisplayName');
        $config = Config::Instance();
        $claims = [
            'iss' => 'https://accounts.google.com',
            'aud' => '1234567890.apps.googleusercontent.com',
            'sub' => '1234567890',
            'exp' => '253402300799',
            'email_verified' => 'true',
            'email' => 'john@example.com',
            'name' => 'John'
        ];
        $expected = (object)[
            'email' => 'john@example.com',
            'displayName' => 'John'
        ];

        $config->expects($this->once())
            ->method('Option')
            ->with('Google.OAuth2.ClientID')
            ->willReturn('1234567890.apps.googleusercontent.com');
        $sut->expects($this->once())
            ->method('normalizeDisplayName')
            ->with('John', 'john@example.com', '1234567890')
            ->willReturn('John');

        $actual = AH::CallMethod($sut, 'validateClaims', [$claims]);
        $this->assertEquals($expected, $actual);
    }

    #endregion validateClaims

    #region normalizeDisplayName -----------------------------------------------

    #[DataProvider('normalizeDisplayNameDataProvider')]
    function testNormalizeDisplayName(
        string $expected,
        string $name,
        string $email,
        string $sub
    ) {
        $sut = $this->systemUnderTest();

        $actual = AH::CallMethod($sut, 'normalizeDisplayName', [
            $name,
            $email,
            $sub
        ]);
        $this->assertSame($expected, $actual);
    }

    #endregion normalizeDisplayName

    #region findOrConstructAccount ---------------------------------------------

    function testFindOrConstructAccountWithExistingRecord()
    {
        $sut = $this->systemUnderTest('findAccount', 'constructAccount');
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->never())
            ->method('constructAccount');

        $this->assertSame(
            $account,
            AH::CallMethod($sut, 'findOrConstructAccount', [
                'john@example.com',
                'John'
            ])
        );
    }

    function testFindOrConstructAccountWithNonExistingRecord()
    {
        $sut = $this->systemUnderTest('findAccount', 'constructAccount');
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn(null);
        $sut->expects($this->once())
            ->method('constructAccount')
            ->with('john@example.com', 'John')
            ->willReturn($account);

        $this->assertSame(
            $account,
            AH::CallMethod($sut, 'findOrConstructAccount', [
                'john@example.com',
                'John'
            ])
        );
    }

    #endregion findOrConstructAccount

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

        $account = AH::CallMethod($sut, 'findAccount', ['john@example.com']);
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

        $account = AH::CallMethod($sut, 'findAccount', ['john@example.com']);
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

    #region constructAccount ---------------------------------------------------

    function testConstructAccount()
    {
        $sut = $this->systemUnderTest();

        $account = AH::CallMethod($sut, 'constructAccount', [
            'john@example.com',
            'John'
        ]);
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('', $account->passwordHash);
        $this->assertSame('John', $account->displayName);
        $this->assertEqualsWithDelta(\time(), $account->timeActivated->getTimestamp(), 1);
        $this->assertNull($account->timeLastLogin);
    }

    #endregion constructAccount

    #region doLogIn ------------------------------------------------------------

    function testDoLogInThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $accountService = AccountService::Instance();

        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);
        $accountService->expects($this->never())
            ->method('CreateSession');
        $accountService->expects($this->never())
            ->method('CreatePersistentLogin');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        AH::CallMethod($sut, 'doLogIn', [$account]);
    }

    function testDoLogInSucceeds()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $accountService = AccountService::Instance();

        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $accountService->expects($this->once())
            ->method('CreateSession')
            ->with($account);
        $accountService->expects($this->once())
            ->method('CreatePersistentLogin')
            ->with($account);

        AH::CallMethod($sut, 'doLogIn', [$account]);
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
        $accountService->expects($this->once())
            ->method('DeletePersistentLogin');

        AH::CallMethod($sut, 'logOut');
    }

    #endregion logOut

    #region Data Providers -----------------------------------------------------

    static function invalidRequestDataProvider()
    {
        return [
            'credential missing' => [
                'data' => [],
                'exceptionMessage' => "Required field 'credential' is missing."
            ],
            'credential not a string' => [
                'data' => [
                    'credential' => 42
                ],
                'exceptionMessage' => "Field 'credential' must be a string."
            ],
            'credential empty' => [
                'data' => [
                    'credential' => ''
                ],
                'exceptionMessage' => "Field 'credential' must have a minimum length of 1 characters."
            ],
        ];
    }

    static function invalidClaimsProvider()
    {
        return [
            'iss missing' => [
                'claims' => [
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Required field 'iss' is missing."
            ],
            'iss not a string' => [
                'claims' => [
                    'iss' => 42,
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'iss' must be a string."
            ],
            'iss not one of the expected values' => [
                'claims' => [
                    'iss' => 'https://example.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'iss' failed custom validation."
            ],
            'aud missing' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Required field 'aud' is missing."
            ],
            'aud not a string' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => 42,
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'aud' must be a string."
            ],
            'aud does not match expected value' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '0987654321.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'aud' failed custom validation."
            ],
            'sub missing' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Required field 'sub' is missing."
            ],
            'sub not a string' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => 42,
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'sub' must be a string."
            ],
            'sub empty' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'sub' must have a minimum length of 1 characters."
            ],
            'sub too long' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => str_repeat('9', 256),
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'sub' must have a maximum length of 255 characters."
            ],
            'exp missing' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Required field 'exp' is missing."
            ],
            'exp not an integer' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => 'not-an-integer',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'exp' must be an integer."
            ],
            'exp not in the future' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => \time() - 5000,
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'exp' failed custom validation."
            ],
            'email_verified missing' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Required field 'email_verified' is missing."
            ],
            'email_verified not a string' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => true,
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'email_verified' must be a string."
            ],
            'email_verified does not match expected value' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'false',
                    'email' => 'john@example.com',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'email_verified' failed custom validation."
            ],
            'email missing' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'email invalid' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'invalid-email',
                    'name' => 'John'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'name missing' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                ],
                'exceptionMessage' => "Required field 'name' is missing."
            ],
            'name not a string' => [
                'claims' => [
                    'iss' => 'https://accounts.google.com',
                    'aud' => '1234567890.apps.googleusercontent.com',
                    'sub' => '1234567890',
                    'exp' => '253402300799',
                    'email_verified' => 'true',
                    'email' => 'john@example.com',
                    'name' => 42
                ],
                'exceptionMessage' => "Field 'name' must be a string."
            ],
        ];
    }

    static function normalizeDisplayNameDataProvider()
    {
        return [
            'name matches pattern' => [
                'expected' => 'John',
                'name' => 'John',
                'email' => 'john@example.com',
                'sub' => '1234567890'
            ],
            'name matches pattern, name has leading and trailing whitespace' => [
                'expected' => 'John',
                'name' => '  John  ',
                'email' => 'john@example.com',
                'sub' => '1234567890'
            ],
            'name does not match pattern, email local part matches pattern' => [
                'expected' => 'john',
                'name' => '<invalid-display-name>',
                'email' => 'john@example.com',
                'sub' => '1234567890'
            ],
            'name does not match pattern, email does not contain "@"' => [
                'expected' => 'User_1234567890',
                'name' => '<invalid-display-name>',
                'email' => 'invalid-email',
                'sub' => '1234567890'
            ],
            'name does not match pattern, email local part does not match pattern' => [
                'expected' => 'User_1234567890',
                'name' => '<invalid-display-name>',
                'email' => '<invalid-email-local-part>@example.com',
                'sub' => '1234567890'
            ],
        ];
    }

    #endregion Data Providers
}
