<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\Account\ActivateAction;

use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Harmonia\Systems\ValidationSystem\DataAccessor;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(ActivateAction::class)]
class ActivateActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;
    private ?Resource $originalResource = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
        Resource::ReplaceInstance($this->originalResource);
    }

    private function systemUnderTest(string ...$mockedMethods): ActivateAction
    {
        return $this->getMockBuilder(ActivateAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfValidateRequestFails()
    {
        $sut = $this->systemUnderTest(
            'validateRequest'
        );

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willThrowException(new \RuntimeException('Placeholder message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Placeholder message.');
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPendingAccountNotFound()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findPendingAccount'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $activationCode = \str_repeat('a', 64); // valid format

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('activationCode')
            ->willReturn($activationCode);
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

    function testOnExecuteThrowsIfEmailAlreadyRegistered()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findPendingAccount',
            'isEmailAlreadyRegistered'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createStub(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('activationCode')
            ->willReturn($activationCode);
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

    function testOnExecuteThrowsIfSavingAccountFails()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findPendingAccount',
            'isEmailAlreadyRegistered',
            'createAccountFromPendingAccount'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createStub(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';
        $account = $this->createMock(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('activationCode')
            ->willReturn($activationCode);
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
                    $this->assertSame(
                        'Failed to save account.',
                        $e->getMessage()
                    );
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Account activation failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDeletingPendingAccountFails()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findPendingAccount',
            'isEmailAlreadyRegistered',
            'createAccountFromPendingAccount'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createMock(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';
        $account = $this->createMock(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('activationCode')
            ->willReturn($activationCode);
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
                    $this->assertSame(
                        'Failed to delete pending account.',
                        $e->getMessage()
                    );
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Account activation failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDeleteCsrfCookieFails()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findPendingAccount',
            'isEmailAlreadyRegistered',
            'createAccountFromPendingAccount'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createMock(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';
        $account = $this->createMock(Account::class);
        $cookieService = CookieService::Instance();
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('activationCode')
            ->willReturn($activationCode);
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

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findPendingAccount',
            'isEmailAlreadyRegistered',
            'createAccountFromPendingAccount'
        );
        $dataAccessor = $this->createMock(DataAccessor::class);
        $activationCode = \str_repeat('a', 64);
        $pendingAccount = $this->createMock(PendingAccount::class);
        $pendingAccount->email = 'john@example.com';
        $account = $this->createMock(Account::class);
        $cookieService = CookieService::Instance();
        $database = Database::Instance();
        $resource = Resource::Instance();
        $redirectUrl = new CUrl('/url/to/login');

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($dataAccessor);
        $dataAccessor->expects($this->once())
            ->method('GetField')
            ->with('activationCode')
            ->willReturn($activationCode);
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
        $resource->expects($this->once())
            ->method('LoginPageUrl')
            ->with('home')
            ->willReturn($redirectUrl);

        $result = AccessHelper::CallMethod($sut, 'onExecute');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('redirectUrl', $result);
        $this->assertEquals($redirectUrl, $result['redirectUrl']);
    }

    #endregion onExecute

    #region validateRequest ----------------------------------------------------

    #[DataProvider('invalidRequestDataProvider')]
    function testValidateRequestThrowsForInvalidRequestData(
        array $data,
        string $exceptionMessage
    ) {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($data);
        $securityService->expects($this->once())
            ->method('TokenPattern')
            ->willReturn('/^[a-f0-9]{64}$/');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        AccessHelper::CallMethod($sut, 'validateRequest');
    }

    #endregion validateRequest

    #region findPendingAccount -------------------------------------------------

    function testFindPendingAccountReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `pendingaccount` WHERE activationCode = :activationCode LIMIT 1',
            bindings: ['activationCode' => 'code1234'],
            result: null,
            times: 1
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
            sql: 'SELECT * FROM `pendingaccount` WHERE activationCode = :activationCode LIMIT 1',
            bindings: ['activationCode' => 'code1234'],
            result: [[
                'id' => 42,
                'email' => 'john@example.com',
                'passwordHash' => 'hash1234',
                'displayName' => 'John',
                'activationCode' => 'code1234',
                'timeRegistered' => '2025-01-01 10:00:00'
            ]],
            times: 1
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
        $this->assertSame('John', $pendingAccount->displayName);
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
            sql: 'SELECT COUNT(*) FROM `account` WHERE email = :email',
            bindings: ['email' => 'test@example.com'],
            result: [[$returnValue ? 1 : 0]],
            times: 1
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
            'displayName' => 'John',
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
        $this->assertSame('John', $account->displayName);
        $this->assertSame($now->format('c'), $account->timeActivated->format('c'));
        $this->assertNull($account->timeLastLogin);
    }

    #endregion createAccountFromPendingAccount

    #region Data Providers -----------------------------------------------------

    static function invalidRequestDataProvider()
    {
        return [
            'activationCode missing' => [
                'data' => [],
                'exceptionMessage' => 'Activation code is required.'
            ],
            'activationCode invalid' => [
                'data' => ['activationCode' => 'invalid-code'],
                'exceptionMessage' => 'Activation code format is invalid.'
            ],
        ];
    }

    #endregion Data Providers
}
