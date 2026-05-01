<?php declare(strict_types=1);
namespace suite\Api\Actions\Account;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\ResetPasswordAction;

use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\Account;
use \Peneus\Model\PasswordReset;
use \Peneus\Resource;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

#[CoversClass(ResetPasswordAction::class)]
class ResetPasswordActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Resource $originalResource = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
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
        Resource::ReplaceInstance($this->originalResource);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): ResetPasswordAction
    {
        return $this->getMockBuilder(ResetPasswordAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    private function contextForOnExecute(
        bool $validatePayloadSucceeds = true,
        bool $findAccountAndPasswordResetSucceeds = true,
        bool $doTransactionSucceeds = true,
        bool $deleteCsrfCookieSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'validatePayload',
            'findAccountAndPasswordReset',
            'doTransaction',
            'composeResult'
        );
        $payload = new \stdClass();
        $payload->resetCode = 'code1234';
        $payload->newPassword = 'pass5678';
        $account = $this->createStub(Account::class);
        $passwordReset = $this->createStub(PasswordReset::class);
        $cookieService = CookieService::Instance();
        $ctx->result = ['redirectUrl' => new CUrl('https://example.com/login')];

        $ctx->sut->expects($ctx->chain())
            ->method('validatePayload')
            ->willReturnCallback(fn() => $validatePayloadSucceeds
                ? $payload
                : throw new \RuntimeException('VALIDATE_PAYLOAD_FAILED'));
        $ctx->sut->expects($ctx->chainIf($validatePayloadSucceeds))
            ->method('findAccountAndPasswordReset')
            ->with($payload->resetCode)
            ->willReturnCallback(fn() => $findAccountAndPasswordResetSucceeds
                ? [$account, $passwordReset]
                : throw new \RuntimeException('FIND_ACCOUNT_AND_PASSWORD_RESET_FAILED'));
        $ctx->sut->expects($ctx->chainIf($findAccountAndPasswordResetSucceeds))
            ->method('doTransaction')
            ->with($payload->newPassword, $account, $passwordReset)
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

    function testOnExecuteFailsIfFindAccountAndPasswordResetFails()
    {
        $ctx = $this->contextForOnExecute(findAccountAndPasswordResetSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FIND_ACCOUNT_AND_PASSWORD_RESET_FAILED');
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
            'resetCode'   => \str_repeat('0123456789AbCdEf', 4),
            'newPassword' => 'pass5678'
        ];
        $ctx = $this->contextForValidatePayload($payload);
        $expected = (object)$payload;
        $actual = ah::CallMethod($ctx->sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region findAccountAndPasswordReset ----------------------------------------

    private function contextForFindAccountAndPasswordReset(
        bool $passwordResetFound = true,
        bool $accountFound = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'tryFindPasswordResetByCode',
            'tryFindAccountById'
        );
        $ctx->resetCode = 'code1234';
        $ctx->passwordReset = $this->createStub(PasswordReset::class);
        $ctx->passwordReset->accountId = 17;
        $ctx->account = $this->createStub(Account::class);

        $ctx->sut->expects($ctx->chain())
            ->method('tryFindPasswordResetByCode')
            ->with($ctx->resetCode)
            ->willReturn($passwordResetFound ? $ctx->passwordReset : null);
        $ctx->sut->expects($ctx->chainIf($passwordResetFound))
            ->method('tryFindAccountById')
            ->with($ctx->passwordReset->accountId)
            ->willReturn($accountFound ? $ctx->account : null);

        return $ctx;
    }

    function testFindAccountAndPasswordResetFailsIfPasswordResetNotFound()
    {
        $ctx = $this->contextForFindAccountAndPasswordReset(passwordResetFound: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("This password reset request is no longer valid.");
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($ctx->sut, 'findAccountAndPasswordReset', [$ctx->resetCode]);
    }

    function testFindAccountAndPasswordResetFailsIfAccountNotFound()
    {
        $ctx = $this->contextForFindAccountAndPasswordReset(accountFound: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("This password reset request is no longer valid.");
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($ctx->sut, 'findAccountAndPasswordReset', [$ctx->resetCode]);
    }

    function testFindAccountAndPasswordResetSucceeds()
    {
        $ctx = $this->contextForFindAccountAndPasswordReset();
        $actual = ah::CallMethod($ctx->sut, 'findAccountAndPasswordReset', [$ctx->resetCode]);
        $this->assertSame([$ctx->account, $ctx->passwordReset], $actual);
    }

    #endregion findAccountAndPasswordReset

    #region doTransaction ------------------------------------------------------

    private function contextForDoTransaction(
        bool $accountSaveSucceeds = true,
        bool $passwordResetDeleteSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest();
        $ctx->newPassword = 'pass5678';
        $ctx->newPasswordHash = 'hash5678';
        $ctx->account = $this->createMock(Account::class);
        $ctx->passwordReset = $this->createMock(PasswordReset::class);
        $database = Database::Instance();
        $securityService = SecurityService::Instance();

        $database->expects($ctx->chain())
            ->method('WithTransaction')
            ->willReturnCallback(fn($callback) => $callback());
        $securityService->expects($ctx->chain())
            ->method('HashPassword')
            ->with($ctx->newPassword)
            ->willReturn($ctx->newPasswordHash);
        $ctx->account->expects($ctx->chain())
            ->method('Save')
            ->willReturn($accountSaveSucceeds);
        $ctx->passwordReset->expects($ctx->chainIf($accountSaveSucceeds))
            ->method('Delete')
            ->willReturn($passwordResetDeleteSucceeds);

        return $ctx;
    }

    function testDoTransactionFailsIfAccountSaveFails()
    {
        $ctx = $this->contextForDoTransaction(accountSaveSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        ah::CallMethod($ctx->sut, 'doTransaction', [
            $ctx->newPassword,
            $ctx->account,
            $ctx->passwordReset
        ]);
    }

    function testDoTransactionFailsIfPasswordResetDeleteFails()
    {
        $ctx = $this->contextForDoTransaction(passwordResetDeleteSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete password reset.");
        ah::CallMethod($ctx->sut, 'doTransaction', [
            $ctx->newPassword,
            $ctx->account,
            $ctx->passwordReset
        ]);
    }

    function testDoTransactionSucceeds()
    {
        $ctx = $this->contextForDoTransaction();
        ah::CallMethod($ctx->sut, 'doTransaction', [
            $ctx->newPassword,
            $ctx->account,
            $ctx->passwordReset
        ]);
        $this->assertSame($ctx->newPasswordHash, $ctx->account->passwordHash);
    }

    #endregion doTransaction

    #region composeResult ------------------------------------------------------

    function testComposeResult()
    {
        $sut = $this->systemUnderTest();
        $resource = Resource::Instance();
        $redirectUrl = new CUrl('https://example.com/login/');
        $expected = ['redirectUrl' => $redirectUrl];

        $resource->expects($this->once())
            ->method('LoginPageUrl')
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
            'resetCode.required' => [[
                'newPassword' => 'pass5678'
            ]],
            'resetCode.regex' => [[
                'resetCode'   => 'invalid-code',
                'newPassword' => 'pass5678'
            ]],
            'newPassword.required' => [[
                'resetCode'   => \str_repeat('a', 64)
            ]],
            'newPassword.string' => [[
                'resetCode'   => \str_repeat('a', 64),
                'newPassword' => ['not', 'a', 'string']
            ]],
            'newPassword.minLength' => [[
                'resetCode'   => \str_repeat('a', 64),
                'newPassword' => \str_repeat('a', SecurityService::PASSWORD_MIN_LENGTH - 1)
            ]],
            'newPassword.maxLength' => [[
                'resetCode'   => \str_repeat('a', 64),
                'newPassword' => \str_repeat('a', SecurityService::PASSWORD_MAX_LENGTH + 1)
            ]],
        ];
    }

    #endregion Data Providers
}
