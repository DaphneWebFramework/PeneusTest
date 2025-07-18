<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\Management\DeleteRecordAction;

use \Harmonia\Config;
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

    function testOnExecuteThrowsIfIdIsMissing()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['table' => 'accountrole']);
        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required field 'id' is missing.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    #[DataProviderExternal(DataHelper::class, 'NonIntegerExcludingNumericStringProvider')]
    function testOnExecuteThrowsIfIdIsNotInteger($value)
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['table' => 'accountrole']);
        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['id' => $value]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'id' must be an integer.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfIdIsLessThanOne()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['table' => 'accountrole']);
        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['id' => 0]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'id' must have a minimum value of 1.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEntityNotFound()
    {
        $sut = $this->systemUnderTest('findEntity');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['table' => 'accountrole']);
        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['id' => 42]);
        $sut->expects($this->once())
            ->method('findEntity')
            ->with(AccountRole::class, 42)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Record with ID 42 not found in table 'accountrole'.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDeleteFails()
    {
        $sut = $this->systemUnderTest('findEntity');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $formParams = $this->createMock(CArray::class);
        $entity = $this->createMock(AccountRole::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['table' => 'accountrole']);
        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['id' => 42]);
        $sut->expects($this->once())
            ->method('findEntity')
            ->with(AccountRole::class, 42)
            ->willReturn($entity);
        $entity->expects($this->once())
            ->method('Delete')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete record from table 'accountrole'.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteReturnsNullOnSuccess()
    {
        $sut = $this->systemUnderTest('findEntity');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $formParams = $this->createMock(CArray::class);
        $entity = $this->createMock(AccountRole::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['table' => 'accountrole']);
        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['id' => 42]);
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
            sql: 'SELECT * FROM accountrole WHERE id = :id LIMIT 1',
            bindings: ['id' => 42],
            result: null,
            times: 1
        );

        $this->assertNull(AccessHelper::CallMethod(
            $sut,
            'findEntity',
            [AccountRole::class, 42]
        ));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindEntityReturnsEntityWhenFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM accountrole WHERE id = :id LIMIT 1',
            bindings: ['id' => 42],
            result: [[
                'id' => 42,
                'accountId' => 99,
                'role' => 10
            ]],
            times: 1
        );

        $result = AccessHelper::CallMethod(
            $sut,
            'findEntity',
            [AccountRole::class, 42]
        );
        $this->assertInstanceOf(AccountRole::class, $result);
        $this->assertSame(42, $result->id);
        $this->assertSame(99, $result->accountId);
        $this->assertSame(10, $result->role);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findEntity
}
