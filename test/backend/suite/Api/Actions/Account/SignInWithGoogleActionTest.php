<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Account\SignInWithGoogleAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Harmonia\Systems\ValidationSystem\DataAccessor;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper;

#[CoversClass(SignInWithGoogleAction::class)]
class SignInWithGoogleActionTest extends TestCase
{
    private ?Database $originalDatabase = null;
    private ?AccountService $originalAccountService = null;
    private ?Request $originalRequest = null;
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
    }

    protected function tearDown(): void
    {
        Database::ReplaceInstance($this->originalDatabase);
        AccountService::ReplaceInstance($this->originalAccountService);
        Request::ReplaceInstance($this->originalRequest);
        Config::ReplaceInstance($this->originalConfig);
    }

    private function systemUnderTest(string ...$mockedMethods): SignInWithGoogleAction
    {
        return $this->getMockBuilder(SignInWithGoogleAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfAccountAlreadyLoggedIn()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'decodeCredential',
            'validateClaims',
            'logOut',
            'homePageUrl'
        );
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(true);
        $sut->expects($this->never())
            ->method('validateRequest');
        $sut->expects($this->never())
            ->method('decodeCredential');
        $sut->expects($this->never())
            ->method('validateClaims');
        $database->expects($this->never())
            ->method('WithTransaction');
        $sut->expects($this->never())
            ->method('logOut');
        $sut->expects($this->never())
            ->method('homePageUrl');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You are already logged in.');
        $this->expectExceptionCode(StatusCode::Conflict->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfValidateRequestFails()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'decodeCredential',
            'validateClaims',
            'logOut',
            'homePageUrl'
        );
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willThrowException(new \RuntimeException);
        $sut->expects($this->never())
            ->method('decodeCredential');
        $sut->expects($this->never())
            ->method('validateClaims');
        $database->expects($this->never())
            ->method('WithTransaction');
        $sut->expects($this->never())
            ->method('logOut');
        $sut->expects($this->never())
            ->method('homePageUrl');

        $this->expectException(\RuntimeException::class);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDecodeCredentialFails()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'decodeCredential',
            'validateClaims',
            'logOut',
            'homePageUrl'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $credential = 'cred1234';
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('credential')
            ->willReturn($credential);
        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with($credential)
            ->willReturn(null);
        $sut->expects($this->never())
            ->method('validateClaims');
        $database->expects($this->never())
            ->method('WithTransaction');
        $sut->expects($this->never())
            ->method('logOut');
        $sut->expects($this->never())
            ->method('homePageUrl');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid credential.');
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfValidateClaimsFails()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'decodeCredential',
            'validateClaims',
            'logOut',
            'homePageUrl'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $credential = 'cred1234';
        $claims = ['key' => 'value'];
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('credential')
            ->willReturn($credential);
        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with($credential)
            ->willReturn($claims);
        $sut->expects($this->once())
            ->method('validateClaims')
            ->with($claims)
            ->willReturn(false);
        $database->expects($this->never())
            ->method('WithTransaction');
        $sut->expects($this->never())
            ->method('logOut');
        $sut->expects($this->never())
            ->method('homePageUrl');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid claims.');
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest(
            'isAccountLoggedIn',
            'validateRequest',
            'decodeCredential',
            'validateClaims',
            'findOrCreateAccount',
            'establishSessionIntegrity',
            'deleteCsrfCookie',
            'logOut',
            'homePageUrl'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $credential = 'cred1234';
        $claims = ['email' => 'john@example.com', 'name' => 'John Doe'];
        $database = Database::Instance();
        $account = $this->createMock(Account::class);

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('credential')
            ->willReturn($credential);
        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with($credential)
            ->willReturn($claims);
        $sut->expects($this->once())
            ->method('validateClaims')
            ->with($claims)
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
        $sut->expects($this->once())
            ->method('findOrCreateAccount')
            ->with($claims['email'], $claims['name'])
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('establishSessionIntegrity');
        $sut->expects($this->never())
            ->method('deleteCsrfCookie');
        $sut->expects($this->once())
            ->method('logOut');
        $sut->expects($this->never())
            ->method('homePageUrl');

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
            'decodeCredential',
            'validateClaims',
            'findOrCreateAccount',
            'establishSessionIntegrity',
            'deleteCsrfCookie',
            'logOut',
            'homePageUrl'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $credential = 'cred1234';
        $claims = ['email' => 'john@example.com', 'name' => 'John Doe'];
        $database = Database::Instance();
        $account = $this->createMock(Account::class);

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('credential')
            ->willReturn($credential);
        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with($credential)
            ->willReturn($claims);
        $sut->expects($this->once())
            ->method('validateClaims')
            ->with($claims)
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
        $sut->expects($this->once())
            ->method('findOrCreateAccount')
            ->with($claims['email'], $claims['name'])
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('establishSessionIntegrity')
            ->with($account)
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('deleteCsrfCookie');
        $sut->expects($this->once())
            ->method('logOut');
        $sut->expects($this->never())
            ->method('homePageUrl');

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
            'decodeCredential',
            'validateClaims',
            'findOrCreateAccount',
            'establishSessionIntegrity',
            'deleteCsrfCookie',
            'logOut',
            'homePageUrl'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $credential = 'cred1234';
        $claims = ['email' => 'john@example.com', 'name' => 'John Doe'];
        $database = Database::Instance();
        $account = $this->createMock(Account::class);

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('credential')
            ->willReturn($credential);
        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with($credential)
            ->willReturn($claims);
        $sut->expects($this->once())
            ->method('validateClaims')
            ->with($claims)
            ->willReturn(true);
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
            ->method('findOrCreateAccount')
            ->with($claims['email'], $claims['name'])
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('establishSessionIntegrity')
            ->with($account)
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('deleteCsrfCookie')
            ->willThrowException(new \RuntimeException);
        $sut->expects($this->once())
            ->method('logOut');
        $sut->expects($this->never())
            ->method('homePageUrl');

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
            'decodeCredential',
            'validateClaims',
            'findOrCreateAccount',
            'establishSessionIntegrity',
            'deleteCsrfCookie',
            'logOut',
            'homePageUrl'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $credential = 'cred1234';
        $claims = ['email' => 'john@example.com', 'name' => 'John Doe'];
        $database = Database::Instance();
        $account = $this->createMock(Account::class);
        $homePageUrl = 'url/to/home';

        $sut->expects($this->once())
            ->method('isAccountLoggedIn')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('credential')
            ->willReturn($credential);
        $sut->expects($this->once())
            ->method('decodeCredential')
            ->with($credential)
            ->willReturn($claims);
        $sut->expects($this->once())
            ->method('validateClaims')
            ->with($claims)
            ->willReturn(true);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });
        $sut->expects($this->once())
            ->method('findOrCreateAccount')
            ->with($claims['email'], $claims['name'])
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('establishSessionIntegrity')
            ->with($account)
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('deleteCsrfCookie');
        $sut->expects($this->never())
            ->method('logOut');
        $sut->expects($this->once())
            ->method('homePageUrl')
            ->willReturn($homePageUrl);

        $result = AccessHelper::CallMethod($sut, 'onExecute');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('redirectUrl', $result);
        $this->assertSame($homePageUrl, $result['redirectUrl']);
    }

    #endregion onExecute

    #region isAccountLoggedIn --------------------------------------------------

    function testIsAccountLoggedInSucceedsIfAccountIsLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();
        $account = $this->createStub(Account::class);

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);

        $result = AccessHelper::CallMethod($sut, 'isAccountLoggedIn');
        $this->assertTrue($result);
    }

    function testIsAccountLoggedInFailsIfAccountIsNotLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn(null);

        $result = AccessHelper::CallMethod($sut, 'isAccountLoggedIn');
        $this->assertFalse($result);
    }

    #endregion isAccountLoggedIn

    #region validateRequest ----------------------------------------------------

    function testValidateRequestThrowsIfCredentialIsMissing()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([]);

        $this->expectException(\RuntimeException::class);
        AccessHelper::CallMethod($sut, 'validateRequest');
    }

    function testValidateRequestThrowsIfCredentialIsNotString()
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
                'credential' => 1234
            ]);

        $this->expectException(\RuntimeException::class);
        AccessHelper::CallMethod($sut, 'validateRequest');
    }

    function testValidateRequestThrowsIfCredentialIsEmptyString()
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
                'credential' => ''
            ]);

        $this->expectException(\RuntimeException::class);
        AccessHelper::CallMethod($sut, 'validateRequest');
    }

    function testValidateRequestSucceedsIfCredentialIsNonEmptyString()
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
                'credential' => '1234'
            ]);

        $result = AccessHelper::CallMethod($sut, 'validateRequest');
        $this->assertInstanceOf(DataAccessor::class, $result);
    }

    #endregion validateRequest

    #region validateClaims -----------------------------------------------------

    function testValidateClaimsFailsIfValidateIssuerFails()
    {
        $sut = $this->systemUnderTest(
            'validateIssuer',
            'validateAudience',
            'validateTimeWindow',
            'validateEmailVerified'
        );
        $claims = ['iss' => 'https://example.com'];

        $sut->expects($this->once())
            ->method('validateIssuer')
            ->with('https://example.com')
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('validateAudience');
        $sut->expects($this->never())
            ->method('validateTimeWindow');
        $sut->expects($this->never())
            ->method('validateEmailVerified');

        $result = AccessHelper::CallMethod($sut, 'validateClaims', [$claims]);
        $this->assertFalse($result);
    }

    function testValidateClaimsFailsIfValidateAudienceFails()
    {
        $sut = $this->systemUnderTest(
            'validateIssuer',
            'validateAudience',
            'validateTimeWindow',
            'validateEmailVerified'
        );
        $claims = [
            'iss' => 'https://accounts.google.com',
            'azp' => 'invalid-azp',
            'aud' => 'invalid-aud'
        ];

        $sut->expects($this->once())
            ->method('validateIssuer')
            ->with('https://accounts.google.com')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('validateAudience')
            ->with('invalid-azp', 'invalid-aud')
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('validateTimeWindow');
        $sut->expects($this->never())
            ->method('validateEmailVerified');

        $result = AccessHelper::CallMethod($sut, 'validateClaims', [$claims]);
        $this->assertFalse($result);
    }

    function testValidateClaimsFailsIfValidateTimeWindowFails()
    {
        $sut = $this->systemUnderTest(
            'validateIssuer',
            'validateAudience',
            'validateTimeWindow',
            'validateEmailVerified'
        );
        $claims = [
            'iss' => 'https://accounts.google.com',
            'azp' => 'valid-azp',
            'aud' => 'valid-aud',
            'nbf' => 'invalid-nbf',
            'exp' => 'invalid-exp'
        ];

        $sut->expects($this->once())
            ->method('validateIssuer')
            ->with('https://accounts.google.com')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('validateAudience')
            ->with('valid-azp', 'valid-aud')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('validateTimeWindow')
            ->with('invalid-nbf', 'invalid-exp')
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('validateEmailVerified');

        $result = AccessHelper::CallMethod($sut, 'validateClaims', [$claims]);
        $this->assertFalse($result);
    }

    function testValidateClaimsFailsIfValidateEmailVerifiedFails()
    {
        $sut = $this->systemUnderTest(
            'validateIssuer',
            'validateAudience',
            'validateTimeWindow',
            'validateEmailVerified'
        );
        $claims = [
            'iss' => 'https://accounts.google.com',
            'azp' => 'valid-azp',
            'aud' => 'valid-aud',
            'nbf' => 'valid-nbf',
            'exp' => 'valid-exp',
            'email_verified' => 'invalid-email-verified'
        ];

        $sut->expects($this->once())
            ->method('validateIssuer')
            ->with('https://accounts.google.com')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('validateAudience')
            ->with('valid-azp', 'valid-aud')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('validateTimeWindow')
            ->with('valid-nbf', 'valid-exp')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('validateEmailVerified')
            ->with('invalid-email-verified')
            ->willReturn(false);

        $result = AccessHelper::CallMethod($sut, 'validateClaims', [$claims]);
        $this->assertFalse($result);
    }

    #endregion validateClaims

    #region validateIssuer -----------------------------------------------------

    function testValidateIssuerFailsIfIssuerIsNotGoogle()
    {
        $sut = $this->systemUnderTest();
        $issuer = 'https://example.com';

        $result = AccessHelper::CallMethod($sut, 'validateIssuer', [$issuer]);
        $this->assertFalse($result);
    }

    function testValidateIssuerSucceedsIfIssuerIsGoogleWithScheme()
    {
        $sut = $this->systemUnderTest();
        $issuer = 'https://accounts.google.com';

        $result = AccessHelper::CallMethod($sut, 'validateIssuer', [$issuer]);
        $this->assertTrue($result);
    }

    function testValidateIssuerSucceedsIfIssuerIsGoogleWithoutScheme()
    {
        $sut = $this->systemUnderTest();
        $issuer = 'accounts.google.com';

        $result = AccessHelper::CallMethod($sut, 'validateIssuer', [$issuer]);
        $this->assertTrue($result);
    }

    #endregion validateIssuer

    #region validateAudience ---------------------------------------------------

    function testValidateAudienceFailsIfAuthorizedPartyIsInvalid()
    {
        $sut = $this->systemUnderTest();
        $authorizedParty = 'invalid-client-id';
        $audience = 'valid-client-id';
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('Option')
            ->with('Google.Auth.ClientID')
            ->willReturn('valid-client-id');

        $result = AccessHelper::CallMethod($sut, 'validateAudience', [
            $authorizedParty,
            $audience
        ]);
        $this->assertFalse($result);
    }

    function testValidateAudienceFailsIfAudienceIsInvalid()
    {
        $sut = $this->systemUnderTest();
        $authorizedParty = 'valid-client-id';
        $audience = 'invalid-client-id';
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('Option')
            ->with('Google.Auth.ClientID')
            ->willReturn('valid-client-id');

        $result = AccessHelper::CallMethod($sut, 'validateAudience', [
            $authorizedParty,
            $audience
        ]);
        $this->assertFalse($result);
    }

    function testValidateAudienceSucceedsIfAuthorizedPartyAndAudienceAreValid()
    {
        $sut = $this->systemUnderTest();
        $authorizedParty = 'valid-client-id';
        $audience = 'valid-client-id';
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('Option')
            ->with('Google.Auth.ClientID')
            ->willReturn('valid-client-id');

        $result = AccessHelper::CallMethod($sut, 'validateAudience', [
            $authorizedParty,
            $audience
        ]);
        $this->assertTrue($result);
    }

    #endregion validateAudience

    #region validateTimeWindow -------------------------------------------------

    function testValidateTimeWindowFailsIfNowIsLessThanNotBefore()
    {
        $sut = $this->systemUnderTest();
        $notBefore = '1000';
        $expiry = '2000';

        $result = AccessHelper::CallMethod($sut, 'validateTimeWindow', [
            $notBefore,
            $expiry,
            999
        ]);
        $this->assertFalse($result);
    }

    function testValidateTimeWindowFailsIfNowIsGreaterThanExpiry()
    {
        $sut = $this->systemUnderTest();
        $notBefore = '1000';
        $expiry = '2000';

        $result = AccessHelper::CallMethod($sut, 'validateTimeWindow', [
            $notBefore,
            $expiry,
            2001
        ]);
        $this->assertFalse($result);
    }

    function testValidateTimeWindowSucceedsIfNowIsBetweenNotBeforeAndExpiry()
    {
        $sut = $this->systemUnderTest();
        $notBefore = '1000';
        $expiry = '2000';

        $result = AccessHelper::CallMethod($sut, 'validateTimeWindow', [
            $notBefore,
            $expiry,
            1500
        ]);
        $this->assertTrue($result);
    }

    #endregion validateTimeWindow

    #region validateEmailVerified ----------------------------------------------

    function testValidateEmailVerifiedFailsIfEmailVerifiedIsInvalid()
    {
        $sut = $this->systemUnderTest();
        $emailVerified = ['false', false, true];

        foreach ($emailVerified as $value) {
            $result = AccessHelper::CallMethod($sut, 'validateEmailVerified', [
                $value
            ]);
            $this->assertFalse($result);
        }
    }

    function testValidateEmailVerifiedSucceedsIfEmailVerifiedIsValid()
    {
        $sut = $this->systemUnderTest();
        $emailVerified = 'true';

        $result = AccessHelper::CallMethod($sut, 'validateEmailVerified', [
            $emailVerified
        ]);
        $this->assertTrue($result);
    }

    #endregion validateEmailVerified

    #region findOrCreateAccount ------------------------------------------------

    function testFindOrCreateAccountWithExistingAccount()
    {
        $sut = $this->systemUnderTest(
            'findAccount',
            'createAccount'
        );
        $email = 'john@example.com';
        $displayName = 'John Doe';
        $timeLastLogin = new \DateTime();
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('findAccount')
            ->with($email)
            ->willReturn($account);
        $sut->expects($this->never())
            ->method('createAccount');

        $account = AccessHelper::CallMethod($sut, 'findOrCreateAccount', [
            $email,
            $displayName,
            $timeLastLogin
        ]);

        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame($timeLastLogin, $account->timeLastLogin);
    }

    function testFindOrCreateAccountWithNonExistingAccount()
    {
        $sut = $this->systemUnderTest(
            'findAccount',
            'createAccount'
        );
        $email = 'john@example.com';
        $displayName = 'John Doe';
        $timeLastLogin = new \DateTime();
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('findAccount')
            ->with($email)
            ->willReturn(null);
        $sut->expects($this->once())
            ->method('createAccount')
            ->with($email, $displayName)
            ->willReturn($account);

        $account = AccessHelper::CallMethod($sut, 'findOrCreateAccount', [
            $email,
            $displayName,
            $timeLastLogin
        ]);

        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame($timeLastLogin, $account->timeLastLogin);
    }

    #endregion findOrCreateAccount

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
                'displayName' => 'John Doe',
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
        $this->assertSame('John Doe', $account->displayName);
        $this->assertSame('2024-01-01 00:00:00',
            $account->timeActivated->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-01 00:00:00',
            $account->timeLastLogin->format('Y-m-d H:i:s'));
    }

    #endregion findAccount

    #region createAccount ------------------------------------------------------

    function testCreateAccount()
    {
        $sut = $this->systemUnderTest();
        $timeActivated = new \DateTime();

        $account = AccessHelper::CallMethod(
            $sut,
            'createAccount',
            ['john@example.com', 'John Doe', $timeActivated]
        );
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('', $account->passwordHash);
        $this->assertSame('John Doe', $account->displayName);
        $this->assertSame($timeActivated->format('c'),
                          $account->timeActivated->format('c'));
        $this->assertNull($account->timeLastLogin);
    }

    #endregion createAccount
}
