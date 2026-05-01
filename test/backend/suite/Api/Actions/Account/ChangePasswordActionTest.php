<?php declare(strict_types=1);
namespace suite\Api\Actions\Account;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Api\Actions\Account\ChangePasswordAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

#[CoversClass(ChangePasswordAction::class)]
class ChangePasswordActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?SecurityService $originalSecurityService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        SecurityService::ReplaceInstance($this->originalSecurityService);
    }

    private function systemUnderTest(string ...$mockedMethods): ChangePasswordAction
    {
        return $this->getMockBuilder(ChangePasswordAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    private function contextForOnExecute(
        bool $ensureLoggedInSucceeds = true,
        bool $ensureLocalAccountSucceeds = true,
        bool $findAccountSucceeds = true,
        bool $validatePayloadSucceeds = true,
        bool $verifyCurrentPasswordSucceeds = true,
        bool $doTransactionSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'ensureLoggedIn',
            'ensureLocalAccount',
            'findAccount',
            'validatePayload',
            'verifyCurrentPassword',
            'doTransaction'
        );
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 17;
        $account = $this->createStub(Account::class);
        $account->passwordHash = 'hash1234';
        $payload = new \stdClass();
        $payload->currentPassword = 'pass1234';
        $payload->newPassword = 'pass5678';

        $ctx->sut->expects($ctx->chain())
            ->method('ensureLoggedIn')
            ->willReturnCallback(fn() => $ensureLoggedInSucceeds
                ? $accountView
                : throw new \RuntimeException('ENSURE_LOGGED_IN_FAILED'));
        $ctx->sut->expects($ctx->chainIf($ensureLoggedInSucceeds))
            ->method('ensureLocalAccount')
            ->with($accountView)
            ->willReturnCallback(fn() => $ensureLocalAccountSucceeds
                ? null
                : throw new \RuntimeException('ENSURE_LOCAL_ACCOUNT_FAILED'));
        $ctx->sut->expects($ctx->chainIf($ensureLocalAccountSucceeds))
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturnCallback(fn() => $findAccountSucceeds
                ? $account
                : throw new \RuntimeException('FIND_ACCOUNT_FAILED'));
        $ctx->sut->expects($ctx->chainIf($findAccountSucceeds))
            ->method('validatePayload')
            ->willReturnCallback(fn() => $validatePayloadSucceeds
                ? $payload
                : throw new \RuntimeException('VALIDATE_PAYLOAD_FAILED'));
        $ctx->sut->expects($ctx->chainIf($validatePayloadSucceeds))
            ->method('verifyCurrentPassword')
            ->with($payload->currentPassword, $account->passwordHash)
            ->willReturnCallback(fn() => $verifyCurrentPasswordSucceeds
                ? null
                : throw new \RuntimeException('VERIFY_CURRENT_PASSWORD_FAILED'));
        $ctx->sut->expects($ctx->chainIf($verifyCurrentPasswordSucceeds))
            ->method('doTransaction')
            ->with($account, $payload->newPassword)
            ->willReturnCallback(fn() => $doTransactionSucceeds
                ? null
                : throw new \RuntimeException('DO_TRANSACTION_FAILED'));

        return $ctx;
    }

    function testOnExecuteFailsIfEnsureLoggedInFails()
    {
        $ctx = $this->contextForOnExecute(ensureLoggedInSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ENSURE_LOGGED_IN_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfEnsureLocalAccountFails()
    {
        $ctx = $this->contextForOnExecute(ensureLocalAccountSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ENSURE_LOCAL_ACCOUNT_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfFindAccountFails()
    {
        $ctx = $this->contextForOnExecute(findAccountSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FIND_ACCOUNT_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfValidatePayloadFails()
    {
        $ctx = $this->contextForOnExecute(validatePayloadSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VALIDATE_PAYLOAD_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfVerifyCurrentPasswordFails()
    {
        $ctx = $this->contextForOnExecute(verifyCurrentPasswordSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VERIFY_CURRENT_PASSWORD_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfDoTransactionFails()
    {
        $ctx = $this->contextForOnExecute(doTransactionSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DO_TRANSACTION_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $ctx = $this->contextForOnExecute();
        $actual = ah::CallMethod($ctx->sut, 'onExecute');
        $this->assertNull($actual);
    }

    #endregion onExecute

    #region ensureLocalAccount -------------------------------------------------

    #[TestWith([true ])]
    #[TestWith([false])]
    function testEnsureLocalAccount(bool $isLocal)
    {
        $sut = $this->systemUnderTest();
        $accountView = $this->createStub(AccountView::class);
        $accountView->isLocal = $isLocal;

        if ($isLocal) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("This account does not have a local password.");
            $this->expectExceptionCode(StatusCode::Forbidden->value);
        }

        ah::CallMethod($sut, 'ensureLocalAccount', [$accountView]);
    }

    #endregion ensureLocalAccount

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
            'currentPassword' => 'pass1234',
            'newPassword' => 'pass5678'
        ];
        $ctx = $this->contextForValidatePayload($payload);
        $expected = (object)$payload;
        $actual = ah::CallMethod($ctx->sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region verifyCurrentPassword ----------------------------------------------

    #[TestWith([true ])]
    #[TestWith([false])]
    function testVerifyCurrentPassword(bool $isVerified)
    {
        $sut = $this->systemUnderTest();
        $currentPassword = 'pass1234';
        $passwordHash = 'hash1234';
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with($currentPassword, $passwordHash)
            ->willReturn($isVerified);

        if ($isVerified) {
            ;
        } else {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("Current password is incorrect.");
            $this->expectExceptionCode(StatusCode::Unauthorized->value);
        }

        ah::CallMethod($sut, 'verifyCurrentPassword', [
            $currentPassword,
            $passwordHash
        ]);
    }

    #endregion verifyCurrentPassword

    #region doTransaction ------------------------------------------------------

    private function contextForDoTransaction(
        bool $accountSaveSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest();
        $database = Database::Instance();
        $ctx->account = $this->createMock(Account::class);
        $ctx->newPassword = 'pass5678';
        $ctx->newHash = 'hash5678';
        $securityService = SecurityService::Instance();

        $database->expects($ctx->chain())
             ->method('WithTransaction')
             ->willReturnCallback(fn($callback) => $callback());
        $securityService->expects($ctx->chain())
            ->method('HashPassword')
            ->with($ctx->newPassword)
            ->willReturn($ctx->newHash);
        $ctx->account->expects($ctx->chain())
            ->method('Save')
            ->willReturn($accountSaveSucceeds);

        return $ctx;
    }

    function testDoTransactionFailsIfAccountSaveFails()
    {
        $ctx = $this->contextForDoTransaction(accountSaveSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        ah::CallMethod($ctx->sut, 'doTransaction', [
            $ctx->account,
            $ctx->newPassword
        ]);
    }

    function testDoTransactionSucceeds()
    {
        $ctx = $this->contextForDoTransaction();
        ah::CallMethod($ctx->sut, 'doTransaction', [
            $ctx->account,
            $ctx->newPassword
        ]);
        $this->assertSame($ctx->newHash, $ctx->account->passwordHash);
    }

    #endregion doTransaction

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'currentPassword.required' => [[
                'newPassword' => 'pass5678'
            ]],
            'currentPassword.string' => [[
                'currentPassword' => ['not', 'a', 'string'],
                'newPassword' => 'pass5678'
            ]],
            'currentPassword.minLength' => [[
                'currentPassword' => \str_repeat('a', SecurityService::PASSWORD_MIN_LENGTH - 1),
                'newPassword' => 'pass5678'
            ]],
            'currentPassword.maxLength' => [[
                'currentPassword' => \str_repeat('a', SecurityService::PASSWORD_MAX_LENGTH + 1),
                'newPassword' => 'pass5678'
            ]],
            'newPassword.required' => [[
                'currentPassword' => 'pass1234'
            ]],
            'newPassword.string' => [[
                'currentPassword' => 'pass1234',
                'newPassword' => ['not', 'a', 'string']
            ]],
            'newPassword.minLength' => [[
                'currentPassword' => 'pass1234',
                'newPassword' => \str_repeat('a', SecurityService::PASSWORD_MIN_LENGTH - 1)
            ]],
            'newPassword.maxLength' => [[
                'currentPassword' => 'pass1234',
                'newPassword' => \str_repeat('a', SecurityService::PASSWORD_MAX_LENGTH + 1)
            ]],
        ];
    }

    #endregion Data Providers
}
