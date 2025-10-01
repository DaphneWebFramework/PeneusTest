<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\Management\EditRecordAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(EditRecordAction::class)]
class EditRecordActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance(new FakeDatabase());
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
    }

    private function systemUnderTest(string ...$mockedMethods): EditRecordAction
    {
        return $this->getMockBuilder(EditRecordAction::class)
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
            ->willReturn(['table' => 'not-allowed']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Table 'not-allowed' is not allowed.");
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
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEntityNotFound()
    {
        $sut = $this->systemUnderTest('findEntity');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $data = [
            'id' => 42,
            'accountId' => 1,
            'role' => 10
        ];

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
            ->method('findEntity')
            ->with(AccountRole::class, 42)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Record with ID 42 not found in table 'accountrole'.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEntitySaveFails()
    {
        $sut = $this->systemUnderTest('findEntity');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $data = [
            'id' => 42,
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
            ->method('findEntity')
            ->with(AccountRole::class, 42)
            ->willReturn($entity);
        $entity->expects($this->once())
            ->method('Populate')
            ->with($data);
        $entity->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Failed to edit record with ID 42 in table 'accountrole'.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteReturnsNullWhenEntitySaveSucceeds()
    {
        $sut = $this->systemUnderTest('findEntity');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $data = [
            'id' => 42,
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
            ->method('findEntity')
            ->with(AccountRole::class, 42)
            ->willReturn($entity);
        $entity->expects($this->once())
            ->method('Populate')
            ->with($data);
        $entity->expects($this->once())
            ->method('Save')
            ->willReturn(true);

        $this->assertNull(AccessHelper::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute

    #region findEntity ---------------------------------------------------------

    function testFindEntityReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: null,
            times: 1
        );

        $entity = AccessHelper::CallMethod(
            $sut,
            'findEntity',
            [AccountRole::class, 42]
        );
        $this->assertNull($entity);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindEntityReturnsEntityWhenFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: [[
                'id' => 42,
                'accountId' => 99,
                'role' => 10
            ]],
            times: 1
        );

        $entity = AccessHelper::CallMethod(
            $sut,
            'findEntity',
            [AccountRole::class, 42]
        );
        $this->assertInstanceOf(AccountRole::class, $entity);
        $this->assertSame(42, $entity->id);
        $this->assertSame(99, $entity->accountId);
        $this->assertSame(10, $entity->role);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findEntity

    #region Data Providers -----------------------------------------------------

    static function invalidModelDataProvider()
    {
        return [
            #region Account
            'account: id missing' => [
                'table' => 'account',
                'data' => [],
                'exceptionMessage' => "Required field 'id' is missing."
            ],
            'account: id not an integer' => [
                'table' => 'account',
                'data' => [
                    'id' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'id' must be an integer."
            ],
            'account: id less than one' => [
                'table' => 'account',
                'data' => [
                    'id' => 0
                ],
                'exceptionMessage' => "Field 'id' must have a minimum value of 1."
            ],
            'account: email missing' => [
                'table' => 'account',
                'data' => [
                    'id' => 42
                ],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'account: email invalid' => [
                'table' => 'account',
                'data' => [
                    'id' => 42,
                    'email' => 'invalid-email'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'account: passwordHash missing' => [
                'table' => 'account',
                'data' => [
                    'id' => 42,
                    'email' => 'john@example.com'
                ],
                'exceptionMessage' => "Required field 'passwordHash' is missing."
            ],
            'account: passwordHash invalid' => [
                'table' => 'account',
                'data' => [
                    'id' => 42,
                    'email' => 'john@example.com',
                    'passwordHash' => 'invalid-hash'
                ],
                'exceptionMessage' => "Field 'passwordHash' must match the required pattern: "
                    . SecurityService::PASSWORD_HASH_PATTERN
            ],
            'account: displayName missing' => [
                'table' => 'account',
                'data' => [
                    'id' => 42,
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123'
                ],
                'exceptionMessage' => "Required field 'displayName' is missing."
            ],
            'account: displayName invalid' => [
                'table' => 'account',
                'data' => [
                    'id' => 42,
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
                    'id' => 42,
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John'
                ],
                'exceptionMessage' => "Required field 'timeActivated' is missing."
            ],
            'account: timeActivated invalid' => [
                'table' => 'account',
                'data' => [
                    'id' => 42,
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'timeActivated' => '01-01-2025'
                ],
                'exceptionMessage' => "Field 'timeActivated' must match the datetime format: Y-m-d H:i:s"
            ],
            'account: timeLastLogin missing' => [
                'table' => 'account',
                'data' => [
                    'id' => 42,
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
                    'id' => 42,
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'timeActivated' => '2025-01-01 00:00:00',
                    'timeLastLogin' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeLastLogin' must match the datetime format: Y-m-d H:i:s"
            ],
            #endregion Account
            #region AccountRole
            'accountRole: id missing' => [
                'table' => 'accountrole',
                'data' => [],
                'exceptionMessage' => "Required field 'id' is missing."
            ],
            'accountRole: id not an integer' => [
                'table' => 'accountrole',
                'data' => [
                    'id' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'id' must be an integer."
            ],
            'accountRole: id less than one' => [
                'table' => 'accountrole',
                'data' => [
                    'id' => 0
                ],
                'exceptionMessage' => "Field 'id' must have a minimum value of 1."
            ],
            'accountRole: accountId missing' => [
                'table' => 'accountrole',
                'data' => [
                    'id' => 42
                ],
                'exceptionMessage' => "Required field 'accountId' is missing."
            ],
            'accountRole: accountId not an integer' => [
                'table' => 'accountrole',
                'data' => [
                    'id' => 42,
                    'accountId' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'accountId' must be an integer."
            ],
            'accountRole: accountId less than one' => [
                'table' => 'accountrole',
                'data' => [
                    'id' => 42,
                    'accountId' => 0
                ],
                'exceptionMessage' => "Field 'accountId' must have a minimum value of 1."
            ],
            'accountRole: role missing' => [
                'table' => 'accountrole',
                'data' => [
                    'id' => 42,
                    'accountId' => 1
                ],
                'exceptionMessage' => "Required field 'role' is missing."
            ],
            'accountRole: role invalid enum value' => [
                'table' => 'accountrole',
                'data' => [
                    'id' => 42,
                    'accountId' => 1,
                    'role' => 999
                ],
                'exceptionMessage' => "Field 'role' must be a valid value of enum 'Peneus\Model\Role'."
            ],
            #endregion AccountRole
            #region PendingAccount
            'pendingAccount: id missing' => [
                'table' => 'pendingaccount',
                'data' => [],
                'exceptionMessage' => "Required field 'id' is missing."
            ],
            'pendingAccount: id not an integer' => [
                'table' => 'pendingaccount',
                'data' => [
                    'id' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'id' must be an integer."
            ],
            'pendingAccount: id less than one' => [
                'table' => 'pendingaccount',
                'data' => [
                    'id' => 0
                ],
                'exceptionMessage' => "Field 'id' must have a minimum value of 1."
            ],
            'pendingAccount: email missing' => [
                'table' => 'pendingaccount',
                'data' => [
                    'id' => 42
                ],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'pendingAccount: email invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'id' => 42,
                    'email' => 'invalid-email'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'pendingAccount: passwordHash missing' => [
                'table' => 'pendingaccount',
                'data' => [
                    'id' => 42,
                    'email' => 'john@example.com',
                ],
                'exceptionMessage' => "Required field 'passwordHash' is missing."
            ],
            'pendingAccount: passwordHash invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'id' => 42,
                    'email' => 'john@example.com',
                    'passwordHash' => 'invalid-hash'
                ],
                'exceptionMessage' => "Field 'passwordHash' must match the required pattern: "
                    . SecurityService::PASSWORD_HASH_PATTERN
            ],
            'pendingAccount: displayName missing' => [
                'table' => 'pendingaccount',
                'data' => [
                    'id' => 42,
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123'
                ],
                'exceptionMessage' => "Required field 'displayName' is missing."
            ],
            'pendingAccount: displayName invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'id' => 42,
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
                    'id' => 42,
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John'
                ],
                'exceptionMessage' => "Required field 'activationCode' is missing."
            ],
            'pendingAccount: activationCode invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'id' => 42,
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
                    'id' => 42,
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
                    'id' => 42,
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'activationCode' => \str_repeat('a', 64),
                    'timeRegistered' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeRegistered' must match the datetime format: Y-m-d H:i:s"
            ],
            #endregion PendingAccount
            #region PasswordReset
            'passwordReset: id missing' => [
                'table' => 'passwordreset',
                'data' => [],
                'exceptionMessage' => "Required field 'id' is missing."
            ],
            'passwordReset: id not an integer' => [
                'table' => 'passwordreset',
                'data' => [
                    'id' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'id' must be an integer."
            ],
            'passwordReset: id less than one' => [
                'table' => 'passwordreset',
                'data' => [
                    'id' => 0
                ],
                'exceptionMessage' => "Field 'id' must have a minimum value of 1."
            ],
            'passwordReset: accountId missing' => [
                'table' => 'passwordreset',
                'data' => [
                    'id' => 42
                ],
                'exceptionMessage' => "Required field 'accountId' is missing."
            ],
            'passwordReset: accountId not an integer' => [
                'table' => 'passwordreset',
                'data' => [
                    'id' => 42,
                    'accountId' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'accountId' must be an integer."
            ],
            'passwordReset: accountId less than one' => [
                'table' => 'passwordreset',
                'data' => [
                    'id' => 42,
                    'accountId' => 0
                ],
                'exceptionMessage' => "Field 'accountId' must have a minimum value of 1."
            ],
            'passwordReset: resetCode missing' => [
                'table' => 'passwordreset',
                'data' => [
                    'id' => 42,
                    'accountId' => 1
                ],
                'exceptionMessage' => "Required field 'resetCode' is missing."
            ],
            'passwordReset: resetCode invalid' => [
                'table' => 'passwordreset',
                'data' => [
                    'id' => 42,
                    'accountId' => 1,
                    'resetCode' => 'invalid-token'
                ],
                'exceptionMessage' => "Field 'resetCode' must match the required pattern: "
                    . SecurityService::TOKEN_DEFAULT_PATTERN
            ],
            'passwordReset: timeRequested missing' => [
                'table' => 'passwordreset',
                'data' => [
                    'id' => 42,
                    'accountId' => 1,
                    'resetCode' => \str_repeat('a', 64)
                ],
                'exceptionMessage' => "Required field 'timeRequested' is missing."
            ],
            'passwordReset: timeRequested invalid' => [
                'table' => 'passwordreset',
                'data' => [
                    'id' => 42,
                    'accountId' => 1,
                    'resetCode' => \str_repeat('a', 64),
                    'timeRequested' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeRequested' must match the datetime format: Y-m-d H:i:s"
            ],
            #endregion PasswordReset
        ];
    }

    #endregion Data Providers
}
