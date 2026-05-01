<?php declare(strict_types=1);
namespace suite\Api\Actions\Account;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\RegisterAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

#[CoversClass(RegisterAction::class)]
class RegisterActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Config $originalConfig = null;
    private ?Resource $originalResource = null;
    private ?SecurityService $originalSecurityService = null;
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
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        Config::ReplaceInstance($this->originalConfig);
        Resource::ReplaceInstance($this->originalResource);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): RegisterAction
    {
        return $this->getMockBuilder(RegisterAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    private function contextForOnExecute(
        bool $validatePayloadSucceeds = true,
        bool $ensureNotRegisteredSucceeds = true,
        bool $ensureNotPendingSucceeds = true,
        bool $doTransactionSucceeds = true,
        bool $deleteCsrfCookieSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'validatePayload',
            'ensureNotRegistered',
            'ensureNotPending',
            'doTransaction',
            'composeResult'
        );
        $email = 'john@example.com';
        $payload = new \stdClass();
        $payload->email = $email;
        $cookieService = CookieService::Instance();
        $ctx->result = ['message' => 'SUCCESS_MESSAGE'];

        $ctx->sut->expects($ctx->chain())
            ->method('validatePayload')
            ->willReturnCallback(fn() => $validatePayloadSucceeds
                ? $payload
                : throw new \RuntimeException('VALIDATE_PAYLOAD_FAILED'));
        $ctx->sut->expects($ctx->chainIf($validatePayloadSucceeds))
            ->method('ensureNotRegistered')
            ->with($email)
            ->willReturnCallback(fn() => $ensureNotRegisteredSucceeds
                ? null
                : throw new \RuntimeException('ENSURE_NOT_REGISTERED_FAILED'));
        $ctx->sut->expects($ctx->chainIf($ensureNotRegisteredSucceeds))
            ->method('ensureNotPending')
            ->with($email)
            ->willReturnCallback(fn() => $ensureNotPendingSucceeds
                ? null
                : throw new \RuntimeException('ENSURE_NOT_PENDING_FAILED'));
        $ctx->sut->expects($ctx->chainIf($ensureNotPendingSucceeds))
            ->method('doTransaction')
            ->with($payload)
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

    function testOnExecuteFailsIfValidatePayloadFails()
    {
        $ctx = $this->contextForOnExecute(validatePayloadSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VALIDATE_PAYLOAD_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfEnsureNotRegisteredFails()
    {
        $ctx = $this->contextForOnExecute(ensureNotRegisteredSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ENSURE_NOT_REGISTERED_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfEnsureNotPendingFails()
    {
        $ctx = $this->contextForOnExecute(ensureNotPendingSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ENSURE_NOT_PENDING_FAILED');
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
        $payload = [
            'email'       => 'john@example.com',
            'password'    => 'pass1234',
            'displayName' => 'John'
        ];
        $ctx = $this->contextForValidatePayload($payload);
        $expected = (object)$payload;
        $actual = ah::CallMethod($ctx->sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region doTransaction ------------------------------------------------------

    private function contextForDoTransaction(
        bool $pendingAccountSaveSucceeds = true,
        bool $sendEmailSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'makePendingAccount',
            'sendEmail'
        );
        $email = 'john@example.com';
        $displayName = 'John';
        $ctx->payload = new \stdClass();
        $ctx->payload->email = $email;
        $ctx->payload->displayName = $displayName;
        $database = Database::Instance();
        $securityService = SecurityService::Instance();
        $activationCode = 'code1234';
        $pendingAccount = $this->createMock(PendingAccount::class);

        $database->expects($ctx->chain())
            ->method('WithTransaction')
            ->willReturnCallback(fn($callback) => $callback());
        $securityService->expects($ctx->chain())
            ->method('GenerateToken')
            ->willReturn($activationCode);
        $ctx->sut->expects($ctx->chain())
            ->method('makePendingAccount')
            ->with($ctx->payload, $activationCode)
            ->willReturn($pendingAccount);
        $pendingAccount->expects($ctx->chain())
            ->method('Save')
            ->willReturn($pendingAccountSaveSucceeds);
        $ctx->sut->expects($ctx->chainIf($pendingAccountSaveSucceeds))
            ->method('sendEmail')
            ->with($email, $displayName, $activationCode)
            ->willReturnCallback(fn() => $sendEmailSucceeds
                ? null
                : throw new \RuntimeException('SEND_EMAIL_FAILED'));

        return $ctx;
    }

    function testDoTransactionFailsIfPendingAccountSaveFails()
    {
        $ctx = $this->contextForDoTransaction(pendingAccountSaveSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save pending account.");
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->payload]);
    }

    function testDoTransactionFailsIfSendEmailFails()
    {
        $ctx = $this->contextForDoTransaction(sendEmailSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SEND_EMAIL_FAILED');
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->payload]);
    }

    function testDoTransactionSucceeds()
    {
        $ctx = $this->contextForDoTransaction();
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->payload]);
    }

    #endregion doTransaction

    #region makePendingAccount -------------------------------------------------

    function testMakePendingAccount()
    {
        $sut = $this->systemUnderTest();
        $email = 'john@example.com';
        $password = 'pass1234';
        $passwordHash = 'hash1234';
        $displayName = 'John';
        $payload = new \stdClass();
        $payload->email = $email;
        $payload->password = $password;
        $payload->displayName = $displayName;
        $activationCode = 'code1234';
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with($password)
            ->willReturn($passwordHash);

        $pendingAccount = ah::CallMethod($sut, 'makePendingAccount', [
            $payload,
            $activationCode
        ]);

        $this->assertInstanceOf(PendingAccount::class, $pendingAccount);
        $this->assertSame(0,                  $pendingAccount->id);
        $this->assertSame($email,             $pendingAccount->email);
        $this->assertSame($passwordHash,      $pendingAccount->passwordHash);
        $this->assertSame($displayName,       $pendingAccount->displayName);
        $this->assertSame($activationCode,    $pendingAccount->activationCode);
        $this->assertEqualsWithDelta(\time(), $pendingAccount->timeRegistered->getTimestamp(), 1);
    }

    #endregion makePendingAccount

    #region sendEmail ----------------------------------------------------------

    private function contextForSendEmail(
        bool $succeeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest('sendTransactionalEmail');
        $config = Config::Instance();
        $resource = Resource::Instance();
        $ctx->email = 'john@example.com';
        $ctx->displayName = 'John';
        $ctx->activationCode = 'code1234';
        $appName = 'Example';
        $actionUrl = $this->createMock(CUrl::class);
        $substitutions = [
            'heroText' =>
                "Welcome to {$appName}!",
            'introText' =>
                "You're almost there! Just click the button below to"
              . " activate your account.",
            'buttonText' =>
                "Activate My Account",
            'disclaimerText' =>
                "You received this email because your email address was"
              . " used to register on {$appName}. If this wasn't you, you"
              . " can safely ignore this email."
        ];

        $config->expects($ctx->chain())
            ->method('Option')
            ->with('AppName')
            ->willReturn($appName);
        $resource->expects($ctx->chain())
            ->method('PageUrl')
            ->with('activate-account')
            ->willReturn($actionUrl);
        $actionUrl->expects($ctx->chain())
            ->method('Extend')
            ->with($ctx->activationCode)
            ->willReturnSelf();
        $ctx->sut->expects($ctx->chain())
            ->method('sendTransactionalEmail')
            ->with(
                $ctx->email,
                $ctx->displayName,
                $actionUrl,
                $substitutions
            )
            ->willReturn($succeeds);

        return $ctx;
    }

    function testSendEmailFails()
    {
        $ctx = $this->contextForSendEmail(succeeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to send email.");
        ah::CallMethod($ctx->sut, 'sendEmail', [
            $ctx->email,
            $ctx->displayName,
            $ctx->activationCode
        ]);
    }

    function testSendEmailSucceeds()
    {
        $ctx = $this->contextForSendEmail();
        ah::CallMethod($ctx->sut, 'sendEmail', [
            $ctx->email,
            $ctx->displayName,
            $ctx->activationCode
        ]);
    }

    #endregion sendEmail

    #region composeResult ------------------------------------------------------

    function testComposeResult()
    {
        $sut = $this->systemUnderTest();
        $expected = [
            'message' =>
                "An account activation link has been sent to your email address."
        ];
        $actual = ah::CallMethod($sut, 'composeResult');
        $this->assertSame($expected, $actual);
    }

    #endregion composeResult

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'email.required' => [[
                'password'    => 'pass1234',
                'displayName' => 'John'
            ]],
            'email.email' => [[
                'email'       => 'invalid-email',
                'password'    => 'pass1234',
                'displayName' => 'John'
            ]],
            'password.required' => [[
                'email'       => 'john@example.com',
                'displayName' => 'John'
            ]],
            'password.string' => [[
                'email'       => 'john@example.com',
                'password'    => ['not', 'a', 'string'],
                'displayName' => 'John'
            ]],
            'password.minLength' => [[
                'email'       => 'john@example.com',
                'password'    => \str_repeat('a', SecurityService::PASSWORD_MIN_LENGTH - 1),
                'displayName' => 'John'
            ]],
            'password.maxLength' => [[
                'email'       => 'john@example.com',
                'password'    => \str_repeat('a', SecurityService::PASSWORD_MAX_LENGTH + 1),
                'displayName' => 'John'
            ]],
            'displayName.required' => [[
                'email'    => 'john@example.com',
                'password' => 'pass1234'
            ]],
            'displayName.regex' => [[
                'email'       => 'john@example.com',
                'password'    => 'pass1234',
                'displayName' => '<invalid-display-name>'
            ]],
        ];
    }

    #endregion Data Providers
}
