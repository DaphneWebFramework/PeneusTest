<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Api\Actions\Account\SignInWithGoogleAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Client;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \Peneus\Resource;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(SignInWithGoogleAction::class)]
class SignInWithGoogleActionTest extends TestCase
{
    private ?Client $client = null;
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Config $originalConfig = null;
    private ?Resource $originalResource = null;
    private ?AccountService $originalAccountService = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
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
        $this->client = null;
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
            ->setConstructorArgs([$this->client])
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

    function testOnExecuteThrowsIfCredentialDecodeAndValidationFails()
    {
        $sut = $this->systemUnderTest('ensureNotLoggedIn', 'validatePayload',
            'decodeProfile');
        $payload = (object)[
            'credential' => 'cred1234'
        ];

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('decodeProfile')
            ->with('cred1234')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoLogInFails()
    {
        $sut = $this->systemUnderTest('ensureNotLoggedIn', 'validatePayload',
            'decodeProfile', 'findOrConstructAccount', 'doLogIn', 'logOut');
        $payload = (object)[
            'credential' => 'cred1234'
        ];
        $profile = (object)[
            'email' => 'john@example.com',
            'displayName' => 'John'
        ];
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('decodeProfile')
            ->with('cred1234')
            ->willReturn($profile);
        $sut->expects($this->once())
            ->method('findOrConstructAccount')
            ->with($profile)
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
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('ensureNotLoggedIn', 'validatePayload',
            'decodeProfile', 'findOrConstructAccount', 'doLogIn', 'logOut');
        $payload = (object)[
            'credential' => 'cred1234'
        ];
        $profile = (object)[
            'email' => 'john@example.com',
            'displayName' => 'John'
        ];
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();
        $redirectUrl = new CUrl('/url/to/home');
        $resource = Resource::Instance();

        $sut->expects($this->once())
            ->method('ensureNotLoggedIn');
        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('decodeProfile')
            ->with('cred1234')
            ->willReturn($profile);
        $sut->expects($this->once())
            ->method('findOrConstructAccount')
            ->with($profile)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doLogIn')
            ->with($account);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });
        $sut->expects($this->never())
            ->method('logOut');
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');
        $resource->expects($this->once())
            ->method('PageUrl')
            ->with('home')
            ->willReturn($redirectUrl);

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('redirectUrl', $result);
        $this->assertEquals($redirectUrl, $result['redirectUrl']);
    }

    #endregion onExecute

    #region ensureNotLoggedIn --------------------------------------------------

    function testEnsureNotLoggedInThrowsIfUserIsLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountView = $this->createStub(AccountView::class);
        $accountService = AccountService::Instance();

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
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $payload = [
            'credential' => 'cred1234'
        ];

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $this->assertEquals((object)$payload, ah::CallMethod($sut, 'validatePayload'));
    }

    #endregion validatePayload

    #region decodeProfile ------------------------------------------------------

    function testDecodeProfileThrowsIfCredentialDecodeFails()
    {
        $sut = $this->systemUnderTest('decodeCredential');

        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with('credential')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid credential.");
        ah::CallMethod($sut, 'decodeProfile', ['credential']);
    }

    function testDecodeProfileThrowsIfClaimsValidationFails()
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
        ah::CallMethod($sut, 'decodeProfile', ['credential']);
    }

    function testDecodeProfileSucceeds()
    {
        $sut = $this->systemUnderTest('decodeCredential', 'validateClaims');
        $claims = ['foo' => 'bar'];
        $profile = (object)['baz' => 'qux'];

        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with('credential')
            ->willReturn($claims);
        $sut->expects($this->once())
            ->method('validateClaims')
            ->with($claims)
            ->willReturn($profile);

        $this->assertSame(
            $profile,
            ah::CallMethod($sut, 'decodeProfile', ['credential'])
        );
    }

    #endregion decodeProfile

    #region decodeCredential ---------------------------------------------------

    function testDecodeCredentialReturnsNullIfClientSendFails()
    {
        $sut = $this->systemUnderTest();

        $this->client->expects($this->once())
            ->method('Url')
            ->with('https://oauth2.googleapis.com/tokeninfo?id_token=cred1234')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Send')
            ->willReturn(false);

        $claims = ah::CallMethod($sut, 'decodeCredential', ['cred1234']);
        $this->assertNull($claims);
    }

    function testDecodeCredentialReturnsNullIfClientStatusCodeIsNot200()
    {
        $sut = $this->systemUnderTest();

        $this->client->expects($this->once())
            ->method('Url')
            ->with('https://oauth2.googleapis.com/tokeninfo?id_token=cred1234')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Send')
            ->willReturn(true);
        $this->client->expects($this->once())
            ->method('StatusCode')
            ->willReturn(400);

        $claims = ah::CallMethod($sut, 'decodeCredential', ['cred1234']);
        $this->assertNull($claims);
    }

    function testDecodeCredentialReturnsNullIfClientBodyCannotBeDecoded()
    {
        $sut = $this->systemUnderTest();

        $this->client->expects($this->once())
            ->method('Url')
            ->with('https://oauth2.googleapis.com/tokeninfo?id_token=cred1234')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Send')
            ->willReturn(true);
        $this->client->expects($this->once())
            ->method('StatusCode')
            ->willReturn(200);
        $this->client->expects($this->once())
            ->method('Body')
            ->willReturn('{invalid');

        $claims = ah::CallMethod($sut, 'decodeCredential', ['cred1234']);
        $this->assertNull($claims);
    }

    function testDecodeCredentialSucceeds()
    {
        $sut = $this->systemUnderTest();

        $this->client->expects($this->once())
            ->method('Url')
            ->with('https://oauth2.googleapis.com/tokeninfo?id_token=cred1234')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Send')
            ->willReturn(true);
        $this->client->expects($this->once())
            ->method('StatusCode')
            ->willReturn(200);
        $this->client->expects($this->once())
            ->method('Body')
            ->willReturn('{"foo":"bar"}');

        $claims = ah::CallMethod($sut, 'decodeCredential', ['cred1234']);
        $this->assertSame(['foo' => 'bar'], $claims);
    }

    #endregion decodeCredential

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
        ah::CallMethod($sut, 'validateClaims', [$claims]);
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
        ah::CallMethod($sut, 'validateClaims', [$claims]);
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
        $profile = (object)[
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

        $this->assertEquals(
            $profile,
            ah::CallMethod($sut, 'validateClaims', [$claims])
        );
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

        $actual = ah::CallMethod($sut, 'normalizeDisplayName', [
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
        $profile = (object)[
            'email' => 'john@example.com',
            'displayName' => 'John'
        ];
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->never())
            ->method('constructAccount');

        $this->assertSame(
            $account,
            ah::CallMethod($sut, 'findOrConstructAccount', [$profile])
        );
    }

    function testFindOrConstructAccountWithNonExistingRecord()
    {
        $sut = $this->systemUnderTest('findAccount', 'constructAccount');
        $profile = (object)[
            'email' => 'john@example.com',
            'displayName' => 'John'
        ];
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn(null);
        $sut->expects($this->once())
            ->method('constructAccount')
            ->with($profile)
            ->willReturn($account);

        $this->assertSame(
            $account,
            ah::CallMethod($sut, 'findOrConstructAccount', [$profile])
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

    #region constructAccount ---------------------------------------------------

    function testConstructAccount()
    {
        $sut = $this->systemUnderTest();
        $profile = (object)[
            'email' => 'john@example.com',
            'displayName' => 'John'
        ];

        $account = ah::CallMethod($sut, 'constructAccount', [$profile]);
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(0, $account->id);
        $this->assertSame($profile->email, $account->email);
        $this->assertSame('', $account->passwordHash);
        $this->assertSame($profile->displayName, $account->displayName);
        $this->assertEqualsWithDelta(\time(), $account->timeActivated->getTimestamp(), 1);
        $this->assertNull($account->timeLastLogin);
    }

    #endregion constructAccount

    #region doLogIn ------------------------------------------------------------

    function testDoLogInThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $account->id = 42;
        $accountService = AccountService::Instance();

        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);
        $accountService->expects($this->never())
            ->method('CreateSession');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        ah::CallMethod($sut, 'doLogIn', [$account]);
    }

    function testDoLogInSucceeds()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $account->id = 42;
        $accountService = AccountService::Instance();

        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $accountService->expects($this->once())
            ->method('CreateSession')
            ->with($account->id, true);

        ah::CallMethod($sut, 'doLogIn', [$account]);
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

    static function invalidPayloadProvider()
    {
        return [
            'credential missing' => [
                'payload' => [],
                'exceptionMessage' => "Required field 'credential' is missing."
            ],
            'credential not a string' => [
                'payload' => [
                    'credential' => 42
                ],
                'exceptionMessage' => "Field 'credential' must be a string."
            ],
            'credential empty' => [
                'payload' => [
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
