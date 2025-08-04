<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Management\CreateTableAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Peneus\Model\Entity;
use \Peneus\Model\ViewEntity;
use \TestToolkit\AccessHelper;

#[CoversClass(CreateTableAction::class)]
class CreateTableActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->createConfig());
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Config::ReplaceInstance($this->originalConfig);
    }

    private function createConfig(): Config
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(string ...$mockedMethods): CreateTableAction
    {
        return $this->getMockBuilder(CreateTableAction::class)
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
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsWhenCreateTableFails()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $entity = new class() extends Entity {
            public static function CreateTable(): bool { return false; }
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
            "Failed to create table for: $entityClass");
        $sut->__construct();
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteReturnsNullWhenCreateTableSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $entity = new class() extends Entity {
            public static function CreateTable(): bool { return true; }
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
