<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Model\Entity;

use \Harmonia\Database\Database;
use \Harmonia\Database\Queries\DeleteQuery;
use \Harmonia\Database\Queries\InsertQuery;
use \Harmonia\Database\Queries\UpdateQuery;
use \Harmonia\Database\ResultSet;
use \TestToolkit\AccessHelper;

class TestEntity extends Entity {
    // Properties to be ignored:
    static private int $aStaticPrivateProperty = 0;
    static protected int $aStaticProtectedProperty = 0;
    static public int $aStaticPublicProperty = 0;
    private int $aPrivateProperty = 0;
    protected int $aProtectedProperty = 0;

    // Properties to be mapped:
    public string $name = '';
    public int $age = 0;
}

#[CoversClass(Entity::class)]
class EntityTest extends TestCase
{
    private ?Database $originalDatabase = null;

    protected function setUp(): void
    {
        $this->originalDatabase = Database::ReplaceInstance(
            $this->createMock(Database::class));
    }

    protected function tearDown(): void
    {
        Database::ReplaceInstance($this->originalDatabase);
    }

    #region __construct --------------------------------------------------------

    function testEntityIsAbstract()
    {
        $this->expectException(\Error::class);

        $entity = new Entity();
    }

    function testConstructWithoutData()
    {
        $entity = new TestEntity();

        $this->assertSame(0, $entity->id);
        $this->assertSame('', $entity->name);
        $this->assertSame(0, $entity->age);
    }

    function testConstructWithDataIncludingUnknownProperty()
    {
        $entity = new TestEntity([
            'name' => 'John',
            'age' => 30,
            'unknown' => 'value'
        ]);

        $this->assertSame(0, $entity->id);
        $this->assertSame('John', $entity->name);
        $this->assertSame(30, $entity->age);
        $this->assertFalse(\property_exists($entity, 'unknown'));
    }

    function testConstructWithDataIncludingStaticProperties()
    {
        $entity = new TestEntity([
            'name' => 'John',
            'age' => 30,
            'aStaticPrivateProperty' => 99,
            'aStaticProtectedProperty' => 99,
            'aStaticPublicProperty' => 99
        ]);

        $this->assertSame(0, $entity->id);
        $this->assertSame('John', $entity->name);
        $this->assertSame(30, $entity->age);

        // Static properties should remain unchanged.
        $this->assertSame(0, AccessHelper::GetNonPublicStaticProperty(
            TestEntity::class, 'aStaticPrivateProperty'));
        $this->assertSame(0, AccessHelper::GetNonPublicStaticProperty(
            TestEntity::class, 'aStaticProtectedProperty'));
        $this->assertSame(0, AccessHelper::GetNonPublicStaticProperty(
            TestEntity::class, 'aStaticPublicProperty'));
    }

    function testConstructWithDataIncludingNonPublicProperties()
    {
        $entity = new TestEntity([
            'name' => 'John',
            'age' => 30,
            'aPrivateProperty' => 99,
            'aProtectedProperty' => 99
        ]);

        $this->assertSame(0, $entity->id);
        $this->assertSame('John', $entity->name);
        $this->assertSame(30, $entity->age);

        // Non-public properties should remain unchanged.
        $this->assertSame(0, AccessHelper::GetNonPublicProperty(
            $entity, 'aPrivateProperty'));
        $this->assertSame(0, AccessHelper::GetNonPublicProperty(
            $entity, 'aProtectedProperty'));
    }

    function testConstructWithDataHavingIntegerKeys()
    {
        $entity = new TestEntity([0 => 'John', 1 => 30]);

        $this->assertSame(0, $entity->id);
        $this->assertSame('', $entity->name); // Name should remain default
        $this->assertSame(0, $entity->age); // Age should remain default
    }

    function testConstructWithDataExcludingId()
    {
        $entity = new TestEntity(['name' => 'John', 'age' => 30]);

        $this->assertSame(0, $entity->id);
        $this->assertSame('John', $entity->name);
        $this->assertSame(30, $entity->age);
    }

    function testConstructWithDataIncludingId()
    {
        $entity = new TestEntity(['id' => 1, 'name' => 'John', 'age' => 30]);

        $this->assertSame(1, $entity->id);
        $this->assertSame('John', $entity->name);
        $this->assertSame(30, $entity->age);
    }

    #endregion __construct

    #region Save ---------------------------------------------------------------

    function testSaveInsertsIfIdIsZero()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(InsertQuery::class, $query);
                $this->assertSame('testentity',
                    AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame('name, age',
                    AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertSame(':name, :age',
                    AccessHelper::GetNonPublicProperty($query, 'values'));
                $this->assertSame(['name' => 'John', 'age' => 30],
                    $query->Bindings());
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastInsertId')
            ->willReturn(1);

        $entity = new TestEntity(['name' => 'John', 'age' => 30]);
        $this->assertTrue($entity->Save());
        $this->assertSame(1, $entity->id);
    }

    function testSaveUpdatesIfIdIsNotZero()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(UpdateQuery::class, $query);
                $this->assertSame('testentity',
                    AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame(['name', 'age'],
                    AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertSame([':name', ':age'],
                    AccessHelper::GetNonPublicProperty($query, 'values'));
                $this->assertSame('id = :id',
                    AccessHelper::GetNonPublicProperty($query, 'condition'));
                $this->assertSame(['id' => 1, 'name' => 'John', 'age' => 30],
                    $query->Bindings());
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));

        $entity = new TestEntity(['id' => 1, 'name' => 'John', 'age' => 30]);
        $this->assertTrue($entity->Save());
        $this->assertSame(1, $entity->id); // ID should remain unchanged
    }

    function testSaveFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);

        $entity = new TestEntity(['name' => 'John', 'age' => 30]);
        $this->assertFalse($entity->Save());
    }

    function testSaveFailsIfNoRowUpdated()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->isInstanceOf(UpdateQuery::class))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastAffectedRowCount')
            ->willReturn(-1); // Simulates MySQL failure

        $entity = new TestEntity(['id' => 1, 'name' => 'John', 'age' => 30]);
        $this->assertFalse($entity->Save());
    }

    #endregion Save

    #region Delete -------------------------------------------------------------

    function testDeleteFailsIfIdIsZero()
    {
        $database = Database::Instance();
        $database->expects($this->never())
            ->method('Execute');

        $entity = new TestEntity(['name' => 'John', 'age' => 30]);
        $this->assertFalse($entity->Delete());
    }

    function testDeleteFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);

        $entity = new TestEntity(['id' => 1, 'name' => 'John', 'age' => 30]);
        $this->assertFalse($entity->Delete());
    }

    function testDeleteFailsIfNoRowDeleted()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->isInstanceOf(DeleteQuery::class))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastAffectedRowCount')
            ->willReturn(0); // Simulates no row was deleted

        $entity = new TestEntity(['id' => 1, 'name' => 'John', 'age' => 30]);
        $this->assertFalse($entity->Delete());
        $this->assertSame(1, $entity->id); // ID should remain unchanged
    }

    function testDeleteSucceedsIfRowExists()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(DeleteQuery::class, $query);
                $this->assertSame('testentity',
                    AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame('id = :id',
                    AccessHelper::GetNonPublicProperty($query, 'condition'));
                $this->assertSame(['id' => 1],
                    $query->Bindings());
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastAffectedRowCount')
            ->willReturn(1); // Simulates row deletion

        $entity = new TestEntity(['id' => 1, 'name' => 'John', 'age' => 30]);
        $this->assertTrue($entity->Delete());
        $this->assertSame(0, $entity->id); // ID should be reset to zero
    }

    #endregion Delete
}
