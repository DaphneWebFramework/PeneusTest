<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\Management\AddRecordAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Services\SecurityService;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(AddRecordAction::class)]
class AddRecordActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->config());
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Config::ReplaceInstance($this->originalConfig);
    }

    private function config()
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
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
        $this->expectExceptionMessage("Failed to add record to table 'accountrole'.");
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
                'data' => [],
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
                    'displayName' => 'John Doe'
                ],
                'exceptionMessage' => "Required field 'timeActivated' is missing."
            ],
            'account: timeActivated invalid' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John Doe',
                    'timeActivated' => '01-01-2025'
                ],
                'exceptionMessage' => "Field 'timeActivated' must match the datetime format: Y-m-d H:i:s"
            ],
            'account: timeLastLogin missing' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John Doe',
                    'timeActivated' => '2025-01-01 00:00:00'
                ],
                'exceptionMessage' => "Required field 'timeLastLogin' is missing."
            ],
            'account: timeLastLogin invalid' => [
                'table' => 'account',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John Doe',
                    'timeActivated' => '2025-01-01 00:00:00',
                    'timeLastLogin' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeLastLogin' must match the datetime format: Y-m-d H:i:s"
            ],
            #endregion Account
            #region AccountRole
            'accountRole: accountId missing' => [
                'table' => 'accountrole',
                'data' => [],
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
            'accountRole: role not an integer' => [
                'table' => 'accountrole',
                'data' => [
                    'accountId' => 1,
                    'role' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'role' must be an integer."
            ],
            'accountRole: role invalid enum value' => [
                'table' => 'accountrole',
                'data' => [
                    'accountId' => 1,
                    'role' => 999
                ],
                'exceptionMessage' => "Field 'role' failed custom validation."
            ],
            #endregion AccountRole
            #region PendingAccount
            'pendingAccount: email missing' => [
                'table' => 'pendingaccount',
                'data' => [],
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
                    'displayName' => 'John Doe'
                ],
                'exceptionMessage' => "Required field 'activationCode' is missing."
            ],
            'pendingAccount: activationCode invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John Doe',
                    'activationCode' => 'invalid-code'
                ],
                'exceptionMessage' => "Field 'activationCode' must match the required pattern: "
                    . SecurityService::TOKEN_PATTERN
            ],
            'pendingAccount: timeRegistered missing' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John Doe',
                    'activationCode' => \str_repeat('a', 64),
                ],
                'exceptionMessage' => "Required field 'timeRegistered' is missing."
            ],
            'pendingAccount: timeRegistered invalid' => [
                'table' => 'pendingaccount',
                'data' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John Doe',
                    'activationCode' => \str_repeat('a', 64),
                    'timeRegistered' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeRegistered' must match the datetime format: Y-m-d H:i:s"
            ],
            #endregion PendingAccount
            #region PasswordReset
            'passwordReset: accountId missing' => [
                'table' => 'passwordreset',
                'data' => [],
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
                    . SecurityService::TOKEN_PATTERN
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
                'exceptionMessage' => "Field 'timeRequested' must match the datetime format: Y-m-d H:i:s"
            ],
            #endregion PasswordReset
        ];
    }

    #endregion Data Providers
}
