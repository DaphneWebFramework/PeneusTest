<?php declare(strict_types=1);
namespace suite\Api;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\DashboardRegistry;

use \Peneus\Model\Entity;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(DashboardRegistry::class)]
class DashboardRegistryTest extends TestCase
{
    private function systemUnderTest(string ...$mockedMethods): DashboardRegistry
    {
        $mock = $this->getMockBuilder(DashboardRegistry::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
        return ah::CallConstructor($mock);
    }

    #region Register -----------------------------------------------------------

    function testRegisterThrowsIfClassIsNotAnEntity()
    {
        $sut = $this->systemUnderTest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Class must be a subclass of Entity class: NotAnEntity");
        ah::CallMethod($sut, 'Register', ['NotAnEntity', []]);
    }

    function testRegisterSucceedsIfClassIsAnEntity()
    {
        $sut = $this->systemUnderTest();
        $entity = new class extends Entity {};

        ah::CallMethod($sut, 'Register', [$entity::class, []]);
        $this->expectNotToPerformAssertions();
    }

    function testRegisterOverwritesPreviousRegistrationForSameTable()
    {
        $sut = $this->systemUnderTest();

        // 1. Define Entity A with initial rules
        $entityA = new class extends Entity {
            public static function TableName(): string {
                return 'table-name';
            }
        };
        $rulesA = ['name' => ['required']];
        ah::CallMethod($sut, 'Register', [$entityA::class, $rulesA]);

        // 2. Define Entity B (different class, same table) with new rules
        $entityB = new class extends Entity {
            public static function TableName(): string {
                return 'table-name';
            }
        };
        $rulesB = ['name' => ['optional']];
        ah::CallMethod($sut, 'Register', [$entityB::class, $rulesB]);

        // 3. Assert the class and rules are overwritten
        $this->assertSame(
            $entityB::class,
            ah::CallMethod($sut, 'EntityClassFor', ['table-name'])
        );
        $this->assertSame(
            $rulesB,
            ah::CallMethod($sut, 'ValidationRulesFor', ['table-name'])
        );
    }

    #endregion Register

    #region EntityClassFor -----------------------------------------------------

    function testEntityClassForReturnsNullIfNotRegistered()
    {
        $sut = $this->systemUnderTest();

        $this->assertNull(
            ah::CallMethod($sut, 'EntityClassFor', ['table-name'])
        );
    }

    function testEntityClassForReturnsClassIfRegistered()
    {
        $sut = $this->systemUnderTest();
        $entity = new class extends Entity {
            public static function TableName(): string {
                return 'table-name';
            }
        };

        ah::CallMethod($sut, 'Register', [$entity::class, []]);
        $this->assertSame(
            $entity::class,
            ah::CallMethod($sut, 'EntityClassFor', ['table-name'])
        );
    }

    #endregion EntityClassFor

    #region ValidationRulesFor -------------------------------------------------

    function testValidationRulesForReturnsNullIfNotRegistered()
    {
        $sut = $this->systemUnderTest();

        $this->assertNull(
            ah::CallMethod($sut, 'ValidationRulesFor', ['table-name'])
        );
    }

    function testValidationRulesForReturnsRulesIfRegistered()
    {
        $sut = $this->systemUnderTest();
        $entity = new class extends Entity {
            public static function TableName(): string {
                return 'table-name';
            }
        };

        ah::CallMethod($sut, 'Register', [
            $entity::class,
            ['name' => ['required', 'string']]
        ]);
        $this->assertSame(
            ['name' => ['required', 'string']],
            ah::CallMethod($sut, 'ValidationRulesFor', ['table-name'])
        );
    }

    #endregion ValidationRulesFor
}
