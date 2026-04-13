<?php declare(strict_types=1);
namespace suite\Model\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Model\Traits\EntityFinder;

use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;
use \Peneus\Model\PersistentLogin;
use \TestToolkit\AccessHelper as ah;

class _EntityFinder {
    use EntityFinder;
}

#[CoversClass(_EntityFinder::class)]
class EntityFinderTest extends TestCase
{
    private ?Database $originalDatabase = null;

    protected function setUp(): void
    {
        $this->originalDatabase =
            Database::ReplaceInstance(new FakeDatabase());
    }

    protected function tearDown(): void
    {
        Database::ReplaceInstance($this->originalDatabase);
    }

    #region tryFindEntity ------------------------------------------------------

    #[DataProvider('entityClassProvider')]
    function testTryFindEntityReturnsNullIfNotFound(string $entityClass)
    {
        $sut = new _EntityFinder();
        $id = 500;
        $tableName = $entityClass::TableName();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: "SELECT * FROM `{$tableName}`"
               . ' WHERE `id` = :id'
               . ' LIMIT 1',
            bindings: ['id' => $id],
            result: null,
            times: 1
        );

        $actual = ah::CallMethod($sut, 'tryFindEntity', [$entityClass, $id]);

        $this->assertNull($actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #[DataProvider('entityClassProvider')]
    function testTryFindEntityReturnsEntityIfFound(string $entityClass)
    {
        $sut = new _EntityFinder();
        $id = 500;
        $tableName = $entityClass::TableName();
        $data = [];
        foreach ($entityClass::Metadata() as $column) {
            $name = $column['name'];
            $data[$name] = match ($column['type']) {
                'INT'      => ($name === 'id') ? $id : 0,
                'BIT'      => 0,
                'DOUBLE'   => 0.0,
                'TEXT'     => '',
                'DATETIME' => '2026-01-01 12:00:00',
                default    => null // should not happen
            };
        }
        $fakeDatabase = Database::Instance();
        $expected = new $entityClass($data);

        $fakeDatabase->Expect(
            sql: "SELECT * FROM `{$tableName}`"
               . ' WHERE `id` = :id'
               . ' LIMIT 1',
            bindings: ['id' => $id],
            result: [$data],
            times: 1
        );

        $actual = ah::CallMethod($sut, 'tryFindEntity', [$entityClass, $id]);

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion tryFindEntity

    #region Data Providers -----------------------------------------------------

    /**
     * @return array<string, array{class-string}>
     */
    static function entityClassProvider()
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
