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

    private function escapeSearchTerm(string $search): string
    {
        return '%' . \strtr($search, [
            '\\' => '\\\\',
            '%'  => '\%',
            '_'  => '\_'
        ]) . '%';
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

    function testOnExecuteWithPageNumberAndPageSize()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass',
            'findEntities', 'countEntities');
        $payload = (object)[
            'table'   => 'account',
            'limit'   => 5,
            'offset'  => 10,
            'search'  => null,
            'sortkey' => null,
            'sortdir' => null
        ];
        $expected = [
            'data' => [new Account(), new Account()],
            'total' => 2
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with($payload->table)
            ->willReturn(Account::class);
        $sut->expects($this->once())
            ->method('findEntities')
            ->with(
                Account::class,
                null,
                null,
                null,
                $payload->limit,
                $payload->offset
            )
            ->willReturn($expected['data']);
        $sut->expects($this->once())
            ->method('countEntities')
            ->with(Account::class, null, null)
            ->willReturn($expected['total']);

        $actual = ah::CallMethod($sut, 'onExecute');

        $this->assertEquals($expected, $actual);
    }

    function testOnExecuteWithSearchTerm()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass',
            'findEntities', 'countEntities');
        $payload = (object)[
            'table'   => 'account',
            'limit'   => 10,
            'offset'  => 0,
            'search'  => '100%_core\dev',
            'sortkey' => null,
            'sortdir' => null
        ];
        $condition = '`id` LIKE :search OR '
                   . '`email` LIKE :search OR '
                   . '`passwordHash` LIKE :search OR '
                   . '`displayName` LIKE :search OR '
                   . '`timeActivated` LIKE :search OR '
                   . '`timeLastLogin` LIKE :search';
        $bindings = ['search' => $this->escapeSearchTerm($payload->search)];
        $expected = [
            'data' => [new Account()],
            'total' => 1
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with($payload->table)
            ->willReturn(Account::class);
        $sut->expects($this->once())
            ->method('findEntities')
            ->with(
                Account::class,
                $condition,
                $bindings,
                null,
                $payload->limit,
                $payload->offset
            )
            ->willReturn($expected['data']);
        $sut->expects($this->once())
            ->method('countEntities')
            ->with(Account::class, $condition, $bindings)
            ->willReturn($expected['total']);

        $actual = ah::CallMethod($sut, 'onExecute');

        $this->assertEquals($expected, $actual);
    }

    function testOnExecuteThrowsIfSortKeyDoesNotExist()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass');
        $payload = (object)[
            'table'   => 'account',
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
            ->with($payload->table)
            ->willReturn(Account::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Table '{$payload->table}' does not have a column named '{$payload->sortkey}'.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteWithSortKey()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass',
            'findEntities', 'countEntities');
        $payload = (object)[
            'table'   => 'account',
            'limit'   => 10,
            'offset'  => 0,
            'search'  => null,
            'sortkey' => 'displayName',
            'sortdir' => null
        ];
        $expected = [
            'data' => [new Account()],
            'total' => 1
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with($payload->table)
            ->willReturn(Account::class);
        $sut->expects($this->once())
            ->method('findEntities')
            ->with(
                Account::class,
                null,
                null,
                "`{$payload->sortkey}`",
                $payload->limit,
                $payload->offset
            )
            ->willReturn($expected['data']);
        $sut->expects($this->once())
            ->method('countEntities')
            ->with(Account::class, null, null)
            ->willReturn($expected['total']);

        $actual = ah::CallMethod($sut, 'onExecute');

        $this->assertEquals($expected, $actual);
    }

    function testOnExecuteWithSortKeyAndSortDirection()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass',
            'findEntities', 'countEntities');
        $payload = (object)[
            'table'   => 'account',
            'limit'   => 10,
            'offset'  => 0,
            'search'  => null,
            'sortkey' => 'email',
            'sortdir' => 'desc'
        ];
        $expected = [
            'data' => [new Account()],
            'total' => 1
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with($payload->table)
            ->willReturn(Account::class);
        $sut->expects($this->once())
            ->method('findEntities')
            ->with(
                Account::class,
                null,
                null,
                "`{$payload->sortkey}` " . \strtoupper($payload->sortdir),
                $payload->limit,
                $payload->offset
            )
            ->willReturn($expected['data']);
        $sut->expects($this->once())
            ->method('countEntities')
            ->with(Account::class, null, null)
            ->willReturn($expected['total']);

        $actual = ah::CallMethod($sut, 'onExecute');

        $this->assertEquals($expected, $actual);
    }

    function testOnExecuteWithAllParameters()
    {
        $sut = $this->systemUnderTest('validatePayload', 'resolveEntityClass',
            'findEntities', 'countEntities');
        $payload = (object)[
            'table'   => 'account',
            'limit'   => 3,
            'offset'  => 3,
            'search'  => 'Alice',
            'sortkey' => 'id',
            'sortdir' => 'asc'
        ];
        $condition = '`id` LIKE :search OR '
                   . '`email` LIKE :search OR '
                   . '`passwordHash` LIKE :search OR '
                   . '`displayName` LIKE :search OR '
                   . '`timeActivated` LIKE :search OR '
                   . '`timeLastLogin` LIKE :search';
        $bindings = ['search' => $this->escapeSearchTerm($payload->search)];
        $expected = [
            'data' => [new Account()],
            'total' => 1
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with($payload->table)
            ->willReturn(Account::class);
        $sut->expects($this->once())
            ->method('findEntities')
            ->with(
                Account::class,
                $condition,
                $bindings,
                "`{$payload->sortkey}` " . \strtoupper($payload->sortdir),
                $payload->limit,
                $payload->offset
            )
            ->willReturn($expected['data']);
        $sut->expects($this->once())
            ->method('countEntities')
            ->with(Account::class, $condition, $bindings)
            ->willReturn($expected['total']);

        $actual = ah::CallMethod($sut, 'onExecute');

        $this->assertEquals($expected, $actual);
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

    #region findEntities -------------------------------------------------------

    function testFindEntitiesWithMinimalParameters()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account`',
            result: [],
            times: 1
        );

        $actual = ah::CallMethod($sut, 'findEntities', [
            Account::class,
            null,
            null,
            null,
            null,
            null
        ]);

        $this->assertSame([], $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindEntitiesWithAllParameters()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();
        $condition = '`displayName` LIKE :search';
        $bindings = ['search' => $this->escapeSearchTerm('Alice')];
        $orderBy = '`id` DESC';
        $limit = 5;
        $offset = 10;
        $data = [[
            'id' => 2,
            'email' => 'alice.campbell@example.com',
            'passwordHash' => 'abc123',
            'displayName' => 'Alice',
            'timeActivated' => '2023-01-01 00:00:00',
            'timeLastLogin' => '2023-01-10 00:00:00'
        ], [
            'id' => 1,
            'email' => 'alice.summers@example.com',
            'passwordHash' => 'def456',
            'displayName' => 'Alice',
            'timeActivated' => '2023-02-01 00:00:00',
            'timeLastLogin' => null
        ]];
        $expected = [
            new Account($data[0]),
            new Account($data[1])
        ];

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account`'
               . " WHERE $condition"
               . " ORDER BY $orderBy"
               . " LIMIT $limit"
               . " OFFSET $offset",
            bindings: $bindings,
            result: $data,
            times: 1
        );

        $actual = ah::CallMethod($sut, 'findEntities', [
            Account::class,
            $condition,
            $bindings,
            $orderBy,
            $limit,
            $offset
        ]);

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findEntities

    #region countEntities ------------------------------------------------------

    function testCountEntitiesWithMinimalParameters()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `account`',
            result: [[0]],
            times: 1
        );

        $actual = ah::CallMethod($sut, 'countEntities', [
            Account::class,
            null,
            null
        ]);

        $this->assertSame(0, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testCountEntitiesWithAllParameters()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();
        $condition = '`timeActivated` IS NOT NULL';
        $bindings = [];

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `account`'
               . " WHERE $condition",
            bindings: $bindings,
            result: [[7]],
            times: 1
        );

        $actual = ah::CallMethod($sut, 'countEntities', [
            Account::class,
            $condition,
            $bindings
        ]);

        $this->assertSame(7, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion countEntities

    #region Data Providers -----------------------------------------------------

    /**
     * @return array<string, array{
     *   payload: array<string, mixed>,
     *   exceptionMessage: string
     * }>
     */
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
