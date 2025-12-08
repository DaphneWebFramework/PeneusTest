<?php declare(strict_types=1);
namespace suite\Api\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Traits\EntityClassResolver;

use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;
use \Peneus\Model\PersistentLogin;

use \Peneus\Api\DashboardRegistry;
use \Peneus\Model\Entity;
use \TestToolkit\AccessHelper as ah;

class _EntityClassResolver { use EntityClassResolver; }

#[CoversClass(_EntityClassResolver::class)]
class EntityClassResolverTest extends TestCase
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

    #region resolveEntityClass -------------------------------------------------

    #[DataProvider('builtinEntityClassesProvider')]
    function testResolveEntityClassReturnsClassFromBuiltinList(string $entityClass)
    {
        $sut = new _EntityClassResolver();
        $tableName = $entityClass::TableName();
        $dashboardRegistry = DashboardRegistry::Instance();

        $dashboardRegistry->expects($this->never())
            ->method('EntityClassFor');

        $this->assertSame(
            $entityClass,
            ah::CallMethod($sut, 'resolveEntityClass', [$tableName])
        );
    }

    function testResolveEntityClassReturnsClassFromDashboardRegistry()
    {
        $sut = new _EntityClassResolver();
        $tableName = 'table-name';
        $entity = $this->createStub(Entity::class);
        $dashboardRegistry = DashboardRegistry::Instance();

        $dashboardRegistry->expects($this->once())
            ->method('EntityClassFor')
            ->with($tableName)
            ->willReturn($entity::class);

        $this->assertSame(
            $entity::class,
            ah::CallMethod($sut, 'resolveEntityClass', [$tableName])
        );
    }

    function testResolveEntityClassThrowsIfClassCannotBeResolved()
    {
        $sut = new _EntityClassResolver();
        $tableName = 'table-name';
        $dashboardRegistry = DashboardRegistry::Instance();

        $dashboardRegistry->expects($this->once())
            ->method('EntityClassFor')
            ->with($tableName)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Unable to resolve entity class for table: $tableName");
        ah::CallMethod($sut, 'resolveEntityClass', [$tableName]);
    }

    #endregion resolveEntityClass

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
