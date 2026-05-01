<?php declare(strict_types=1);
namespace suite\Api\Actions\Account;

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
use \Peneus\Model\Account;
use \Peneus\Resource;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

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

    private function contextForOnExecute(
        bool $ensureNotLoggedInSucceeds = true,
        bool $validatePayloadSucceeds = true,
        bool $decodeProfileSucceeds = true,
        bool $doTransactionSucceeds = true,
        bool $deleteCsrfCookieSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'ensureNotLoggedIn',
            'validatePayload',
            'decodeProfile',
            'findOrMakeAccount',
            'doTransaction',
            'composeResult'
        );
        $payload = new \stdClass();
        $payload->credential = 'cred1234';
        $profile = new \stdClass();
        $account = $this->createStub(Account::class);
        $cookieService = CookieService::Instance();
        $ctx->result = ['redirectUrl' => new CUrl('https://example.com/home/')];

        $ctx->sut->expects($ctx->chain())
            ->method('ensureNotLoggedIn')
            ->willReturnCallback(fn() => $ensureNotLoggedInSucceeds
                ? null
                : throw new \RuntimeException('ENSURE_NOT_LOGGED_IN_FAILED'));
        $ctx->sut->expects($ctx->chainIf($ensureNotLoggedInSucceeds))
            ->method('validatePayload')
            ->willReturnCallback(fn() => $validatePayloadSucceeds
                ? $payload
                : throw new \RuntimeException('VALIDATE_PAYLOAD_FAILED'));
        $ctx->sut->expects($ctx->chainIf($validatePayloadSucceeds))
            ->method('decodeProfile')
            ->with($payload->credential)
            ->willReturnCallback(fn() => $decodeProfileSucceeds
                ? $profile
                : throw new \RuntimeException('DECODE_PROFILE_FAILED'));
        $ctx->sut->expects($ctx->chainIf($decodeProfileSucceeds))
            ->method('findOrMakeAccount')
            ->with($profile)
            ->willReturn($account);
        $ctx->sut->expects($ctx->chain())
            ->method('doTransaction')
            ->with($account)
            ->willReturnCallback(fn() => $doTransactionSucceeds
                ? null
                : throw new \RuntimeException('DO_TRANSACTION_FAILED'));
        $cookieService->expects($ctx->chainIf($doTransactionSucceeds))
            ->method('DeleteCsrfCookie')
            ->willReturnCallback(fn() => $deleteCsrfCookieSucceeds
                ? null
                : throw new \RuntimeException('DELETE_CSRF_COOKIE_FAILED'));
        $ctx->sut->expects($ctx->chainIf($deleteCsrfCookieSucceeds))
            ->method('composeResult')
            ->willReturn($ctx->result);

        return $ctx;
    }

    function testOnExecuteFailsIfEnsureNotLoggedInFails()
    {
        $ctx = $this->contextForOnExecute(ensureNotLoggedInSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ENSURE_NOT_LOGGED_IN_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfValidatePayloadFails()
    {
        $ctx = $this->contextForOnExecute(validatePayloadSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VALIDATE_PAYLOAD_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfDecodeProfileFails()
    {
        $ctx = $this->contextForOnExecute(decodeProfileSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DECODE_PROFILE_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfDoTransactionFails()
    {
        $ctx = $this->contextForOnExecute(doTransactionSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DO_TRANSACTION_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfDeleteCsrfCookieFails()
    {
        $ctx = $this->contextForOnExecute(deleteCsrfCookieSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DELETE_CSRF_COOKIE_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $ctx = $this->contextForOnExecute();
        $actual = ah::CallMethod($ctx->sut, 'onExecute');
        $this->assertSame($ctx->result, $actual);
    }

    #endregion onExecute

    #region validatePayload ----------------------------------------------------

    private function contextForValidatePayload(
        array $payload
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($ctx->chain())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($ctx->chain())
            ->method('ToArray')
            ->willReturn($payload);

        return $ctx;
    }

    #[DataProvider('invalidPayloadProvider')]
    function testValidatePayloadFails(array $payload)
    {
        $ctx = $this->contextForValidatePayload($payload);
        $this->expectException(\RuntimeException::class);
        ah::CallMethod($ctx->sut, 'validatePayload');
    }

    function testValidatePayloadSucceeds()
    {
        $payload = ['credential' => 'cred1234'];
        $ctx = $this->contextForValidatePayload($payload);
        $expected = (object)$payload;
        $actual = ah::CallMethod($ctx->sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region decodeProfile ------------------------------------------------------

    private function contextForDecodeProfile(
        bool $decodeCredentialSucceeds = true,
        bool $validateClientIdSucceeds = true,
        bool $validateClaimsSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'decodeCredential',
            'validateClientId',
            'validateClaims'
        );
        $ctx->credential = 'cred1234';
        $claims = ['foo' => 'bar'];
        $clientId = '1234567890.apps.googleusercontent.com';
        $ctx->profile = new \stdClass();
        $ctx->profile->email = 'john@example.com';
        $ctx->profile->displayName = 'John';

        $ctx->sut->expects($ctx->chain())
            ->method('decodeCredential')
            ->with($ctx->credential)
            ->willReturn($decodeCredentialSucceeds ? $claims : null);
        $ctx->sut->expects($ctx->chainIf($decodeCredentialSucceeds))
            ->method('validateClientId')
            ->willReturnCallback(fn() => $validateClientIdSucceeds
                ? $clientId
                : throw new \RuntimeException('VALIDATE_CLIENT_ID_FAILED'));
        $ctx->sut->expects($ctx->chainIf($validateClientIdSucceeds))
            ->method('validateClaims')
            ->with($claims, $clientId)
            ->willReturnCallback(fn() => $validateClaimsSucceeds
                ? $ctx->profile
                : throw new \RuntimeException('VALIDATE_CLAIMS_FAILED'));

        return $ctx;
    }

    function testDecodeProfileFailsIfDecodeCredentialFails()
    {
        $ctx = $this->contextForDecodeProfile(decodeCredentialSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid credential.");
        ah::CallMethod($ctx->sut, 'decodeProfile', [$ctx->credential]);
    }

    function testDecodeProfileFailsIfValidateClientIdFails()
    {
        $ctx = $this->contextForDecodeProfile(validateClientIdSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VALIDATE_CLIENT_ID_FAILED');
        ah::CallMethod($ctx->sut, 'decodeProfile', [$ctx->credential]);
    }

    function testDecodeProfileFailsIfValidateClaimsFails()
    {
        $ctx = $this->contextForDecodeProfile(validateClaimsSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VALIDATE_CLAIMS_FAILED');
        ah::CallMethod($ctx->sut, 'decodeProfile', [$ctx->credential]);
    }

    function testDecodeProfileSucceeds()
    {
        $ctx = $this->contextForDecodeProfile();
        $actual = ah::CallMethod($ctx->sut, 'decodeProfile', [$ctx->credential]);
        $this->assertSame($ctx->profile, $actual);
    }

    #endregion decodeProfile

    #region decodeCredential ---------------------------------------------------

    private function contextForDecodeCredential(
        bool $sendSucceeds = true,
        int $statusCode = 200,
        string $body = '{"foo":"bar"}'
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest();
        $ctx->credential = 'cred1234';
        $url = "https://oauth2.googleapis.com/tokeninfo?id_token={$ctx->credential}";

        $this->client->expects($ctx->chain())
            ->method('Url')
            ->with($url)
            ->willReturnSelf();
        $this->client->expects($ctx->chain())
            ->method('Send')
            ->willReturn($sendSucceeds);
        $this->client->expects($ctx->chainIf($sendSucceeds))
            ->method('StatusCode')
            ->willReturn($statusCode);
        $this->client->expects($ctx->chainIf($statusCode === 200))
            ->method('Body')
            ->willReturn($body);

        return $ctx;
    }

    function testDecodeCredentialFailsIfClientSendFails()
    {
        $ctx = $this->contextForDecodeCredential(sendSucceeds: false);
        $actual = ah::CallMethod($ctx->sut, 'decodeCredential', [$ctx->credential]);
        $this->assertNull($actual);
    }

    function testDecodeCredentialFailsIfClientStatusCodeIsNot200()
    {
        $ctx = $this->contextForDecodeCredential(statusCode: 400);
        $actual = ah::CallMethod($ctx->sut, 'decodeCredential', [$ctx->credential]);
        $this->assertNull($actual);
    }

    function testDecodeCredentialFailsIfClientBodyCannotBeDecoded()
    {
        $ctx = $this->contextForDecodeCredential(body: '{invalid');
        $actual = ah::CallMethod($ctx->sut, 'decodeCredential', [$ctx->credential]);
        $this->assertNull($actual);
    }

    function testDecodeCredentialSucceeds()
    {
        $ctx = $this->contextForDecodeCredential();
        $expected = ['foo' => 'bar'];
        $actual = ah::CallMethod($ctx->sut, 'decodeCredential', [$ctx->credential]);
        $this->assertSame($expected, $actual);
    }

    #endregion decodeCredential

    #region validateClientId ---------------------------------------------------

    private function contextForValidateClientId(
        ?string $clientId
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($ctx->chain())
            ->method('Option')
            ->with('Google.OAuth2.ClientID')
            ->willReturn($clientId);

        return $ctx;
    }

    #[DataProvider('invalidClientIdProvider')]
    function testValidateClientIdFails(?string $clientId)
    {
        $ctx = $this->contextForValidateClientId($clientId);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Missing or invalid Google OAuth 2.0 client ID.");
        ah::CallMethod($ctx->sut, 'validateClientId');
    }

    function testValidateClientIdSucceeds()
    {
        $clientId = '1234567890.apps.googleusercontent.com';
        $ctx = $this->contextForValidateClientId($clientId);
        $actual = ah::CallMethod($ctx->sut, 'validateClientId');
        $this->assertEquals($clientId, $actual);
    }

    #endregion validateClientId

    #region validateClaims -----------------------------------------------------

    private function contextForValidateClaims(
        array $claims,
        bool $isValid = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest('normalizeDisplayName');
        $ctx->clientId = '1234567890.apps.googleusercontent.com';
        $ctx->displayName = 'Display Name';

        $ctx->sut->expects($ctx->chainIf($isValid))
            ->method('normalizeDisplayName')
            ->with(
                $isValid ? $claims['name'] : '',
                $isValid ? $claims['email'] : '',
                $isValid ? $claims['sub'] : ''
            )
            ->willReturn($ctx->displayName);

        return $ctx;
    }

    #[DataProvider('invalidClaimsProvider')]
    function testValidateClaimsFails(array $claims)
    {
        $ctx = $this->contextForValidateClaims($claims, isValid: false);
        $this->expectException(\RuntimeException::class);
        ah::CallMethod($ctx->sut, 'validateClaims', [
            $claims,
            $ctx->clientId
        ]);
    }

    function testValidateClaimsSucceeds()
    {
        $claims = [
            'iss'   => 'https://accounts.google.com',
            'aud'   => '1234567890.apps.googleusercontent.com',
            'sub'   => '1234567890',
            'exp'   => '253402300799',
            'email_verified' => 'true',
            'email' => 'john@example.com',
            'name'  => 'John'
        ];
        $ctx = $this->contextForValidateClaims($claims);
        $expected = (object)[
            'email'       => $claims['email'],
            'displayName' => $ctx->displayName
        ];
        $actual = ah::CallMethod($ctx->sut, 'validateClaims', [
            $claims,
            $ctx->clientId
        ]);
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
        $actual = ah::CallMethod($sut, 'normalizeDisplayName', [
            $name,
            $email,
            $sub
        ]);
        $this->assertSame($expected, $actual);
    }

    #endregion normalizeDisplayName

    #region findOrMakeAccount --------------------------------------------------

    private function contextForFindOrMakeAccount(
        bool $entityExists = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest('tryFindAccountByEmail', 'makeAccount');
        $ctx->profile = new \stdClass();
        $ctx->profile->email = 'john@example.com';
        $ctx->existingEntity = $this->createStub(Account::class);
        $ctx->newEntity = $this->createStub(Account::class);

        $ctx->sut->expects($ctx->chain())
            ->method('tryFindAccountByEmail')
            ->with($ctx->profile->email)
            ->willReturn($entityExists ? $ctx->existingEntity : null);
        $ctx->sut->expects($ctx->chainIf(!$entityExists))
            ->method('makeAccount')
            ->with($ctx->profile)
            ->willReturn($ctx->newEntity);

        return $ctx;
    }

    function testFindOrMakeAccountReturnsExistingIfFound()
    {
        $ctx = $this->contextForFindOrMakeAccount(entityExists: true);
        $actual = ah::CallMethod($ctx->sut, 'findOrMakeAccount', [$ctx->profile]);
        $this->assertSame($ctx->existingEntity, $actual);
    }

    function testFindOrMakeAccountReturnsNewIfNotFound()
    {
        $ctx = $this->contextForFindOrMakeAccount(entityExists: false);
        $actual = ah::CallMethod($ctx->sut, 'findOrMakeAccount', [$ctx->profile]);
        $this->assertSame($ctx->newEntity, $actual);
    }

    #endregion findOrMakeAccount

    #region makeAccount --------------------------------------------------------

    function testMakeAccount()
    {
        $sut = $this->systemUnderTest();
        $email = 'john@example.com';
        $displayName = 'John';
        $profile = new \stdClass();
        $profile->email = $email;
        $profile->displayName = $displayName;

        $account = ah::CallMethod($sut, 'makeAccount', [$profile]);

        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(0,                  $account->id);
        $this->assertSame($email,             $account->email);
        $this->assertSame('',                 $account->passwordHash);
        $this->assertSame($displayName,       $account->displayName);
        $this->assertEqualsWithDelta(\time(), $account->timeActivated->getTimestamp(), 1);
        $this->assertNull(                    $account->timeLastLogin);
    }

    #endregion makeAccount

    #region doTransaction ------------------------------------------------------

    private function contextForDoTransaction(
        bool $isRegistering,
        bool $accountSaveSucceeds = true,
        bool $triggerActivationHooksSucceeds = true,
        bool $sessionCreateSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest('triggerActivationHooks', 'tryLogOut');
        $ctx->account = $this->createMock(Account::class);
        $ctx->account->id = $isRegistering ? 0 : 17;
        $database = Database::Instance();
        $accountService = AccountService::Instance();

        $database->expects($ctx->chain())
            ->method('WithTransaction')
            ->willReturnCallback(fn($callback) => $callback());
        $ctx->account->expects($ctx->chain())
            ->method('Save')
            ->willReturn($accountSaveSucceeds);
        $ctx->sut->expects($isRegistering
                ? $ctx->chainIf($accountSaveSucceeds)
                : $this->never())
            ->method('triggerActivationHooks')
            ->with($ctx->account)
            ->willReturnCallback(fn() => $triggerActivationHooksSucceeds
                ? null
                : throw new \RuntimeException('TRIGGER_ACTIVATION_HOOKS_FAILED'));
        $accountService->expects($ctx->chainIf($isRegistering
                ? $triggerActivationHooksSucceeds
                : $accountSaveSucceeds))
            ->method('CreateSession')
            ->with($ctx->account->id, true)
            ->willReturnCallback(fn() => $sessionCreateSucceeds
                ? null
                : throw new \RuntimeException('SESSION_CREATE_FAILED'));
        $ctx->update($sessionCreateSucceeds);
        $ctx->sut->expects($ctx->isFailed()
                ? $this->once()
                : $this->never())
            ->method('tryLogOut');

        return $ctx;
    }

    function testDoTransactionFailsIfAccountSaveFails()
    {
        $ctx = $this->contextForDoTransaction(
            isRegistering: false,
            accountSaveSucceeds: false
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->account]);
    }

    function testDoTransactionFailsIfTriggerActivationHooksFails()
    {
        $ctx = $this->contextForDoTransaction(
            isRegistering: true,
            triggerActivationHooksSucceeds: false
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TRIGGER_ACTIVATION_HOOKS_FAILED');
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->account]);
    }

    #[TestWith([false])]
    #[TestWith([true ])]
    function testDoTransactionFailsIfSessionCreateFails(bool $isRegistering)
    {
        $ctx = $this->contextForDoTransaction(
            isRegistering: $isRegistering,
            sessionCreateSucceeds: false
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SESSION_CREATE_FAILED');
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->account]);
    }

    #[TestWith([false])]
    #[TestWith([true ])]
    function testDoTransactionSucceeds(bool $isRegistering)
    {
        $ctx = $this->contextForDoTransaction(isRegistering: $isRegistering);
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->account]);
        $this->assertEqualsWithDelta(\time(), $ctx->account->timeLastLogin->getTimestamp(), 1);
    }

    #endregion doTransaction

    #region tryLogOut ----------------------------------------------------------

    #[TestWith([true ])]
    #[TestWith([false])]
    function testTryLogOut(bool $sessionDeleteSucceeds)
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('DeleteSession')
            ->willReturnCallback(fn() => $sessionDeleteSucceeds
                ? null
                : throw new \RuntimeException());

        ah::CallMethod($sut, 'tryLogOut');
    }

    #endregion tryLogOut

    #region composeResult ------------------------------------------------------

    function testComposeResult()
    {
        $sut = $this->systemUnderTest();
        $resource = Resource::Instance();
        $redirectUrl = new CUrl('https://example.com/home/');
        $expected = ['redirectUrl' => $redirectUrl];

        $resource->expects($this->once())
            ->method('PageUrl')
            ->with('home')
            ->willReturn($redirectUrl);

        $actual = ah::CallMethod($sut, 'composeResult');

        $this->assertSame($expected, $actual);
    }

    #endregion composeResult

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'credential.required' => [[
                // empty
            ]],
            'credential.string' => [[
                'credential' => ['not', 'a', 'string']
            ]],
            'credential.minLength' => [[
                'credential' => ''
            ]],
        ];
    }

    static function invalidClientIdProvider()
    {
        return [
            [null],
            [''],
            ['.apps.googleusercontent.com'],
            ['"!^+%.apps.googleusercontent.com']
        ];
    }

    static function invalidClaimsProvider()
    {
        return [
            'iss.required' => [[
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'iss.string' => [[
                'iss' => ['not', 'a', 'string'],
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'iss.custom' => [[
                'iss' => 'https://example.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'aud.required' => [[
                'iss' => 'https://accounts.google.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'aud.string' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => ['not', 'a', 'string'],
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'aud.custom' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '0987654321.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'sub.required' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'sub.string' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => ['not', 'a', 'string'],
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'sub.minLength' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'sub.maxLength' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => str_repeat('9', 256),
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'exp.required' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'exp.integer' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => 'not-an-integer',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'exp.custom' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => \time() - 5000,
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'email_verified.required' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'email_verified.string' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => true,
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'email_verified.custom' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'false',
                'email' => 'john@example.com',
                'name' => 'John'
            ]],
            'email.required' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'name' => 'John'
            ]],
            'email.email' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'not-an-email',
                'name' => 'John'
            ]],
            'name.required' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
            ]],
            'name.string' => [[
                'iss' => 'https://accounts.google.com',
                'aud' => '1234567890.apps.googleusercontent.com',
                'sub' => '1234567890',
                'exp' => '253402300799',
                'email_verified' => 'true',
                'email' => 'john@example.com',
                'name' => ['not', 'a', 'string']
            ]],
        ];
    }

    static function normalizeDisplayNameDataProvider()
    {
        return [
            'name matches pattern' => [
                'expected' => 'John',
                'name'     => 'John',
                'email'    => 'john@example.com',
                'sub'      => '1234567890'
            ],
            'name matches pattern, name has leading and trailing whitespace' => [
                'expected' => 'John',
                'name'     => '  John  ',
                'email'    => 'john@example.com',
                'sub'      => '1234567890'
            ],
            'name does not match pattern, email local part matches pattern' => [
                'expected' => 'john',
                'name'     => '<invalid-display-name>',
                'email'    => 'john@example.com',
                'sub'      => '1234567890'
            ],
            'name does not match pattern, email does not contain "@"' => [
                'expected' => 'User_1234567890',
                'name'     => '<invalid-display-name>',
                'email'    => 'invalid-email',
                'sub'      => '1234567890'
            ],
            'name does not match pattern, email local part does not match pattern' => [
                'expected' => 'User_1234567890',
                'name'     => '<invalid-display-name>',
                'email'    => '<invalid-email-local-part>@example.com',
                'sub'      => '1234567890'
            ],
        ];
    }

    #endregion Data Providers
}
