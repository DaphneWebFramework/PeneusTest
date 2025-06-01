<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\ActivateAccountAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Logger;
use \Harmonia\Services\CookieService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(ActivateAccountAction::class)]
class ActivateAccountActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?CookieService $originalCookieService = null;
    private ?Config $originalConfig = null;
    private ?Logger $originalLogger = null;

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
        $this->originalLogger =
            Logger::ReplaceInstance($this->createStub(Logger::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        CookieService::ReplaceInstance($this->originalCookieService);
        Config::ReplaceInstance($this->originalConfig);
        Logger::ReplaceInstance($this->originalLogger);
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
            'createAccountFromPendingAccount',
            'buildLoginUrl'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createMock(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';
        $account = $this->createMock(Account::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();
        $redirectUrl = '/redirect/target';

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
        $sut->expects($this->once())
            ->method('buildLoginUrl')
            ->willReturn($redirectUrl);

        $result = AccessHelper::CallMethod($sut, 'onExecute');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('redirectUrl', $result);
        $this->assertSame($redirectUrl, $result['redirectUrl']);
    }

    #endregion onExecute

    #region findPendingAccount -------------------------------------------------

    function testFindPendingAccountReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM pendingaccount WHERE activationCode = :activationCode LIMIT 1',
            bindings: ['activationCode' => 'code1234'],
            result: null
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertNull(AccessHelper::CallMethod(
            $sut,
            'findPendingAccount',
            ['code1234']
        ));
    }

    function testFindPendingAccountReturnsEntityWhenFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM pendingaccount WHERE activationCode = :activationCode LIMIT 1',
            bindings: ['activationCode' => 'code1234'],
            result: [[
                'id' => 42,
                'email' => 'john@example.com',
                'passwordHash' => 'hash1234',
                'displayName' => 'John Doe',
                'activationCode' => 'code1234',
                'timeRegistered' => '2025-01-01 10:00:00'
            ]]
        );
        Database::ReplaceInstance($fakeDatabase);

        $pendingAccount = AccessHelper::CallMethod(
            $sut,
            'findPendingAccount',
            ['code1234']
        );
        $this->assertInstanceOf(PendingAccount::class, $pendingAccount);
        $this->assertSame(42, $pendingAccount->id);
        $this->assertSame('john@example.com', $pendingAccount->email);
        $this->assertSame('hash1234', $pendingAccount->passwordHash);
        $this->assertSame('John Doe', $pendingAccount->displayName);
        $this->assertSame('code1234', $pendingAccount->activationCode);
        $this->assertSame('2025-01-01 10:00:00',
            $pendingAccount->timeRegistered->format('Y-m-d H:i:s'));
    }

    #endregion findPendingAccount

    #region isEmailAlreadyRegistered -------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testIsEmailAlreadyRegistered($returnValue)
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM account WHERE email = :email',
            bindings: ['email' => 'test@example.com'],
            result: [[$returnValue ? 1 : 0]]
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'isEmailAlreadyRegistered',
            ['test@example.com']
        ));
    }

    #endregion isEmailAlreadyRegistered

    #region createAccountFromPendingAccount ------------------------------------

    function testCreateAccountFromPendingAccount()
    {
        $sut = $this->systemUnderTest();
        $now = new \DateTime();
        $pendingAccount = new PendingAccount([
            'email' => 'john@example.com',
            'passwordHash' => 'hash1234',
            'displayName' => 'John Doe',
            'activationCode' => 'code1234',
            'timeRegistered' => '2024-12-31 23:59:59'
        ]);

        $account = AccessHelper::CallMethod(
            $sut,
            'createAccountFromPendingAccount',
            [$pendingAccount, $now]
        );
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('hash1234', $account->passwordHash);
        $this->assertSame('John Doe', $account->displayName);
        $this->assertSame($now->format('c'), $account->timeActivated->format('c'));
        $this->assertNull($account->timeLastLogin);
    }

    #endregion createAccountFromPendingAccount
}
