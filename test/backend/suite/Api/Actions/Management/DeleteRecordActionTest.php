<?php declare(strict_types=1);
namespace suite\Api\Actions\Management;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Management\DeleteRecordAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\AccountRole; // sample
use \TestToolkit\AccessHelper as ah;

#[CoversClass(DeleteRecordAction::class)]
class DeleteRecordActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): DeleteRecordAction
    {
        return $this->getMockBuilder(DeleteRecordAction::class)
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

    function testOnExecuteThrowsIfEntityNotFound()
    {
        $sut = $this->systemUnderTest('validatePayload', 'findEntity');
        $payload = (object)[
            'entityClass' => AccountRole::class,
            'data' => ['id' => 42]
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('findEntity')
            ->with($payload->entityClass, 42)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Record not found.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEntityDeleteFails()
    {
        $sut = $this->systemUnderTest('validatePayload', 'findEntity');
        $payload = (object)[
            'entityClass' => AccountRole::class,
            'data' => ['id' => 42]
        ];
        $entity = $this->createMock(AccountRole::class);

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('findEntity')
            ->with($payload->entityClass, $payload->data['id'])
            ->willReturn($entity);
        $entity->expects($this->once())
            ->method('Delete')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete record.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('validatePayload', 'findEntity');
        $payload = (object)[
            'entityClass' => AccountRole::class,
            'data' => ['id' => 42]
        ];
        $entity = $this->createMock(AccountRole::class);

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('findEntity')
            ->with($payload->entityClass, $payload->data['id'])
            ->willReturn($entity);
        $entity->expects($this->once())
            ->method('Delete')
            ->willReturn(true);

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertNull($result);
    }

    #endregion onExecute

    #region validatePayload ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
    function testValidatePayloadThrows(
        array $query,
        array $body,
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
            ->willReturn($query);
        $request->expects($this->any()) // any: QueryParams validation might fail
            ->method('JsonBody')
            ->willReturn($body);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadThrowsIfModelResolutionFails()
    {
        $sut = $this->systemUnderTest('resolveEntityClass');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $query = ['table' => 'not-a-table'];

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($query);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('not-a-table')
            ->willThrowException(new \InvalidArgumentException('Expected message.'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $query = ['table' => 'accountrole'];
        $body = ['id' => 42];
        $expected = (object)[
            'entityClass' => AccountRole::class,
            'data' => $body
        ];

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($query);
        $request->expects($this->once())
            ->method('JsonBody')
            ->willReturn($body);

        $actual = ah::CallMethod($sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region findEntity ---------------------------------------------------------

    function testFindEntityReturnsNullIfNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: null,
            times: 1
        );

        $entity = ah::CallMethod($sut, 'findEntity', [AccountRole::class, 42]);
        $this->assertNull($entity);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindEntityReturnsEntityIfFound()
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

        $entity = ah::CallMethod($sut, 'findEntity', [AccountRole::class, 42]);
        $this->assertInstanceOf(AccountRole::class, $entity);
        $this->assertSame(42, $entity->id);
        $this->assertSame(99, $entity->accountId);
        $this->assertSame(10, $entity->role);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findEntity

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'table missing' => [
                'query' => [],
                'body' => [],
                'exceptionMessage' => "Required field 'table' is missing."
            ],
            'table not string' => [
                'query' => ['table' => 123],
                'body' => [],
                'exceptionMessage' => "Field 'table' must be a string."
            ],
            'id missing' => [
                'query' => ['table' => 'account'],
                'body' => [],
                'exceptionMessage' => "Required field 'id' is missing."
            ],
            'id not an integer' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'id' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'id' must be an integer."
            ],
            'id less than one' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'id' => 0
                ],
                'exceptionMessage' => "Field 'id' must have a minimum value of 1."
            ],
        ];
    }

    #endregion Data Providers
}
