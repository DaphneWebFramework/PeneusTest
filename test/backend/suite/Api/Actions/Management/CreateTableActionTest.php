<?php declare(strict_types=1);
namespace suite\Api\Actions\Management;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Management\CreateTableAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Peneus\Model\Entity;
use \Peneus\Model\ViewEntity;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(CreateTableAction::class)]
class CreateTableActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): CreateTableAction
    {
        return $this->getMockBuilder(CreateTableAction::class)
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

    function testOnExecuteThrowsIfCreateTableFails()
    {
        $sut = $this->systemUnderTest('validatePayload');
        $entity = new class() extends Entity {
            public static function CreateTable(): bool { return false; }
        };
        $payload = (object)[
            'entityClass' => \get_class($entity)
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Failed to create table for: {$payload->entityClass}");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('validatePayload');
        $entity = new class() extends Entity {
            public static function CreateTable(): bool { return true; }
        };
        $payload = (object)[
            'entityClass' => \get_class($entity)
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertNull($result);
    }

    #endregion onExecute

    #region validatePayload ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
    function testValidatePayloadThrows(array $payload, string $exceptionMessage)
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $entity = new class() extends Entity {};
        $payload = [
            'entityClass' => \get_class($entity)
        ];
        $expected = (object)$payload;

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
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
            'entityClass missing' => [
                'payload' => [],
                'exceptionMessage' => "Required field 'entityClass' is missing."
            ],
            'entityClass not a string' => [
                'payload' => ['entityClass' => 42],
                'exceptionMessage' => "Field 'entityClass' must be a string."
            ],
            'entityClass not a subclass of Entity' => [
                'payload' => ['entityClass' => \stdClass::class],
                'exceptionMessage' => "Field 'entityClass' failed custom validation."
            ],
            'entityClass is abstract' => [
                'payload' => ['entityClass' => ViewEntity::class],
                'exceptionMessage' => "Field 'entityClass' failed custom validation."
            ],
        ];
    }

    #endregion Data Providers
}
