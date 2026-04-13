<?php declare(strict_types=1);
namespace suite\Api\Actions\Management;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Management\DeleteRecordAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Peneus\Model\Entity;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(DeleteRecordAction::class)]
class DeleteRecordActionTest extends TestCase
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
        $sut = $this->systemUnderTest('validatePayload', 'tryFindEntity');
        $payload = (object)[
            'entityClass' => Entity::class,
            'data' => ['id' => 42]
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('tryFindEntity')
            ->with($payload->entityClass, 42)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Record not found.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEntityDeleteFails()
    {
        $sut = $this->systemUnderTest('validatePayload', 'tryFindEntity');
        $payload = (object)[
            'entityClass' => Entity::class,
            'data' => ['id' => 42]
        ];
        $entity = $this->createMock(Entity::class);

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('tryFindEntity')
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
        $sut = $this->systemUnderTest('validatePayload', 'tryFindEntity');
        $payload = (object)[
            'entityClass' => Entity::class,
            'data' => ['id' => 42]
        ];
        $entity = $this->createMock(Entity::class);

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('tryFindEntity')
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
        $sut = $this->systemUnderTest('resolveEntityClass');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $query = ['table' => 'entity'];
        $body = ['id' => 42];
        $expected = (object)[
            'entityClass' => Entity::class,
            'data' => $body
        ];

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($query);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('entity')
            ->willReturn(Entity::class);
        $request->expects($this->once())
            ->method('JsonBody')
            ->willReturn($body);

        $actual = ah::CallMethod($sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region Data Providers -----------------------------------------------------

    /**
     * @return array<string, array{
     *   query: array<string, mixed>,
     *   body: array<string, mixed>,
     *   exceptionMessage: string
     * }>
     */
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
