<?php declare(strict_types=1);
namespace suite\Api\Actions\Account;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Api\Actions\Account\LogInAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

#[CoversClass(LogInAction::class)]
class LogInActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?AccountService $originalAccountService = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        AccountService::ReplaceInstance($this->originalAccountService);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): LogInAction
    {
        return $this->getMockBuilder(LogInAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    private function contextForOnExecute(
        bool $ensureNotLoggedInSucceeds = true,
        bool $validatePayloadSucceeds = true,
        bool $authenticateAccountSucceeds = true,
        bool $doTransactionSucceeds = true,
        bool $deleteCsrfCookieSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'ensureNotLoggedIn',
            'validatePayload',
            'authenticateAccount',
            'doTransaction'
        );
        $payload = new \stdClass();
        $payload->email = 'john@example.com';
        $payload->password = 'pass1234';
        $payload->keepLoggedIn = true;
        $account = $this->createStub(Account::class);
        $cookieService = CookieService::Instance();

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
            ->method('authenticateAccount')
            ->with($payload->email, $payload->password)
            ->willReturnCallback(fn() => $authenticateAccountSucceeds
                ? $account
                : throw new \RuntimeException('AUTHENTICATE_ACCOUNT_FAILED'));
        $ctx->sut->expects($ctx->chainIf($authenticateAccountSucceeds))
            ->method('doTransaction')
            ->with($account, $payload->keepLoggedIn)
            ->willReturnCallback(fn() => $doTransactionSucceeds
                ? null
                : throw new \RuntimeException('DO_TRANSACTION_FAILED'));
        $cookieService->expects($ctx->chainIf($doTransactionSucceeds))
            ->method('DeleteCsrfCookie')
            ->willReturnCallback(fn() => $deleteCsrfCookieSucceeds
                ? null
                : throw new \RuntimeException('DELETE_CSRF_COOKIE_FAILED'));

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

    function testOnExecuteFailsIfAuthenticateAccountFails()
    {
        $ctx = $this->contextForOnExecute(authenticateAccountSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AUTHENTICATE_ACCOUNT_FAILED');
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
        $this->assertNull($actual);
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

    #[DataProvider('validPayloadProvider')]
    function testValidatePayloadSucceeds(array $payload)
    {
        $ctx = $this->contextForValidatePayload($payload);
        $expected = (object)$payload;
        $expected->keepLoggedIn = 'on' === ($payload['keepLoggedIn'] ?? null);
        $actual = ah::CallMethod($ctx->sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region authenticateAccount ------------------------------------------------

    private function contextForAuthenticateAccount(
        bool $accountFound = true,
        bool $passwordVerified = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest('tryFindAccountByEmail');
        $ctx->email = 'john@example.com';
        $ctx->password = 'pass1234';
        $ctx->account = $this->createStub(Account::class);
        $ctx->account->passwordHash = 'hash1234';
        $securityService = SecurityService::Instance();

        $ctx->sut->expects($ctx->chain())
            ->method('tryFindAccountByEmail')
            ->with($ctx->email)
            ->willReturn($accountFound ? $ctx->account : null);
        $securityService->expects($ctx->chainIf($accountFound))
            ->method('VerifyPassword')
            ->with($ctx->password, $ctx->account->passwordHash)
            ->willReturn($passwordVerified);

        return $ctx;
    }

    function testAuthenticateAccountFailsIfAccountNotFound()
    {
        $ctx = $this->contextForAuthenticateAccount(accountFound: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Incorrect email address or password.");
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        ah::CallMethod($ctx->sut, 'authenticateAccount', [
            $ctx->email,
            $ctx->password
        ]);
    }

    function testAuthenticateAccountFailsIfPasswordNotVerified()
    {
        $ctx = $this->contextForAuthenticateAccount(passwordVerified: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Incorrect email address or password.");
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        ah::CallMethod($ctx->sut, 'authenticateAccount', [
            $ctx->email,
            $ctx->password
        ]);
    }

    function testAuthenticateAccountSucceeds()
    {
        $ctx = $this->contextForAuthenticateAccount();
        $actual = ah::CallMethod($ctx->sut, 'authenticateAccount', [
            $ctx->email,
            $ctx->password
        ]);
        $this->assertSame($ctx->account, $actual);
    }

    #endregion authenticateAccount

    #region doTransaction ------------------------------------------------------

    private function contextForDoTransaction(
        bool $accountSaveSucceeds = true,
        bool $sessionCreateSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest('tryLogOut');
        $ctx->account = $this->createMock(Account::class);
        $ctx->keepLoggedIn = true;
        $database = Database::Instance();
        $accountService = AccountService::Instance();

        $database->expects($ctx->chain())
            ->method('WithTransaction')
            ->willReturnCallback(fn($callback) => $callback());
        $ctx->account->expects($ctx->chain())
            ->method('Save')
            ->willReturn($accountSaveSucceeds);
        $accountService->expects($ctx->chainIf($accountSaveSucceeds))
            ->method('CreateSession')
            ->with($ctx->account->id, $ctx->keepLoggedIn)
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
        $ctx = $this->contextForDoTransaction(accountSaveSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        ah::CallMethod($ctx->sut, 'doTransaction', [
            $ctx->account,
            $ctx->keepLoggedIn
        ]);
    }

    function testDoTransactionFailsIfSessionCreateFails()
    {
        $ctx = $this->contextForDoTransaction(sessionCreateSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SESSION_CREATE_FAILED');
        ah::CallMethod($ctx->sut, 'doTransaction', [
            $ctx->account,
            $ctx->keepLoggedIn
        ]);
    }

    function testDoTransactionSucceeds()
    {
        $ctx = $this->contextForDoTransaction();
        ah::CallMethod($ctx->sut, 'doTransaction', [
            $ctx->account,
            $ctx->keepLoggedIn
        ]);
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

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'email.required' => [[
                'password' => 'pass1234'
            ]],
            'email.email' => [[
                'email' => 'invalid-email',
                'password' => 'pass1234'
            ]],
            'password.required' => [[
                'email' => 'john@example.com'
            ]],
            'password.string' => [[
                'email' => 'john@example.com',
                'password' => ['not', 'a', 'string']
            ]],
            'password.minLength' => [[
                'email' => 'john@example.com',
                'password' => \str_repeat('a', SecurityService::PASSWORD_MIN_LENGTH - 1)
            ]],
            'password.maxLength' => [[
                'email' => 'john@example.com',
                'password' => \str_repeat('a', SecurityService::PASSWORD_MAX_LENGTH + 1)
            ]],
            'keepLoggedIn.string' => [[
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'keepLoggedIn' => ['not', 'a', 'string']
            ]],
            'keepLoggedIn.custom' => [[
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'keepLoggedIn' => 'yes' // expected: 'on'
            ]]
        ];
    }

    static function validPayloadProvider()
    {
        return [
            'no keepLoggedIn' => [[
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]],
            'keepLoggedIn' => [[
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'keepLoggedIn' => 'on'
            ]]
        ];
    }

    #endregion Data Providers
}
