<?php declare(strict_types=1);
namespace suite\Api\Actions\Account;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\ActivateAction;

use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Services\CookieService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

#[CoversClass(ActivateAction::class)]
class ActivateActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Resource $originalResource = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        Resource::ReplaceInstance($this->originalResource);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): ActivateAction
    {
        return $this->getMockBuilder(ActivateAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    private function contextForOnExecute(
        bool $validatePayloadSucceeds = true,
        bool $findPendingAccountSucceeds = true,
        bool $ensureNotRegisteredSucceeds = true,
        bool $doTransactionSucceeds = true,
        bool $deleteCsrfCookieSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'validatePayload',
            'findPendingAccount',
            'ensureNotRegistered',
            'doTransaction',
            'composeResult'
        );
        $payload = new \stdClass();
        $payload->activationCode = 'code1234';
        $pendingAccount = $this->createStub(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';
        $cookieService = CookieService::Instance();
        $ctx->result = ['redirectUrl' => new CUrl('https://example.com/login')];

        $ctx->sut->expects($ctx->chain())
            ->method('validatePayload')
            ->willReturnCallback(fn() => $validatePayloadSucceeds
                ? $payload
                : throw new \RuntimeException('VALIDATE_PAYLOAD_FAILED'));
        $ctx->sut->expects($ctx->chainIf($validatePayloadSucceeds))
            ->method('findPendingAccount')
            ->with($payload->activationCode)
            ->willReturnCallback(fn() => $findPendingAccountSucceeds
                ? $pendingAccount
                : throw new \RuntimeException('FIND_PENDING_ACCOUNT_FAILED'));
        $ctx->sut->expects($ctx->chainIf($findPendingAccountSucceeds))
            ->method('ensureNotRegistered')
            ->with($pendingAccount->email)
            ->willReturnCallback(fn() => $ensureNotRegisteredSucceeds
                ? null
                : throw new \RuntimeException('ENSURE_NOT_REGISTERED_FAILED'));
        $ctx->sut->expects($ctx->chainIf($ensureNotRegisteredSucceeds))
            ->method('doTransaction')
            ->with($pendingAccount)
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

    function testOnExecuteFailsIfPayloadInvalid()
    {
        $ctx = $this->contextForOnExecute(validatePayloadSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VALIDATE_PAYLOAD_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfPendingAccountNotFound()
    {
        $ctx = $this->contextForOnExecute(findPendingAccountSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FIND_PENDING_ACCOUNT_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfEmailAlreadyRegistered()
    {
        $ctx = $this->contextForOnExecute(ensureNotRegisteredSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ENSURE_NOT_REGISTERED_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfTransactionFails()
    {
        $ctx = $this->contextForOnExecute(doTransactionSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DO_TRANSACTION_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfCsrfCookieDeleteFails()
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
        $payload = ['activationCode' => \str_repeat('a', 64)];
        $ctx = $this->contextForValidatePayload($payload);
        $expected = (object)$payload;
        $actual = ah::CallMethod($ctx->sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region doTransaction ------------------------------------------------------

    private function contextForDoTransaction(
        bool $accountSaveSucceeds = true,
        bool $pendingAccountDeleteSucceeds = true,
        bool $triggerActivationHooksSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest('makeAccount', 'triggerActivationHooks');
        $ctx->pendingAccount = $this->createMock(PendingAccount::class);
        $database = Database::Instance();
        $account = $this->createMock(Account::class);

        $database->expects($ctx->chain())
            ->method('WithTransaction')
            ->willReturnCallback(fn($callback) => $callback());
        $ctx->sut->expects($ctx->chain())
            ->method('makeAccount')
            ->with($ctx->pendingAccount)
            ->willReturn($account);
        $account->expects($ctx->chain())
            ->method('Save')
            ->willReturn($accountSaveSucceeds);
        $ctx->pendingAccount->expects($ctx->chainIf($accountSaveSucceeds))
            ->method('Delete')
            ->willReturn($pendingAccountDeleteSucceeds);
        $ctx->sut->expects($ctx->chainIf($pendingAccountDeleteSucceeds))
            ->method('triggerActivationHooks')
            ->with($account)
            ->willReturnCallback(fn() => $triggerActivationHooksSucceeds
                ? null
                : throw new \RuntimeException('TRIGGER_ACTIVATION_HOOKS_FAILED'));

        return $ctx;
    }

    function testDoTransactionFailsIfAccountSaveFails()
    {
        $ctx = $this->contextForDoTransaction(accountSaveSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->pendingAccount]);
    }

    function testDoTransactionFailsIfPendingAccountDeleteFails()
    {
        $ctx = $this->contextForDoTransaction(pendingAccountDeleteSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete pending account.");
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->pendingAccount]);
    }

    function testDoTransactionFailsIfTriggerActivationHooksFails()
    {
        $ctx = $this->contextForDoTransaction(triggerActivationHooksSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TRIGGER_ACTIVATION_HOOKS_FAILED');
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->pendingAccount]);
    }

    function testDoTransactionSucceeds()
    {
        $ctx = $this->contextForDoTransaction();
        ah::CallMethod($ctx->sut, 'doTransaction', [$ctx->pendingAccount]);
    }

    #endregion doTransaction

    #region makeAccount --------------------------------------------------------

    function testMakeAccount()
    {
        $sut = $this->systemUnderTest();
        $email = 'john@example.com';
        $passwordHash = 'hash1234';
        $displayName = 'John';
        $pendingAccount = $this->createStub(PendingAccount::class);
        $pendingAccount->email = $email;
        $pendingAccount->passwordHash = $passwordHash;
        $pendingAccount->displayName = $displayName;

        $account = ah::CallMethod($sut, 'makeAccount', [
            $pendingAccount
        ]);

        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(0,                  $account->id);
        $this->assertSame($email,             $account->email);
        $this->assertSame($passwordHash,      $account->passwordHash);
        $this->assertSame($displayName,       $account->displayName);
        $this->assertEqualsWithDelta(\time(), $account->timeActivated->getTimestamp(), 1);
        $this->assertNull(                    $account->timeLastLogin);
    }

    #endregion makeAccount

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
            'activationCode.required' => [[
                // empty
            ]],
            'activationCode.regex' => [[
                'activationCode' => 'invalid-code'
            ]],
        ];
    }

    #endregion Data Providers
}
