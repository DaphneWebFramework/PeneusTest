<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\Management\ListRecordsAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(ListRecordsAction::class)]
class ListRecordsActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance(new FakeDatabase());
        $this->originalConfig =
            Config::ReplaceInstance($this->config());
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        Config::ReplaceInstance($this->originalConfig);
    }

    private function config()
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(string ...$mockedMethods): ListRecordsAction
    {
        return $this->getMockBuilder(ListRecordsAction::class)
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

    #[DataProviderExternal(DataHelper::class, 'NonIntegerExcludingNumericStringProvider')]
    function testOnExecuteThrowsIfPageNumberIsNotInteger($value)
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
                'table' => 'account',
                'page' => $value
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'page' must be an integer.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPageNumberIsLessThanOne()
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
                'table' => 'account',
                'page' => 0
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'page' must have a minimum value of 1.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    #[DataProviderExternal(DataHelper::class, 'NonIntegerExcludingNumericStringProvider')]
    function testOnExecuteThrowsIfPageSizeIsNotInteger($value)
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
                'table' => 'account',
                'pagesize' => $value
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'pagesize' must be an integer.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPageSizeIsLessThanOne()
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
                'table' => 'account',
                'pagesize' => 0
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'pagesize' must have a minimum value of 1.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPageSizeIsGreaterThanHundred()
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
                'table' => 'account',
                'pagesize' => 101
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'pagesize' must have a maximum value of 100.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    #[DataProviderExternal(DataHelper::class, 'NonStringProvider')]
    function testOnExecuteThrowsIfSearchTermIsNotString($value)
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
                'table' => 'account',
                'search' => $value
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'search' must be a string.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    #[DataProviderExternal(DataHelper::class, 'NonStringProvider')]
    function testOnExecuteThrowsIfSortKeyIsNotString($value)
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
                'table' => 'account',
                'sortkey' => $value
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'sortkey' must be a string.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    #[DataProviderExternal(DataHelper::class, 'NonStringProvider')]
    function testOnExecuteThrowsIfSortDirectionIsNotString($value)
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
                'table' => 'account',
                'sortdir' => $value
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'sortdir' must be a string.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfSortDirectionIsInvalid()
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
                'table' => 'account',
                'sortdir' => 'up'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'sortdir' failed custom validation.");
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

    function testOnExecuteReturnsFirstPageForAccountTableWithDefaults()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'table' => 'account'
            ]);
        $database->Expect(
            sql: 'SELECT * FROM account LIMIT 10 OFFSET 0',
            result: [
                [
                    'id' => 1,
                    'email' => 'alice@example.com',
                    'passwordHash' => 'abc123',
                    'displayName' => 'Alice',
                    'timeActivated' => '2023-01-01 00:00:00',
                    'timeLastLogin' => '2023-01-10 00:00:00'
                ],
                [
                    'id' => 2,
                    'email' => 'bob@example.com',
                    'passwordHash' => 'def456',
                    'displayName' => 'Bob',
                    'timeActivated' => '2023-02-01 00:00:00',
                    'timeLastLogin' => null
                ]
            ],
            times: 1
        );
        $database->Expect(
            sql: 'SELECT COUNT(*) FROM account',
            result: [[2]],
            times: 1
        );

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertCount(2, $result['data']);
        $this->assertInstanceOf(Account::class, $result['data'][0]);
          $this->assertSame(1, $result['data'][0]->id);
          $this->assertSame('alice@example.com', $result['data'][0]->email);
          $this->assertSame('abc123', $result['data'][0]->passwordHash);
          $this->assertSame('Alice', $result['data'][0]->displayName);
          $this->assertSame('2023-01-01 00:00:00', $result['data'][0]->timeActivated->format('Y-m-d H:i:s'));
          $this->assertSame('2023-01-10 00:00:00', $result['data'][0]->timeLastLogin->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(Account::class, $result['data'][1]);
          $this->assertSame(2, $result['data'][1]->id);
          $this->assertSame('bob@example.com', $result['data'][1]->email);
          $this->assertSame('def456', $result['data'][1]->passwordHash);
          $this->assertSame('Bob', $result['data'][1]->displayName);
          $this->assertSame('2023-02-01 00:00:00', $result['data'][1]->timeActivated->format('Y-m-d H:i:s'));
          $this->assertNull($result['data'][1]->timeLastLogin);
        $this->assertSame(2, $result['total']);
        $database->VerifyAllExpectationsMet();
    }

    function testOnExecuteReturnsFirstPageForAccountRoleTableWithDefaults()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'table' => 'accountrole'
            ]);
        $database->Expect(
            sql: 'SELECT * FROM accountrole LIMIT 10 OFFSET 0',
            result: [
                ['id' => 1, 'accountId' => 101, 'role' => 0],
                ['id' => 2, 'accountId' => 102, 'role' => 10],
                ['id' => 3, 'accountId' => 103, 'role' => 20]
            ],
            times: 1
        );
        $database->Expect(
            sql: 'SELECT COUNT(*) FROM accountrole',
            result: [[3]],
            times: 1
        );

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertCount(3, $result['data']);
        $this->assertInstanceOf(AccountRole::class, $result['data'][0]);
          $this->assertSame(1, $result['data'][0]->id);
          $this->assertSame(101, $result['data'][0]->accountId);
          $this->assertSame(0, $result['data'][0]->role);
        $this->assertInstanceOf(AccountRole::class, $result['data'][1]);
          $this->assertSame(2, $result['data'][1]->id);
          $this->assertSame(102, $result['data'][1]->accountId);
          $this->assertSame(10, $result['data'][1]->role);
        $this->assertInstanceOf(AccountRole::class, $result['data'][2]);
          $this->assertSame(3, $result['data'][2]->id);
          $this->assertSame(103, $result['data'][2]->accountId);
          $this->assertSame(20, $result['data'][2]->role);
        $this->assertSame(3, $result['total']);
        $database->VerifyAllExpectationsMet();
    }

    function testOnExecuteReturnsFirstPageForPasswordResetTableWithDefaults()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'table' => 'passwordreset'
            ]);
        $database->Expect(
            sql: 'SELECT * FROM passwordreset LIMIT 10 OFFSET 0',
            result: [
                [
                    'id' => 1,
                    'accountId' => 101,
                    'resetCode' => 'abc123',
                    'timeRequested' => '2024-04-01 12:30:00'
                ],
                [
                    'id' => 2,
                    'accountId' => 102,
                    'resetCode' => 'def456',
                    'timeRequested' => '2024-04-02 13:00:00'
                ]
            ],
            times: 1
        );
        $database->Expect(
            sql: 'SELECT COUNT(*) FROM passwordreset',
            result: [[2]],
            times: 1
        );

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertCount(2, $result['data']);
        $this->assertInstanceOf(PasswordReset::class, $result['data'][0]);
          $this->assertSame(1, $result['data'][0]->id);
          $this->assertSame(101, $result['data'][0]->accountId);
          $this->assertSame('abc123', $result['data'][0]->resetCode);
          $this->assertSame('2024-04-01 12:30:00', $result['data'][0]->timeRequested->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(PasswordReset::class, $result['data'][1]);
          $this->assertSame(2, $result['data'][1]->id);
          $this->assertSame(102, $result['data'][1]->accountId);
          $this->assertSame('def456', $result['data'][1]->resetCode);
          $this->assertSame('2024-04-02 13:00:00', $result['data'][1]->timeRequested->format('Y-m-d H:i:s'));
        $this->assertSame(2, $result['total']);
        $database->VerifyAllExpectationsMet();
    }

    function testOnExecuteReturnsFirstPageForPendingAccountTableWithDefaults()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'table' => 'pendingaccount'
            ]);
        $database->Expect(
            sql: 'SELECT * FROM pendingaccount LIMIT 10 OFFSET 0',
            result: [
                [
                    'id' => 1,
                    'email' => 'alice@example.com',
                    'passwordHash' => 'abc123',
                    'displayName' => 'Alice',
                    'activationCode' => 'ACT-001',
                    'timeRegistered' => '2024-05-01 10:00:00'
                ],
                [
                    'id' => 2,
                    'email' => 'bob@example.com',
                    'passwordHash' => 'def456',
                    'displayName' => 'Bob',
                    'activationCode' => 'ACT-002',
                    'timeRegistered' => '2024-05-02 11:00:00'
                ]
            ],
            times: 1
        );
        $database->Expect(
            sql: 'SELECT COUNT(*) FROM pendingaccount',
            result: [[2]],
            times: 1
        );

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertCount(2, $result['data']);
        $this->assertInstanceOf(PendingAccount::class, $result['data'][0]);
          $this->assertSame(1, $result['data'][0]->id);
          $this->assertSame('alice@example.com', $result['data'][0]->email);
          $this->assertSame('abc123', $result['data'][0]->passwordHash);
          $this->assertSame('Alice', $result['data'][0]->displayName);
          $this->assertSame('ACT-001', $result['data'][0]->activationCode);
          $this->assertSame('2024-05-01 10:00:00', $result['data'][0]->timeRegistered->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(PendingAccount::class, $result['data'][1]);
          $this->assertSame(2, $result['data'][1]->id);
          $this->assertSame('bob@example.com', $result['data'][1]->email);
          $this->assertSame('def456', $result['data'][1]->passwordHash);
          $this->assertSame('Bob', $result['data'][1]->displayName);
          $this->assertSame('ACT-002', $result['data'][1]->activationCode);
          $this->assertSame('2024-05-02 11:00:00', $result['data'][1]->timeRegistered->format('Y-m-d H:i:s'));
        $this->assertSame(2, $result['total']);
        $database->VerifyAllExpectationsMet();
    }

    function testOnExecuteWithPageNumberAndPageSize()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'table' => 'accountrole',
                'page' => 3,
                'pagesize' => 5
            ]);
        $database->Expect(
            sql: 'SELECT * FROM accountrole LIMIT 5 OFFSET 10', // (3 - 1) * 5
            result: [
                ['id' => 42, 'accountId' => 101, 'role' => 99]
            ],
            times: 1
        );
        $database->Expect(
            sql: 'SELECT COUNT(*) FROM accountrole',
            result: [[1]],
            times: 1
        );

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(AccountRole::class, $result['data'][0]);
          $this->assertSame(42, $result['data'][0]->id);
          $this->assertSame(101, $result['data'][0]->accountId);
          $this->assertSame(99, $result['data'][0]->role);
        $this->assertSame(1, $result['total']);
        $database->VerifyAllExpectationsMet();
    }

    function testOnExecuteWithSearchTerm()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $database = Database::Instance();
        $searchTerm = '100%_core\dev';
        $escapedSearchTerm = '%' . \strtr($searchTerm, [
            '\\' => '\\\\',
            '%'  => '\%',
            '_'  => '\_'
        ]) . '%';

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'table' => 'account',
                'search' => $searchTerm
            ]);
        $database->Expect(
            sql: 'SELECT * FROM account WHERE '
               . '`id` LIKE :search OR '
               . '`email` LIKE :search OR '
               . '`passwordHash` LIKE :search OR '
               . '`displayName` LIKE :search OR '
               . '`timeActivated` LIKE :search OR '
               . '`timeLastLogin` LIKE :search '
               . 'LIMIT 10 OFFSET 0',
            bindings: ['search' => $escapedSearchTerm],
            result: [[
                'id' => 1,
                'email' => 'one@example.com',
                'passwordHash' => 'abc123',
                'displayName' => 'Member of 100%_core\dev team',
                'timeActivated' => '2024-01-01 00:00:00',
                'timeLastLogin' => '2024-01-02 12:00:00'
            ]],
            times: 1
        );
        $database->Expect(
            sql: 'SELECT COUNT(*) FROM account WHERE '
               . '`id` LIKE :search OR '
               . '`email` LIKE :search OR '
               . '`passwordHash` LIKE :search OR '
               . '`displayName` LIKE :search OR '
               . '`timeActivated` LIKE :search OR '
               . '`timeLastLogin` LIKE :search',
            bindings: ['search' => $escapedSearchTerm],
            result: [[1]],
            times: 1
        );

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(Account::class, $result['data'][0]);
          $this->assertSame(1, $result['data'][0]->id);
          $this->assertSame('one@example.com', $result['data'][0]->email);
          $this->assertSame('abc123', $result['data'][0]->passwordHash);
          $this->assertSame('Member of 100%_core\dev team', $result['data'][0]->displayName);
          $this->assertSame('2024-01-01 00:00:00', $result['data'][0]->timeActivated->format('Y-m-d H:i:s'));
          $this->assertSame('2024-01-02 12:00:00', $result['data'][0]->timeLastLogin->format('Y-m-d H:i:s'));
        $this->assertSame(1, $result['total']);
        $database->VerifyAllExpectationsMet();
    }

    function testOnExecuteThrowsIfSortKeyDoesNotExist()
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
                'table' => 'accountrole',
                'sortkey' => 'not-a-column'
            ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Table 'accountrole' does not have a column named 'not-a-column'.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteWithSortKey()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'table' => 'accountrole',
                'sortkey' => 'role'
            ]);
        $database->Expect(
            sql: 'SELECT * FROM accountrole ORDER BY `role` LIMIT 10 OFFSET 0',
            result: [
                ['id' => 5, 'accountId' => 200, 'role' => 10]
            ],
            times: 1
        );
        $database->Expect(
            sql: 'SELECT COUNT(*) FROM accountrole',
            result: [[1]],
            times: 1
        );

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(AccountRole::class, $result['data'][0]);
          $this->assertSame(5, $result['data'][0]->id);
          $this->assertSame(200, $result['data'][0]->accountId);
          $this->assertSame(10, $result['data'][0]->role);
        $this->assertSame(1, $result['total']);
        $database->VerifyAllExpectationsMet();
    }

    function testOnExecuteWithSortKeyAndSortDirection()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'table' => 'accountrole',
                'sortkey' => 'role',
                'sortdir' => 'desc'
            ]);
        $database->Expect(
            sql: 'SELECT * FROM accountrole ORDER BY `role` DESC LIMIT 10 OFFSET 0',
            result: [
                ['id' => 7, 'accountId' => 300, 'role' => 99]
            ],
            times: 1
        );
        $database->Expect(
            sql: 'SELECT COUNT(*) FROM accountrole',
            result: [[1]],
            times: 1
        );

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(AccountRole::class, $result['data'][0]);
          $this->assertSame(7, $result['data'][0]->id);
          $this->assertSame(300, $result['data'][0]->accountId);
          $this->assertSame(99, $result['data'][0]->role);
        $this->assertSame(1, $result['total']);
        $database->VerifyAllExpectationsMet();
    }

    function testOnExecuteWithAllQueryParameters()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'table' => 'accountrole',
                'page' => 2,
                'pagesize' => 3,
                'search' => '20',
                'sortkey' => 'role',
                'sortdir' => 'asc'
            ]);
        $database->Expect(
            sql: 'SELECT * FROM accountrole WHERE '
               . '`id` LIKE :search OR '
               . '`accountId` LIKE :search OR '
               . '`role` LIKE :search '
               . 'ORDER BY `role` ASC '
               . 'LIMIT 3 OFFSET 3',
            bindings: ['search' => '%20%'],
            result: [
                ['id' => 10, 'accountId' => 9001, 'role' => 20]
            ],
            times: 1
        );
        $database->Expect(
            sql: 'SELECT COUNT(*) FROM accountrole WHERE '
               . '`id` LIKE :search OR '
               . '`accountId` LIKE :search OR '
               . '`role` LIKE :search',
            bindings: ['search' => '%20%'],
            result: [[1]],
            times: 1
        );

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(AccountRole::class, $result['data'][0]);
          $this->assertSame(10, $result['data'][0]->id);
          $this->assertSame(9001, $result['data'][0]->accountId);
          $this->assertSame(20, $result['data'][0]->role);
        $this->assertSame(1, $result['total']);
        $database->VerifyAllExpectationsMet();
    }

    #endregion onExecute
}
