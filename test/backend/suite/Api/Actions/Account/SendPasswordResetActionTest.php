<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Api\Actions\Account\SendPasswordResetAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Api\Hooks\ICaptchaHook;
use \Peneus\Model\Account;
use \Peneus\Model\PasswordReset;
use \Peneus\Resource;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(SendPasswordResetAction::class)]
class SendPasswordResetActionTest extends TestCase
{
    private ?ICaptchaHook $captchaHook = null;
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Config $originalConfig = null;
    private ?Resource $originalResource = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->captchaHook = $this->createMock(ICaptchaHook::class);
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
    }

    protected function tearDown(): void
    {
        $this->captchaHook = null;
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        Config::ReplaceInstance($this->originalConfig);
        Resource::ReplaceInstance($this->originalResource);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): SendPasswordResetAction
    {
        return $this->getMockBuilder(SendPasswordResetAction::class)
            ->setConstructorArgs([$this->captchaHook])
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfPayloadValidationFails()
    {
        $sut = $this->systemUnderTest('validatePayload');

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteBypassesDatabaseTransactionIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('validatePayload', 'tryFindAccountByEmail',
            'doSend');
        $payload = (object)[
            'email' => 'john@example.com'
        ];
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('tryFindAccountByEmail')
            ->with('john@example.com')
            ->willReturn(null);
        $database->expects($this->never())
            ->method('WithTransaction');
        $sut->expects($this->never())
            ->method('doSend');
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');

        $result = ah::CallMethod($sut, 'onExecute');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertSame(
            "A password reset link has been sent to your email address.",
            $result['message']
        );
    }

    function testOnExecuteThrowsIfDoSendFails()
    {
        $sut = $this->systemUnderTest('validatePayload', 'tryFindAccountByEmail',
            'doSend');
        $payload = (object)[
            'email' => 'john@example.com'
        ];
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('tryFindAccountByEmail')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doSend')
            ->with($account)
            ->willThrowException(new \RuntimeException());
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "We couldn't send the email. Please try again later.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('validatePayload', 'tryFindAccountByEmail',
            'doSend');
        $payload = (object)[
            'email' => 'john@example.com'
        ];
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('tryFindAccountByEmail')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doSend')
            ->with($account);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');

        $result = ah::CallMethod($sut, 'onExecute');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertSame(
            "A password reset link has been sent to your email address.",
            $result['message']
        );
    }

    #endregion onExecute

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
        $this->captchaHook->expects($this->never())
            ->method('OnVerifyCaptcha');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadThrowsIfCaptchaVerificationFails()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $payload = [
            'email' => 'john@example.com'
        ];

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);
        $this->captchaHook->expects($this->once())
            ->method('OnVerifyCaptcha')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $payload = [
            'email' => 'john@example.com'
        ];
        $expected = (object)$payload;

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);
        $this->captchaHook->expects($this->once())
            ->method('OnVerifyCaptcha');

        $this->assertEquals($expected, ah::CallMethod($sut, 'validatePayload'));
    }

    #endregion validatePayload

    #region doSend -------------------------------------------------------------

    function testDoSendThrowsIfPasswordResetSaveFails()
    {
        $sut = $this->systemUnderTest('findOrConstructPasswordReset', 'sendEmail');
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $securityService = SecurityService::Instance();
        $pr = $this->createMock(PasswordReset::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('findOrConstructPasswordReset')
            ->with(42)
            ->willReturn($pr);
        $pr->expects($this->once())
            ->method('Save')
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('sendEmail');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save password reset.");
        ah::CallMethod($sut, 'doSend', [$account]);
    }

    function testDoSendThrowsIfSendEmailFails()
    {
        $sut = $this->systemUnderTest('findOrConstructPasswordReset', 'sendEmail');
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $account->email = 'john@example.com';
        $account->displayName = 'John';
        $securityService = SecurityService::Instance();
        $pr = $this->createMock(PasswordReset::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('findOrConstructPasswordReset')
            ->with(42)
            ->willReturn($pr);
        $pr->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendEmail')
            ->with('john@example.com', 'John', 'code1234')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to send email.");
        ah::CallMethod($sut, 'doSend', [$account]);
    }

    function testDoSendSucceeds()
    {
        $sut = $this->systemUnderTest('findOrConstructPasswordReset', 'sendEmail');
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $account->email = 'john@example.com';
        $account->displayName = 'John';
        $securityService = SecurityService::Instance();
        $pr = $this->createMock(PasswordReset::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('findOrConstructPasswordReset')
            ->with(42)
            ->willReturn($pr);
        $pr->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendEmail')
            ->with('john@example.com', 'John', 'code1234')
            ->willReturn(true);

        ah::CallMethod($sut, 'doSend', [$account]);
    }

    #endregion doSend

    #region findOrConstructPasswordReset ---------------------------------------

    function testFindOrConstructPasswordResetWithExistingRecord()
    {
        $sut = $this->systemUnderTest('tryFindPasswordResetByAccountId',
            'constructPasswordReset');
        $pr = $this->createStub(PasswordReset::class);

        $sut->expects($this->once())
            ->method('tryFindPasswordResetByAccountId')
            ->with(42)
            ->willReturn($pr);
        $sut->expects($this->never())
            ->method('constructPasswordReset');

        $this->assertSame($pr, ah::CallMethod(
            $sut,
            'findOrConstructPasswordReset',
            [42]
        ));
    }

    function testFindOrConstructPasswordResetWithNonExistingRecord()
    {
        $sut = $this->systemUnderTest('tryFindPasswordResetByAccountId',
            'constructPasswordReset');
        $pr = $this->createStub(PasswordReset::class);

        $sut->expects($this->once())
            ->method('tryFindPasswordResetByAccountId')
            ->with(42)
            ->willReturn(null);
        $sut->expects($this->once())
            ->method('constructPasswordReset')
            ->with(42)
            ->willReturn($pr);

        $this->assertSame($pr, ah::CallMethod(
            $sut,
            'findOrConstructPasswordReset',
            [42]
        ));
    }

    #endregion findOrConstructPasswordReset

    #region constructPasswordReset ---------------------------------------------

    function testConstructPasswordReset()
    {
        $sut = $this->systemUnderTest();

        $pr = ah::CallMethod($sut, 'constructPasswordReset', [42]);
        $this->assertInstanceOf(PasswordReset::class, $pr);
        $this->assertSame(42, $pr->accountId);
    }

    #endregion constructPasswordReset

    #region sendEmail ----------------------------------------------------------

    #[TestWith([true])]
    #[TestWith([false])]
    function testSendEmail($returnValue)
    {
        $sut = $this->systemUnderTest('sendTransactionalEmail');
        $config = Config::Instance();
        $actionUrl = $this->createMock(CUrl::class);
        $resource = Resource::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('Example');
        $resource->expects($this->once())
            ->method('PageUrl')
            ->with('reset-password')
            ->willReturn($actionUrl);
        $actionUrl->expects($this->once())
            ->method('Extend')
            ->with('code1234')
            ->willReturnSelf();
        $actionUrl->expects($this->once())
            ->method('__toString')
            ->willReturn('https://example.com/pages/reset-password/code1234');
        $sut->expects($this->once())
            ->method('sendTransactionalEmail')
            ->with(
                'john@example.com',
                'John',
                'https://example.com/pages/reset-password/code1234',
                [
                    'heroText' =>
                        "Reset your password",
                    'introText' =>
                        "Follow the link below to choose a new password.",
                    'buttonText' =>
                        "Reset My Password",
                    'disclaimerText' =>
                        "You received this email because a password reset was"
                      . " requested for your account on Example. If you did"
                      . " not request this, you can safely ignore this email."
                ]
            )
            ->willReturn($returnValue);

        $this->assertSame(
            $returnValue,
            ah::CallMethod($sut, 'sendEmail', [
                'john@example.com',
                'John',
                'code1234'
            ])
        );
    }

    #endregion sendEmail

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
                'payload' => ['email' => 'invalid-email'],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ]
        ];
    }

    #endregion Data Providers
}
