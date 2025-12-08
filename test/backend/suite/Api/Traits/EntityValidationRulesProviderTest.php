<?php declare(strict_types=1);
namespace suite\Api\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Traits\EntityValidationRulesProvider;

use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;
use \Peneus\Model\PersistentLogin;

use \Peneus\Api\DashboardRegistry;
use \Peneus\Model\Entity;
use \TestToolkit\AccessHelper as ah;

class _EntityValidationRulesProvider { use EntityValidationRulesProvider; }

#[CoversClass(_EntityValidationRulesProvider::class)]
class EntityValidationRulesProviderTest extends TestCase
{
    private ?DashboardRegistry $originalDashboardRegistry = null;

    protected function setUp(): void
    {
        $this->originalDashboardRegistry =
            DashboardRegistry::ReplaceInstance($this->createMock(DashboardRegistry::class));
    }

    protected function tearDown(): void
    {
        DashboardRegistry::ReplaceInstance($this->originalDashboardRegistry);
    }

    private function systemUnderTest(string ...$mockedMethods): _EntityValidationRulesProvider
    {
        return $this->getMockBuilder(_EntityValidationRulesProvider::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region validationRulesForCreate -------------------------------------------

    function testValidationRulesForCreateThrowsIfClassIsNotEntity()
    {
        $sut = $this->systemUnderTest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Class must be a subclass of Entity class: NotAnEntity");
        ah::CallMethod($sut, 'validationRulesForCreate', ['NotAnEntity']);
    }

    #[DataProvider('builtinEntityClassesProvider')]
    function testValidationRulesForCreateReturnsRulesFromBuiltinList(
        string $entityClass
    ) {
        $sut = $this->systemUnderTest();
        $dashboardRegistry = DashboardRegistry::Instance();

        $dashboardRegistry->expects($this->never())
            ->method('ValidationRulesFor');

        $rules = ah::CallMethod($sut, 'validationRulesForCreate', [$entityClass]);
        $this->assertIsArray($rules); // we don't care about the contents
    }

    function testValidationRulesForCreateReturnsRulesFromDashboardRegistry()
    {
        $sut = $this->systemUnderTest();
        $entity = new class extends Entity {
            public static function TableName(): string {
                return 'table-name';
            }
        };
        $entityClass = $entity::class;
        $dashboardRegistry = DashboardRegistry::Instance();
        $rules = ['name' => ['required', 'string']];

        $dashboardRegistry->expects($this->once())
            ->method('ValidationRulesFor')
            ->with('table-name')
            ->willReturn($rules);

        $this->assertSame(
            $rules,
            ah::CallMethod($sut, 'validationRulesForCreate', [$entityClass])
        );
    }

    function testValidationRulesForCreateThrowsIfNoRulesFound()
    {
        $sut = $this->systemUnderTest();
        $entity = new class extends Entity {
            public static function TableName(): string {
                return 'table-name';
            }
        };
        $entityClass = $entity::class;
        $dashboardRegistry = DashboardRegistry::Instance();

        $dashboardRegistry->expects($this->once())
            ->method('ValidationRulesFor')
            ->with('table-name')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "No validation rules found for entity class: $entityClass");
        ah::CallMethod($sut, 'validationRulesForCreate', [$entityClass]);
    }

    #endregion validationRulesForCreate

    #region validationRulesForDelete -------------------------------------------

    function testValidationRulesForDelete()
    {
        $sut = $this->systemUnderTest();
        $expected = [
            'id' => [
                'required',
                'integer:strict',
                'min:1'
            ]
        ];

        $actual = ah::CallMethod($sut, 'validationRulesForDelete');
        $this->assertSame($expected, $actual);
    }

    #endregion validationRulesForDelete

    #region validationRulesForUpdate -------------------------------------------

    function testValidationRulesForUpdate()
    {
        $sut = $this->systemUnderTest(
            'validationRulesForDelete',
            'validationRulesForCreate'
        );
        $entity = $this->createStub(Entity::class);
        $entityClass = $entity::class;
        $rulesForDelete = ['id' => ['required', 'integer:strict', 'min:1']];
        $rulesForCreate = ['field' => ['required', 'string']];
        $expected = \array_merge($rulesForDelete, $rulesForCreate);

        $sut->expects($this->once())
            ->method('validationRulesForDelete')
            ->willReturn($rulesForDelete);
        $sut->expects($this->once())
            ->method('validationRulesForCreate')
            ->with($entityClass)
            ->willReturn($rulesForCreate);

        $actual = ah::CallMethod($sut, 'validationRulesForUpdate', [$entityClass]);
        $this->assertSame($expected, $actual);
    }

    #endregion validationRulesForUpdate

    #region Data Providers -----------------------------------------------------

    static function builtinEntityClassesProvider()
    {
        return [
            'Account'         => [Account::class],
            'AccountRole'     => [AccountRole::class],
            'PendingAccount'  => [PendingAccount::class],
            'PasswordReset'   => [PasswordReset::class],
            'PersistentLogin' => [PersistentLogin::class],
        ];
    }

    #endregion Data Providers
}
