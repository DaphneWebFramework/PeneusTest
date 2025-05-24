<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\ActivateAccountAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \TestToolkit\AccessHelper;

#[CoversClass(ActivateAccountAction::class)]
class ActivateAccountActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?CookieService $originalCookieService = null;
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->config());
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        CookieService::ReplaceInstance($this->originalCookieService);
        Config::ReplaceInstance($this->originalConfig);
    }

    private function config()
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(string ...$mockedMethods): ActivateAccountAction
    {
        return $this->getMockBuilder(ActivateAccountAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfActivationCodeIsMissing(): void
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([]); // Missing activationCode

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Activation code is required.');
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfActivationCodeIsInvalid(): void
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
                'activationCode' => 'not-a-valid-code'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Activation code format is invalid.');
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPendingAccountNotFound(): void
    {
        $sut = $this->systemUnderTest('findPendingAccount');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $activationCode = \str_repeat('a', 64); // valid format

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'activationCode' => $activationCode
            ]);
        $sut->expects($this->once())
            ->method('findPendingAccount')
            ->with($activationCode)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'No account is awaiting activation for the given code.');
        $this->expectExceptionCode(StatusCode::NotFound->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEmailAlreadyRegistered(): void
    {
        $sut = $this->systemUnderTest(
            'findPendingAccount',
            'isEmailAlreadyRegistered'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createStub(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'activationCode' => $activationCode
            ]);
        $sut->expects($this->once())
            ->method('findPendingAccount')
            ->with($activationCode)
            ->willReturn($pendingAccount);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This email address is already registered.');
        $this->expectExceptionCode(StatusCode::Conflict->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfSavingAccountFails(): void
    {
        $sut = $this->systemUnderTest(
            'findPendingAccount',
            'isEmailAlreadyRegistered',
            'createAccountFromPendingAccount'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createStub(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';
        $account = $this->createMock(Account::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'activationCode' => $activationCode
            ]);
        $sut->expects($this->once())
            ->method('findPendingAccount')
            ->with($activationCode)
            ->willReturn($pendingAccount);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('createAccountFromPendingAccount')
            ->with($pendingAccount)
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame('Failed to save account.', $e->getMessage());
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Account activation failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDeletingPendingAccountFails(): void
    {
        $sut = $this->systemUnderTest(
            'findPendingAccount',
            'isEmailAlreadyRegistered',
            'createAccountFromPendingAccount'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createMock(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';
        $account = $this->createMock(Account::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'activationCode' => $activationCode
            ]);
        $sut->expects($this->once())
            ->method('findPendingAccount')
            ->with($activationCode)
            ->willReturn($pendingAccount);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('createAccountFromPendingAccount')
            ->with($pendingAccount)
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $pendingAccount->expects($this->once())
            ->method('Delete')
            ->willReturn(false);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame('Failed to delete pending account.', $e->getMessage());
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Account activation failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDeleteCsrfCookieFails(): void
    {
        $sut = $this->systemUnderTest(
            'findPendingAccount',
            'isEmailAlreadyRegistered',
            'createAccountFromPendingAccount'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createMock(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';
        $account = $this->createMock(Account::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'activationCode' => $activationCode
            ]);
        $sut->expects($this->once())
            ->method('findPendingAccount')
            ->with($activationCode)
            ->willReturn($pendingAccount);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('createAccountFromPendingAccount')
            ->with($pendingAccount)
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $pendingAccount->expects($this->once())
            ->method('Delete')
            ->willReturn(true);
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie')
            ->willThrowException(new \RuntimeException);

        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Account activation failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceedsIfDatabaseTransactionSucceeds(): void
    {
        $sut = $this->systemUnderTest(
            'findPendingAccount',
            'isEmailAlreadyRegistered',
            'createAccountFromPendingAccount'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createMock(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';
        $account = $this->createMock(Account::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'activationCode' => $activationCode
            ]);
        $sut->expects($this->once())
            ->method('findPendingAccount')
            ->with($activationCode)
            ->willReturn($pendingAccount);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('createAccountFromPendingAccount')
            ->with($pendingAccount)
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $pendingAccount->expects($this->once())
            ->method('Delete')
            ->willReturn(true);
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });

        $this->assertNull(AccessHelper::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute
}
