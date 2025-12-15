<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Management\DropTableAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Peneus\Model\Entity;
use \Peneus\Model\ViewEntity;
use \TestToolkit\AccessHelper;

#[CoversClass(DropTableAction::class)]
class DropTableActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): DropTableAction
    {
        return $this->getMockBuilder(DropTableAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    #[DataProvider('invalidFormDataProvider')]
    function testOnExecuteThrowsForInvalidFormData(
        array $data,
        string $exceptionMessage
    ) {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($data);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsWhenDropTableFails()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $entity = new class() extends Entity {
            public static function DropTable(): bool { return false; }
        };
        $entityClass = \get_class($entity);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['entityClass' => $entityClass]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Failed to drop table for: $entityClass");
        $sut->__construct();
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteReturnsNullWhenDropTableSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $entity = new class() extends Entity {
            public static function DropTable(): bool { return true; }
        };
        $entityClass = \get_class($entity);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['entityClass' => $entityClass]);

        $sut->__construct();
        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertNull($result);
    }

    #endregion onExecute

    #region Data Providers -----------------------------------------------------

    static function invalidFormDataProvider()
    {
        return [
            'entityClass missing' => [
                [],
                "Required field 'entityClass' is missing."
            ],
            'entityClass not a string' => [
                ['entityClass' => 42],
                "Field 'entityClass' must be a string."
            ],
            'entityClass not a subclass of Entity' => [
                ['entityClass' => \stdClass::class],
                "Field 'entityClass' failed custom validation."
            ],
            'entityClass is abstract' => [
                ['entityClass' => ViewEntity::class],
                "Field 'entityClass' failed custom validation."
            ],
        ];
    }

    #endregion Data Providers
}
