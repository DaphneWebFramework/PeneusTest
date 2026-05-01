<?php declare(strict_types=1);
namespace suite\Api\Actions\Account;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\SendPasswordResetAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\Account;
use \Peneus\Model\PasswordReset;
use \Peneus\Resource;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

#[CoversClass(SendPasswordResetAction::class)]
class SendPasswordResetActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): SendPasswordResetAction
    {
        return $this->getMockBuilder(SendPasswordResetAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    private function contextForOnExecute(
        bool $validatePayloadSucceeds = true,
        bool $doTransactionSucceeds = true,
        bool $deleteCsrfCookieSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'validatePayload',
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
            'email' => 'john@example.com'
        ];
        $ctx = $this->contextForValidatePayload($payload);
        $expected = (object)$payload;
        $actual = ah::CallMethod($ctx->sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region doTransaction ------------------------------------------------------

    private function contextForDoTransaction(
        bool $accountFound = true,
        bool $accountIsThirdParty = false,
        bool $passwordResetSaveSucceeds = true,
        bool $sendEmailSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'tryFindAccountByEmail',
            'isThirdPartyAccount',
            'findOrMakePasswordReset',
            'sendEmail'
        );
        $email = 'john@example.com';
        $ctx->payload = new \stdClass();
        $ctx->payload->email = $email;
        $accountId = 17;
        $displayName = 'John';
        $account = $this->createStub(Account::class);
        $account->id = $accountId;
        $account->email = $email;
        $account->displayName = $displayName;
        $database = Database::Instance();
        $securityService = SecurityService::Instance();
        $resetCode = 'code1234';
        $passwordReset = $this->createMock(PasswordReset::class);

        $ctx->sut->expects($ctx->chain())
            ->method('tryFindAccountByEmail')
            ->with($email)
            ->willReturn($accountFound ? $account : null);
        $ctx->sut->expects($ctx->chainIf($accountFound))
            ->method('isThirdPartyAccount')
            ->with($account)
            ->willReturn($accountIsThirdParty);
        $database->expects($ctx->chainIf(!$accountIsThirdParty))
            ->method('WithTransaction')
            ->willReturnCallback(fn($callback) => $callback());
        $securityService->expects($ctx->chain())
            ->method('GenerateToken')
            ->willReturn($resetCode);
        $ctx->sut->expects($ctx->chain())
            ->method('findOrMakePasswordReset')
            ->with($accountId)
            ->willReturn($passwordReset);
        $passwordReset->expects($ctx->chain())
            ->method('Save')
            ->willReturn($passwordResetSaveSucceeds);
        $ctx->sut->expects($ctx->chainIf($passwordResetSaveSucceeds))
            ->method('sendEmail')
            ->with($email, $displayName, $resetCode)
            ->willReturnCallback(fn() => $sendEmailSucceeds
                ? null
                : throw new \RuntimeException('SEND_EMAIL_FAILED'));

        return $ctx;
    }

    function testDoTransactionSkipsIfAccountNotFound()
    {
        $ctx = $this->contextForDoTransaction(accountFound: false);
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->payload]);
    }

    function testDoTransactionSkipsIfAccountIsThirdParty()
    {
        $ctx = $this->contextForDoTransaction(accountIsThirdParty: true);
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->payload]);
    }

    function testDoTransactionFailsIfPasswordResetSaveFails()
    {
        $ctx = $this->contextForDoTransaction(passwordResetSaveSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save password reset.");
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

    #region isThirdPartyAccount ------------------------------------------------

    function testIsThirdPartyAccountReturnsFalseIfAccountHasPasswordHash()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createStub(Account::class);
        $account->passwordHash = 'hash1234';
        $this->assertFalse(ah::CallMethod($sut, 'isThirdPartyAccount', [$account]));
    }

    function testIsThirdPartyAccountReturnsTrueIfAccountHasNoPasswordHash()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createStub(Account::class);
        $account->passwordHash = '';
        $this->assertTrue(ah::CallMethod($sut, 'isThirdPartyAccount', [$account]));
    }

    #endregion isThirdPartyAccount

    #region findOrMakePasswordReset --------------------------------------------

    private function contextForFindOrMakePasswordReset(
        bool $entityExists = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'tryFindPasswordResetByAccountId',
            'makePasswordReset'
        );
        $ctx->accountId = 17;
        $ctx->existingEntity = $this->createStub(PasswordReset::class);
        $ctx->newEntity = $this->createStub(PasswordReset::class);

        $ctx->sut->expects($ctx->chain())
            ->method('tryFindPasswordResetByAccountId')
            ->with($ctx->accountId)
            ->willReturn($entityExists ? $ctx->existingEntity : null);
        $ctx->sut->expects($ctx->chainIf(!$entityExists))
            ->method('makePasswordReset')
            ->with($ctx->accountId)
            ->willReturn($ctx->newEntity);

        return $ctx;
    }

    function testFindOrMakePasswordResetReturnsExistingIfFound()
    {
        $ctx = $this->contextForFindOrMakePasswordReset(entityExists: true);
        $actual = ah::CallMethod($ctx->sut, 'findOrMakePasswordReset', [$ctx->accountId]);
        $this->assertSame($ctx->existingEntity, $actual);
    }

    function testFindOrMakePasswordResetReturnsNewIfNotFound()
    {
        $ctx = $this->contextForFindOrMakePasswordReset(entityExists: false);
        $actual = ah::CallMethod($ctx->sut, 'findOrMakePasswordReset', [$ctx->accountId]);
        $this->assertSame($ctx->newEntity, $actual);
    }

    #endregion findOrMakePasswordReset

    #region makePasswordReset --------------------------------------------------

    function testMakePasswordReset()
    {
        $sut = $this->systemUnderTest();
        $accountId = 17;

        $passwordReset = ah::CallMethod($sut, 'makePasswordReset', [$accountId]);

        $this->assertInstanceOf(PasswordReset::class, $passwordReset);
        $this->assertSame(0,                  $passwordReset->id);
        $this->assertSame($accountId,         $passwordReset->accountId);
        $this->assertSame('',                 $passwordReset->resetCode);
        $this->assertEqualsWithDelta(\time(), $passwordReset->timeRequested->getTimestamp(), 1);
    }

    #endregion makePasswordReset

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
        $ctx->resetCode = 'code1234';
        $appName = 'Example';
        $actionUrl = $this->createMock(CUrl::class);
        $substitutions = [
            'heroText' =>
                "Reset your password",
            'introText' =>
                "Follow the link below to choose a new password.",
            'buttonText' =>
                "Reset My Password",
            'disclaimerText' =>
                "You received this email because a password reset was"
              . " requested for your account on {$appName}. If you did"
              . " not request this, you can safely ignore this email."
        ];

        $config->expects($ctx->chain())
            ->method('Option')
            ->with('AppName')
            ->willReturn($appName);
        $resource->expects($ctx->chain())
            ->method('PageUrl')
            ->with('reset-password')
            ->willReturn($actionUrl);
        $actionUrl->expects($ctx->chain())
            ->method('Extend')
            ->with($ctx->resetCode)
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
            $ctx->resetCode
        ]);
    }

    function testSendEmailSucceeds()
    {
        $ctx = $this->contextForSendEmail();
        ah::CallMethod($ctx->sut, 'sendEmail', [
            $ctx->email,
            $ctx->displayName,
            $ctx->resetCode
        ]);
    }

    #endregion sendEmail

    #region composeResult ------------------------------------------------------

    function testComposeResult()
    {
        $sut = $this->systemUnderTest();
        $expected = [
            'message' =>
                "A password reset link has been sent to your email address."
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
                // empty
            ]],
            'email.email' => [[
                'email' => 'invalid-email'
            ]],
        ];
    }

    #endregion Data Providers
}
