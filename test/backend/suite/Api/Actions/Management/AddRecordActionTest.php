<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\Management\AddRecordAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\SecurityService;
use \Peneus\Model\AccountRole; // sample
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(AddRecordAction::class)]
class AddRecordActionTest extends TestCase
{
    private ?Request $originalRequest = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
    }

    private function systemUnderTest(string ...$mockedMethods): AddRecordAction
    {
        return $this->getMockBuilder(AddRecordAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfTableNameIsMissing()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required field 'table' is missing.");
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    #[DataProviderExternal(DataHelper::class, 'NonStringProvider')]
    function testOnExecuteThrowsIfTableNameIsNotString($value)
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'table' => $value
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'table' must be a string.");
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfModelResolutionFails()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['table' => 'table-name']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Unable to resolve entity class for table: table-name");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    #[DataProvider('invalidModelDataProvider')]
    function testOnExecuteThrowsForInvalidModelData(
        string $table,
        array $data,
        string $exceptionMessage
    ) {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['table' => $table]);
        $request->expects($this->once())
            ->method('JsonBody')
            ->willReturn($data);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEntitySaveFails()
    {
        $sut = $this->systemUnderTest('createEntity');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $data = [
            'accountId' => 1,
            'role' => 10
        ];
        $entity = $this->createMock(AccountRole::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['table' => 'accountrole']);
        $request->expects($this->once())
            ->method('JsonBody')
            ->willReturn($data);
        $sut->expects($this->once())
            ->method('createEntity')
            ->with(AccountRole::class, $data)
            ->willReturn($entity);
        $entity->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Failed to add record to table 'accountrole'.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteReturnsIdWhenEntitySaveSucceeds()
    {
        $sut = $this->systemUnderTest('createEntity');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $data = [
            'accountId' => 1,
            'role' => 10
        ];
        $entity = $this->createMock(AccountRole::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['table' => 'accountrole']);
        $request->expects($this->once())
            ->method('JsonBody')
            ->willReturn($data);
        $sut->expects($this->once())
            ->method('createEntity')
            ->with(AccountRole::class, $data)
            ->willReturn($entity);
        $entity->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $entity->id = 42;

        $this->assertSame(
            ['id' => 42],
            AccessHelper::CallMethod($sut, 'onExecute')
        );
    }

    #endregion onExecute

    #region Data Providers -----------------------------------------------------

    static function invalidModelDataProvider()
    {
        return [
            #region Account
            'account: email missing' => [
                'table' => 'account',
                'data' => [
                ],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'account: email invalid' => [
                'table' => 'account',
                'data' => [
                    'email' => 'invalid-email'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'account: passwordHash missing' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com'
                ],
                'exceptionMessage' => "Required field 'passwordHash' is missing."
            ],
            'account: passwordHash invalid' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => 'invalid-hash'
                ],
                'exceptionMessage' => "Field 'passwordHash' must match the required pattern: "
                    . SecurityService::PASSWORD_HASH_PATTERN
            ],
            'account: displayName missing' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123'
                ],
                'exceptionMessage' => "Required field 'displayName' is missing."
            ],
            'account: displayName invalid' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => '<invalid-display-name>'
                ],
                'exceptionMessage' => "Field 'displayName' must match the required pattern: "
                    . AccountService::DISPLAY_NAME_PATTERN
            ],
            'account: timeActivated missing' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John'
                ],
                'exceptionMessage' => "Required field 'timeActivated' is missing."
            ],
            'account: timeActivated invalid' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'timeActivated' => '01-01-2025'
                ],
                'exceptionMessage' => "Field 'timeActivated' must match the exact datetime format: Y-m-d H:i:s"
            ],
            'account: timeLastLogin missing' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'timeActivated' => '2025-01-01 00:00:00'
                ],
                'exceptionMessage' => "Required field 'timeLastLogin' is missing."
            ],
            'account: timeLastLogin invalid' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'timeActivated' => '2025-01-01 00:00:00',
                    'timeLastLogin' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeLastLogin' must match the exact datetime format: Y-m-d H:i:s"
            ],
            #endregion Account
            #region AccountRole
            'accountRole: accountId missing' => [
                'table' => 'accountrole',
                'data' => [
                ],
                'exceptionMessage' => "Required field 'accountId' is missing."
            ],
            'accountRole: accountId not an integer' => [
                'table' => 'accountrole',
                'data' => [
                    'accountId' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'accountId' must be an integer."
            ],
            'accountRole: accountId less than one' => [
                'table' => 'accountrole',
                'data' => [
                    'accountId' => 0
                ],
                'exceptionMessage' => "Field 'accountId' must have a minimum value of 1."
            ],
            'accountRole: role missing' => [
                'table' => 'accountrole',
                'data' => [
                    'accountId' => 1
                ],
                'exceptionMessage' => "Required field 'role' is missing."
            ],
            'accountRole: role invalid enum value' => [
                'table' => 'accountrole',
                'data' => [
                    'accountId' => 1,
                    'role' => 999
                ],
                'exceptionMessage' => "Field 'role' must be a valid value of enum 'Peneus\Model\Role'."
            ],
            #endregion AccountRole
            #region PendingAccount
            'pendingAccount: email missing' => [
                'table' => 'pendingaccount',
                'data' => [
                ],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'pendingAccount: email invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'invalid-email'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'pendingAccount: passwordHash missing' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                ],
                'exceptionMessage' => "Required field 'passwordHash' is missing."
            ],
            'pendingAccount: passwordHash invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => 'invalid-hash'
                ],
                'exceptionMessage' => "Field 'passwordHash' must match the required pattern: "
                    . SecurityService::PASSWORD_HASH_PATTERN
            ],
            'pendingAccount: displayName missing' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123'
                ],
                'exceptionMessage' => "Required field 'displayName' is missing."
            ],
            'pendingAccount: displayName invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => '<invalid-display-name>'
                ],
                'exceptionMessage' => "Field 'displayName' must match the required pattern: "
                    . AccountService::DISPLAY_NAME_PATTERN
            ],
            'pendingAccount: activationCode missing' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John'
                ],
                'exceptionMessage' => "Required field 'activationCode' is missing."
            ],
            'pendingAccount: activationCode invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'activationCode' => 'invalid-code'
                ],
                'exceptionMessage' => "Field 'activationCode' must match the required pattern: "
                    . SecurityService::TOKEN_DEFAULT_PATTERN
            ],
            'pendingAccount: timeRegistered missing' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'activationCode' => \str_repeat('a', 64),
                ],
                'exceptionMessage' => "Required field 'timeRegistered' is missing."
            ],
            'pendingAccount: timeRegistered invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'activationCode' => \str_repeat('a', 64),
                    'timeRegistered' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeRegistered' must match the exact datetime format: Y-m-d H:i:s"
            ],
            #endregion PendingAccount
            #region PasswordReset
            'passwordReset: accountId missing' => [
                'table' => 'passwordreset',
                'data' => [
                ],
                'exceptionMessage' => "Required field 'accountId' is missing."
            ],
            'passwordReset: accountId not an integer' => [
                'table' => 'passwordreset',
                'data' => [
                    'accountId' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'accountId' must be an integer."
            ],
            'passwordReset: accountId less than one' => [
                'table' => 'passwordreset',
                'data' => [
                    'accountId' => 0
                ],
                'exceptionMessage' => "Field 'accountId' must have a minimum value of 1."
            ],
            'passwordReset: resetCode missing' => [
                'table' => 'passwordreset',
                'data' => [
                    'accountId' => 1
                ],
                'exceptionMessage' => "Required field 'resetCode' is missing."
            ],
            'passwordReset: resetCode invalid' => [
                'table' => 'passwordreset',
                'data' => [
                    'accountId' => 1,
                    'resetCode' => 'invalid-token'
                ],
                'exceptionMessage' => "Field 'resetCode' must match the required pattern: "
                    . SecurityService::TOKEN_DEFAULT_PATTERN
            ],
            'passwordReset: timeRequested missing' => [
                'table' => 'passwordreset',
                'data' => [
                    'accountId' => 1,
                    'resetCode' => \str_repeat('a', 64)
                ],
                'exceptionMessage' => "Required field 'timeRequested' is missing."
            ],
            'passwordReset: timeRequested invalid' => [
                'table' => 'passwordreset',
                'data' => [
                    'accountId' => 1,
                    'resetCode' => \str_repeat('a', 64),
                    'timeRequested' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeRequested' must match the exact datetime format: Y-m-d H:i:s"
            ],
            #endregion PasswordReset
            #region PersistentLogin
            'persistentLogin: accountId missing' => [
                'table' => 'persistentlogin',
                'data' => [
                ],
                'exceptionMessage' => "Required field 'accountId' is missing."
            ],
            'persistentLogin: accountId not an integer' => [
                'table' => 'persistentlogin',
                'data' => [
                    'accountId' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'accountId' must be an integer."
            ],
            'persistentLogin: accountId less than one' => [
                'table' => 'persistentlogin',
                'data' => [
                    'accountId' => 0
                ],
                'exceptionMessage' => "Field 'accountId' must have a minimum value of 1."
            ],
            'persistentLogin: clientSignature missing' => [
                'table' => 'persistentlogin',
                'data' => [
                    'accountId' => 1
                ],
                'exceptionMessage' => "Required field 'clientSignature' is missing."
            ],
            'persistentLogin: clientSignature invalid' => [
                'table' => 'persistentlogin',
                'data' => [
                    'accountId' => 1,
                    'clientSignature' => 'invalid-signature'
                ],
                'exceptionMessage' => "Field 'clientSignature' must match the required pattern: /^[0-9a-zA-Z+\/]{22,24}$/"
            ],
            'persistentLogin: lookupKey missing' => [
                'table' => 'persistentlogin',
                'data' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22)
                ],
                'exceptionMessage' => "Required field 'lookupKey' is missing."
            ],
            'persistentLogin: lookupKey invalid' => [
                'table' => 'persistentlogin',
                'data' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22),
                    'lookupKey' => 'invalid-key'
                ],
                'exceptionMessage' => "Field 'lookupKey' must match the required pattern: /^[0-9a-fA-F]{16}$/"
            ],
            'persistentLogin: tokenHash missing' => [
                'table' => 'persistentlogin',
                'data' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22),
                    'lookupKey' => \str_repeat('a', 16)
                ],
                'exceptionMessage' => "Required field 'tokenHash' is missing."
            ],
            'persistentLogin: tokenHash invalid' => [
                'table' => 'persistentlogin',
                'data' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22),
                    'lookupKey' => \str_repeat('a', 16),
                    'tokenHash' => 'invalid-hash'
                ],
                'exceptionMessage' => "Field 'tokenHash' must match the required pattern: "
                    . SecurityService::PASSWORD_HASH_PATTERN
            ],
            'persistentLogin: timeExpires missing' => [
                'table' => 'persistentlogin',
                'data' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22),
                    'lookupKey' => \str_repeat('a', 16),
                    'tokenHash' => '$2y$10$12345678901234567890123456789012345678901234567890123'
                ],
                'exceptionMessage' => "Required field 'timeExpires' is missing."
            ],
            'persistentLogin: timeExpires invalid' => [
                'table' => 'persistentlogin',
                'data' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22),
                    'lookupKey' => \str_repeat('a', 16),
                    'tokenHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'timeExpires' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeExpires' must match the exact datetime format: Y-m-d H:i:s"
            ],
            #endregion PersistentLogin
        ];
    }

    #endregion Data Providers
}
