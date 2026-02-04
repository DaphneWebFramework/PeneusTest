<?php declare(strict_types=1);
namespace suite\Api\Actions\Management;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Management\ListRecordsAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(ListRecordsAction::class)]
class ListRecordsActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): ListRecordsAction
    {
        return $this->getMockBuilder(ListRecordsAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfPayloadValidationFails()
    {
        $sut = $this->systemUnderTest('validatePayload');

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfModelResolutionFails()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass');
        $payload = (object)[
            'table' => 'not-a-table'
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('not-a-table')
            ->willThrowException(new \InvalidArgumentException('Expected message.'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteWithDefaults()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass');
        $payload = (object)[
            'table'   => 'account',
            'limit'   => 10,
            'offset'  => 0,
            'search'  => null,
            'sortkey' => null,
            'sortdir' => null
        ];
        $fakeDatabase = Database::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('account')
            ->willReturn(Account::class);
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` LIMIT 10 OFFSET 0',
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
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `account`',
            result: [[2]],
            times: 1
        );

        $result = ah::CallMethod($sut, 'onExecute');
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
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testOnExecuteWithPageNumberAndPageSize()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass');
        $payload = (object)[
            'table'   => 'accountrole',
            'limit'   => 5,
            'offset'  => 10,
            'search'  => null,
            'sortkey' => null,
            'sortdir' => null
        ];
        $fakeDatabase = Database::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('accountrole')
            ->willReturn(AccountRole::class);
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole` LIMIT 5 OFFSET 10',
            result: [
                ['id' => 42, 'accountId' => 101, 'role' => 99]
            ],
            times: 1
        );
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `accountrole`',
            result: [[1]],
            times: 1
        );

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(AccountRole::class, $result['data'][0]);
          $this->assertSame(42, $result['data'][0]->id);
          $this->assertSame(101, $result['data'][0]->accountId);
          $this->assertSame(99, $result['data'][0]->role);
        $this->assertSame(1, $result['total']);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testOnExecuteWithSearchTerm()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass');
        $payload = (object)[
            'table'   => 'account',
            'limit'   => 10,
            'offset'  => 0,
            'search'  => '100%_core\dev',
            'sortkey' => null,
            'sortdir' => null
        ];
        $escapedSearchTerm = '%' . \strtr($payload->search, [
            '\\' => '\\\\',
            '%'  => '\%',
            '_'  => '\_'
        ]) . '%';
        $fakeDatabase = Database::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('account')
            ->willReturn(Account::class);
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE '
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
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `account` WHERE '
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

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(Account::class, $result['data'][0]);
          $this->assertSame(1, $result['data'][0]->id);
          $this->assertSame('one@example.com', $result['data'][0]->email);
          $this->assertSame('abc123', $result['data'][0]->passwordHash);
          $this->assertSame('Member of 100%_core\dev team', $result['data'][0]->displayName);
          $this->assertSame('2024-01-01 00:00:00', $result['data'][0]->timeActivated->format('Y-m-d H:i:s'));
          $this->assertSame('2024-01-02 12:00:00', $result['data'][0]->timeLastLogin->format('Y-m-d H:i:s'));
        $this->assertSame(1, $result['total']);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testOnExecuteThrowsIfSortKeyDoesNotExist()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass');
        $payload = (object)[
            'table'   => 'accountrole',
            'limit'   => 10,
            'offset'  => 0,
            'search'  => null,
            'sortkey' => 'not-a-column',
            'sortdir' => 'ASC'
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('accountrole')
            ->willReturn(AccountRole::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Table 'accountrole' does not have a column named 'not-a-column'.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteWithSortKey()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass');
        $payload = (object)[
            'table'   => 'accountrole',
            'limit'   => 10,
            'offset'  => 0,
            'search'  => null,
            'sortkey' => 'role',
            'sortdir' => null
        ];
        $fakeDatabase = Database::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('accountrole')
            ->willReturn(AccountRole::class);
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole` ORDER BY `role` LIMIT 10 OFFSET 0',
            result: [
                ['id' => 5, 'accountId' => 200, 'role' => 10]
            ],
            times: 1
        );
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `accountrole`',
            result: [[1]],
            times: 1
        );

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(AccountRole::class, $result['data'][0]);
          $this->assertSame(5, $result['data'][0]->id);
          $this->assertSame(200, $result['data'][0]->accountId);
          $this->assertSame(10, $result['data'][0]->role);
        $this->assertSame(1, $result['total']);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testOnExecuteWithSortKeyAndSortDirection()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass');
        $payload = (object)[
            'table'   => 'accountrole',
            'limit'   => 10,
            'offset'  => 0,
            'search'  => null,
            'sortkey' => 'role',
            'sortdir' => 'DESC'
        ];
        $fakeDatabase = Database::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('accountrole')
            ->willReturn(AccountRole::class);
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole` ORDER BY `role` DESC LIMIT 10 OFFSET 0',
            result: [
                ['id' => 7, 'accountId' => 300, 'role' => 99]
            ],
            times: 1
        );
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `accountrole`',
            result: [[1]],
            times: 1
        );

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(AccountRole::class, $result['data'][0]);
          $this->assertSame(7, $result['data'][0]->id);
          $this->assertSame(300, $result['data'][0]->accountId);
          $this->assertSame(99, $result['data'][0]->role);
        $this->assertSame(1, $result['total']);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

function testOnExecuteWithAllParameters()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass');
        $payload = (object)[
            'table'   => 'accountrole',
            'limit'   => 3,
            'offset'  => 3,
            'search'  => '20',
            'sortkey' => 'role',
            'sortdir' => 'ASC'
        ];
        $fakeDatabase = Database::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('accountrole')
            ->willReturn(AccountRole::class);
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole` WHERE '
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
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `accountrole` WHERE '
               . '`id` LIKE :search OR '
               . '`accountId` LIKE :search OR '
               . '`role` LIKE :search',
            bindings: ['search' => '%20%'],
            result: [[1]],
            times: 1
        );

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(AccountRole::class, $result['data'][0]);
          $this->assertSame(10, $result['data'][0]->id);
          $this->assertSame(9001, $result['data'][0]->accountId);
          $this->assertSame(20, $result['data'][0]->role);
        $this->assertSame(1, $result['total']);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion onExecute

    #region validatePayload ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
    function testValidatePayloadThrows(array $payload, string $exceptionMessage)
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadWithMinimalInput()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $payload = [
            'table' => 'account'
        ];
        $expected = (object)[
            'table'   => 'account',
            'limit'   => 10,
            'offset'  => 0,
            'search'  => null,
            'sortkey' => null,
            'sortdir' => null
        ];

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $actual = ah::CallMethod($sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    function testValidatePayloadWithFullInput()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $payload = [
            'table'    => 'account',
            'page'     => 3,
            'pagesize' => 20,
            'search'   => 'query',
            'sortkey'  => 'id',
            'sortdir'  => 'desc'
        ];
        $expected = (object)[
            'table'   => 'account',
            'limit'   => 20,
            'offset'  => 40, // (3 - 1) * 20
            'search'  => 'query',
            'sortkey' => 'id',
            'sortdir' => 'desc'
        ];

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $actual = ah::CallMethod($sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'table missing' => [
                'payload' => [],
                'exceptionMessage' => "Required field 'table' is missing."
            ],
            'table not string' => [
                'payload' => ['table' => 123],
                'exceptionMessage' => "Field 'table' must be a string."
            ],
            'page not integer' => [
                'payload' => ['table' => 'account', 'page' => 'abc'],
                'exceptionMessage' => "Field 'page' must be an integer."
            ],
            'page too low' => [
                'payload' => ['table' => 'account', 'page' => 0],
                'exceptionMessage' => "Field 'page' must have a minimum value of 1."
            ],
            'pagesize not integer' => [
                'payload' => ['table' => 'account', 'pagesize' => 'abc'],
                'exceptionMessage' => "Field 'pagesize' must be an integer."
            ],
            'pagesize too low' => [
                'payload' => ['table' => 'account', 'pagesize' => 0],
                'exceptionMessage' => "Field 'pagesize' must have a minimum value of 1."
            ],
            'pagesize too high' => [
                'payload' => ['table' => 'account', 'pagesize' => 101],
                'exceptionMessage' => "Field 'pagesize' must have a maximum value of 100."
            ],
            'search not string' => [
                'payload' => ['table' => 'account', 'search' => 123],
                'exceptionMessage' => "Field 'search' must be a string."
            ],
            'sortkey not string' => [
                'payload' => ['table' => 'account', 'sortkey' => 123],
                'exceptionMessage' => "Field 'sortkey' must be a string."
            ],
            'sortdir not string' => [
                'payload' => ['table' => 'account', 'sortdir' => 123],
                'exceptionMessage' => "Field 'sortdir' must be a string."
            ],
            'sortdir not asc or desc' => [
                'payload' => ['table' => 'account', 'sortdir' => 'up'],
                'exceptionMessage' => "Field 'sortdir' failed custom validation."
            ],
        ];
    }

    #endregion Data Providers
}
