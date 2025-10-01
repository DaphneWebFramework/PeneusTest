<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\Management\DeleteRecordAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\AccountRole;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

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
            'id' => 42
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

    function testOnExecuteThrowsIfEntityDeleteFails()
    {
        $sut = $this->systemUnderTest('findEntity');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $data = [
            'id' => 42
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
            ->method('Delete')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Failed to delete record with ID 42 from table 'accountrole'.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteReturnsNullWhenEntityDeleteSucceeds()
    {
        $sut = $this->systemUnderTest('findEntity');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $data = [
            'id' => 42
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
            ->method('Delete')
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
            'id missing' => [
                'table' => 'account',
                'data' => [],
                'exceptionMessage' => "Required field 'id' is missing."
            ],
            'id not an integer' => [
                'table' => 'account',
                'data' => [
                    'id' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'id' must be an integer."
            ],
            'id less than one' => [
                'table' => 'account',
                'data' => [
                    'id' => 0
                ],
                'exceptionMessage' => "Field 'id' must have a minimum value of 1."
            ],
        ];
    }

    #endregion Data Providers
}
