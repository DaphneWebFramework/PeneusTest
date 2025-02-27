<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Model\Entity;

use \Harmonia\Database\Database;
use \Harmonia\Database\Queries\DeleteQuery;
use \Harmonia\Database\Queries\InsertQuery;
use \Harmonia\Database\Queries\SelectQuery;
use \Harmonia\Database\Queries\UpdateQuery;
use \Harmonia\Database\ResultSet;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

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

class TestEntityWithDateTime extends Entity {
    public \DateTime $createdAt; // e.g. DATETIME in MySQL
    public \DateTime $registeredOn; // e.g. DATE in MySQL
}

class TestEntityWithUninitializedProperties extends Entity {
    public bool $aBool;
    public int $anInt;
    public float $aFloat;
    public string $aString;
    public array $anArray;
    public \stdClass $aStdClass;
}

class TestEntityWithReadOnlyProperty extends Entity {
    public readonly string $property;
    public function __construct(array $data) {
        parent::__construct($data);
        $this->property = "I'm readonly";
    }
}

class TestEntityWithUntypedProperty extends Entity {
    public $property;
}

class TestEntityWithPropertyOfNonExistentClass extends Entity {
    public NonExistentClass $property;
}

class TestEntityWithNullablePropertyOfNonExistentClass extends Entity {
    public ?NonExistentClass $property;
}

         class NonInstantiableClass1 { public function __construct(int $x) {} }
         class NonInstantiableClass2 { private function __construct() {} }
abstract class NonInstantiableClass3 {}
     interface NonInstantiableClass4 {}
         trait NonInstantiableClass5 {}
          enum NonInstantiableClass6 {}

class TestEntityWithPropertiesOfNonInstantiableClasses extends Entity {
    public NonInstantiableClass1 $property1;
    public NonInstantiableClass2 $property2;
    public NonInstantiableClass3 $property3;
    public NonInstantiableClass4 $property4;
    public NonInstantiableClass5 $property5;
    public NonInstantiableClass6 $property6;
}

class TestEntityWithNoProperties extends Entity {
}

class TestEntityWithNonBindableProperties extends Entity {
    public array $anArray;
    public mixed $aResource; // Will be assigned a resource during tests
    public \stdClass $anObjectWithoutToString;
    public string $aString; // Only this property will be bound
}

class TestEntityWithCustomTableName extends Entity {
    public string $name = '';
    public int $age = 0;
    protected static function tableName(): string {
        return 'custom_table_name';
    }
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

    function testConstructWithUnknownProperty()
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

    function testConstructWithStaticProperties()
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

    function testConstructWithNonPublicProperties()
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

    function testConstructWithIntegerKeys()
    {
        $entity = new TestEntity([0 => 'John', 1 => 30]);
        $this->assertSame(0, $entity->id);
        $this->assertSame('', $entity->name); // Name should remain default
        $this->assertSame(0, $entity->age); // Age should remain default
    }

    function testConstructWithIncorrectTypes()
    {
        $entity = new TestEntity(['name' => 30, 'age' => 'John']);
        $this->assertSame(0, $entity->id);
        $this->assertSame('', $entity->name); // Name should remain default
        $this->assertSame(0, $entity->age); // Age should remain default
    }

    function testConstructWithDateTimeFields()
    {
        $entity = new TestEntityWithDateTime([
            'createdAt' => '2025-03-15 12:45:00',
            'registeredOn' => '2025-03-15'
        ]);
        $this->assertInstanceOf(\DateTime::class, $entity->createdAt);
        $this->assertInstanceOf(\DateTime::class, $entity->registeredOn);
        $this->assertSame('2025-03-15 12:45:00', $entity->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('2025-03-15', $entity->registeredOn->format('Y-m-d'));
    }

    function testConstructWithMalformedDateTimeField()
    {
        $entity = new TestEntityWithDateTime([
            'createdAt' => '02.12.20239'
        ]);
        $this->assertInstanceOf(\DateTime::class, $entity->createdAt);
    }

    #[DataProviderExternal(DataHelper::class, 'NonStringProvider')]
    function testConstructWithhNonStringDateTimeField($value)
    {
        $entity = new TestEntityWithDateTime([
            'createdAt' => $value
        ]);
        $this->assertInstanceOf(\DateTime::class, $entity->createdAt);
    }

    function testConstructWithUninitializedProperties()
    {
        $entity = new TestEntityWithUninitializedProperties([
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => 'Hello, World!',
            'anArray' => ['key' => 'value'],
            'aStdClass' => new \stdClass()
        ]);
        $this->assertIsBool($entity->aBool);
        $this->assertSame(true, $entity->aBool);
        $this->assertIsInt($entity->anInt);
        $this->assertSame(42, $entity->anInt);
        $this->assertIsFloat($entity->aFloat);
        $this->assertSame(3.14, $entity->aFloat);
        $this->assertIsString($entity->aString);
        $this->assertSame('Hello, World!', $entity->aString);
        $this->assertIsArray($entity->anArray);
        $this->assertSame(['key' => 'value'], $entity->anArray);
        $this->assertInstanceOf(\stdClass::class, $entity->aStdClass);
    }

    function testConstructWithReadOnlyProperty()
    {
        $entity = new TestEntityWithReadOnlyProperty([
            'property' => "I'm overwriting"
        ]);
        $this->assertSame("I'm readonly", $entity->property);
    }

    function testConstructWithUntypedProperty()
    {
        $entity = new TestEntityWithUntypedProperty([
            'property' => 'value'
        ]);
        $this->assertSame('value', $entity->property);
    }

    function testConstructWithPropertyOfNonExistentClass()
    {
        $entity = new TestEntityWithPropertyOfNonExistentClass([
            'property' => null
        ]);
        $this->assertTrue(true); // No error should be thrown
    }

    function testConstructWithNullablePropertyOfNonExistentClass()
    {
        $entity = new TestEntityWithNullablePropertyOfNonExistentClass([
            'property' => null
        ]);
        $this->assertTrue(true); // No error should be thrown
    }

    function testConstructWithPropertiesOfNonInstantiableClasses()
    {
        $entity = new TestEntityWithPropertiesOfNonInstantiableClasses([
            'property1' => null,
            'property2' => null,
            'property3' => null,
            'property4' => null,
            'property5' => null,
            'property6' => null
        ]);
        $this->assertTrue(true); // No error should be thrown
    }

    #endregion __construct

    #region Save ---------------------------------------------------------------

    function testSaveFailsToInsertOnEntityWithNoProperties()
    {
        $database = Database::Instance();
        $database->expects($this->never())
            ->method('Execute');

        $entity = new TestEntityWithNoProperties();

        $this->assertFalse($entity->Save());
    }

    function testSaveFailsToUpdateOnEntityWithNoProperties()
    {
        $database = Database::Instance();
        $database->expects($this->never())
            ->method('Execute');

        $entity = new TestEntityWithNoProperties(['id' => 1]);

        $this->assertFalse($entity->Save());
    }

    function testSaveFailsToInsertIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);

        $entity = new TestEntity(['name' => 'John', 'age' => 30]);
        $this->assertFalse($entity->Save());
    }

    function testSaveFailsToUpdateIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);

        $entity = new TestEntity(['id' => 1, 'name' => 'John', 'age' => 30]);
        $this->assertFalse($entity->Save());
    }

    function testSaveFailsToUpdateIfNoRowsAffected()
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

    function testSaveInsertsWithDateTimeFields()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(InsertQuery::class, $query);
                $this->assertSame('createdAt, registeredOn',
                    AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertSame(':createdAt, :registeredOn',
                    AccessHelper::GetNonPublicProperty($query, 'values'));
                $bindings = $query->Bindings();
                $this->assertArrayHasKey('createdAt', $bindings);
                $this->assertSame('2025-03-15 12:45:00', $bindings['createdAt']);
                // Since 'registeredOn' is a DATE field, the time part should be
                // automatically set to the current time. Compare the time part
                // with the current time with a 10-second delta.
                $this->assertArrayHasKey('registeredOn', $bindings);
                $registeredOnDate = \substr($bindings['registeredOn'], 0, 10);
                $registeredOnTime = \substr($bindings['registeredOn'], 11);
                $this->assertSame('2025-03-15', $registeredOnDate);
                $this->assertEqualsWithDelta(\time(), \strtotime($registeredOnTime), 10);
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));

        $entity = new TestEntityWithDateTime([
            'createdAt' => '2025-03-15 12:45:00',
            'registeredOn' => '2025-03-15'
        ]);
        $entity->Save();
    }

    function testSaveUpdatesWithDateTimeFields()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(UpdateQuery::class, $query);
                $this->assertSame(['createdAt', 'registeredOn'],
                    AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertSame([':createdAt', ':registeredOn'],
                    AccessHelper::GetNonPublicProperty($query, 'values'));
                $bindings = $query->Bindings();
                $this->assertArrayHasKey('id', $bindings);
                $this->assertSame(1, $bindings['id']);
                $this->assertArrayHasKey('createdAt', $bindings);
                $this->assertSame('2025-03-15 12:45:00', $bindings['createdAt']);
                // Since 'registeredOn' is a DATE field, the time part should be
                // automatically set to the current time. Compare the time part
                // with the current time with a 10-second delta.
                $this->assertArrayHasKey('registeredOn', $bindings);
                $registeredOnDate = \substr($bindings['registeredOn'], 0, 10);
                $registeredOnTime = \substr($bindings['registeredOn'], 11);
                $this->assertSame('2025-03-15', $registeredOnDate);
                $this->assertEqualsWithDelta(\time(), \strtotime($registeredOnTime), 10);
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));

        $entity = new TestEntityWithDateTime([
            'id' => 1,
            'createdAt' => '2025-03-15 12:45:00',
            'registeredOn' => '2025-03-15'
        ]);
        $entity->Save();
    }

    function testSaveInsertSkipsNonBindableProperties()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(InsertQuery::class, $query);
                $this->assertSame('testentitywithnonbindableproperties',
                    AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame('aString',
                    AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertSame(':aString',
                    AccessHelper::GetNonPublicProperty($query, 'values'));
                $this->assertSame(['aString' => 'Hello, World!'],
                    $query->Bindings());
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastInsertId')
            ->willReturn(1);

        $entity = new TestEntityWithNonBindableProperties([
            'anArray' => ['key' => 'value'],
            'aResource' => \fopen('php://memory', 'r'),
            'anObjectWithoutToString' => new \stdClass(),
            'aString' => 'Hello, World!'
        ]);

        $this->assertTrue($entity->Save());

        \fclose($entity->aResource);
    }

    function testSaveUpdateSkipsNonBindableProperties()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(UpdateQuery::class, $query);
                $this->assertSame('testentitywithnonbindableproperties',
                    AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame(['aString'],
                    AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertSame([':aString'],
                    AccessHelper::GetNonPublicProperty($query, 'values'));
                $this->assertSame('id = :id',
                    AccessHelper::GetNonPublicProperty($query, 'condition'));
                $this->assertSame(['id' => 1, 'aString' => 'Hello, World!'],
                    $query->Bindings());
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));

        $entity = new TestEntityWithNonBindableProperties([
            'id' => 1,
            'anArray' => ['key' => 'value'],
            'aResource' => \fopen('php://memory', 'r'),
            'anObjectWithoutToString' => new \stdClass(),
            'aString' => 'Hello, World!'
        ]);

        $this->assertTrue($entity->Save());

        \fclose($entity->aResource);
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

    #region FindById -----------------------------------------------------------

    function testFindByIdReturnsNullIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);

        $entity = TestEntity::FindById(1);

        $this->assertNull($entity);
    }

    function testFindByIdReturnsNullIfNotFound()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->method('Row')->willReturn(null);

        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn($resultSet);

        $entity = TestEntity::FindById(99);

        $this->assertNull($entity);
    }

    function testFindByIdReturnsEntityIfExists()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->method('Row')->willReturn([
            'id' => 1,
            'name' => 'John Doe',
            'age' => 30
        ]);

        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame('testentity',
                    AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame('*',
                    AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertSame('id = :id',
                    AccessHelper::GetNonPublicProperty($query, 'condition'));
                $this->assertSame(['id' => 1], $query->Bindings());
                return true;
            }))
            ->willReturn($resultSet);

        $entity = TestEntity::FindById(1);

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertSame(1, $entity->id);
        $this->assertSame('John Doe', $entity->name);
        $this->assertSame(30, $entity->age);
    }

    #endregion FindById

    #region FindFirst ----------------------------------------------------------

    function testFindFirstReturnsNullIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);

        $entity = TestEntity::FindFirst(
            'status = :status',
            ['status' => 'active']
        );

        $this->assertNull($entity);
    }

    function testFindFirstReturnsNullIfNotFound()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->method('Row')->willReturn(null);

        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn($resultSet);

        $entity = TestEntity::FindFirst(
            'status = :status',
            ['status' => 'inactive']
        );

        $this->assertNull($entity);
    }

    function testFindFirstReturnsEntityWhenNoParametersProvided()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->method('Row')->willReturn([
            'id' => 1,
            'name' => 'First User',
            'age' => 25
        ]);

        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame('testentity', AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame('*', AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertNull(AccessHelper::GetNonPublicProperty($query, 'condition'));
                $this->assertNull(AccessHelper::GetNonPublicProperty($query, 'orderBy'));
                $this->assertSame('1', AccessHelper::GetNonPublicProperty($query, 'limit'));
                $this->assertSame([], $query->Bindings());
                return true;
            }))
            ->willReturn($resultSet);

        $entity = TestEntity::FindFirst();

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertSame(1, $entity->id);
        $this->assertSame('First User', $entity->name);
        $this->assertSame(25, $entity->age);
    }

    function testFindFirstReturnsEntityIfExists()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->method('Row')->willReturn([
            'id' => 1,
            'name' => 'John Doe',
            'age' => 30
        ]);

        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame('testentity', AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame('*', AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertSame('status = :status', AccessHelper::GetNonPublicProperty($query, 'condition'));
                $this->assertNull(AccessHelper::GetNonPublicProperty($query, 'orderBy'));
                $this->assertSame('1', AccessHelper::GetNonPublicProperty($query, 'limit'));
                $this->assertSame(['status' => 'active'], $query->Bindings());
                return true;
            }))
            ->willReturn($resultSet);

        $entity = TestEntity::FindFirst(
            'status = :status',
            ['status' => 'active']
        );

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertSame(1, $entity->id);
        $this->assertSame('John Doe', $entity->name);
        $this->assertSame(30, $entity->age);
    }

    function testFindFirstReturnsEntityIfExistsWithOrderBy()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->method('Row')->willReturn([
            'id' => 2,
            'name' => 'Jane Doe',
            'age' => 25
        ]);

        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame('testentity', AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame('*', AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertSame('status = :status', AccessHelper::GetNonPublicProperty($query, 'condition'));
                $this->assertSame('created_at DESC', AccessHelper::GetNonPublicProperty($query, 'orderBy'));
                $this->assertSame('1', AccessHelper::GetNonPublicProperty($query, 'limit'));
                $this->assertSame(['status' => 'active'], $query->Bindings());
                return true;
            }))
            ->willReturn($resultSet);

        $entity = TestEntity::FindFirst(
            'status = :status',
            ['status' => 'active'],
            'created_at DESC'
        );

        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertSame(2, $entity->id);
        $this->assertSame('Jane Doe', $entity->name);
        $this->assertSame(25, $entity->age);
    }

    #endregion FindFirst

    #region Find ---------------------------------------------------------------

    function testFindReturnsEmptyArrayIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);

        $entities = TestEntity::Find();

        $this->assertIsArray($entities);
        $this->assertEmpty($entities);
    }

    function testFindReturnsEmptyArrayIfNotFound()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->method('Row')->willReturn(null);

        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn($resultSet);

        $entities = TestEntity::Find();

        $this->assertIsArray($entities);
        $this->assertEmpty($entities);
    }

    function testFindReturnsArrayOfEntitiesWhenNoParametersProvided()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($invokedCount = $this->exactly(3))
            ->method('Row')
            ->willReturnCallback(function() use($invokedCount) {
                switch ($invokedCount->numberOfInvocations()) {
                    case 1:
                        return ['id' => 3, 'name' => 'Alice Doe', 'age' => 27];
                    case 2:
                        return ['id' => 4, 'name' => 'Bob Smith', 'age' => 35];
                    case 3:
                        return null; // Stop iteration
                }
            });

        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame('testentity', AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame('*', AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertNull(AccessHelper::GetNonPublicProperty($query, 'condition'));
                $this->assertNull(AccessHelper::GetNonPublicProperty($query, 'orderBy'));
                $this->assertNull(AccessHelper::GetNonPublicProperty($query, 'limit'));
                $this->assertSame([], $query->Bindings());
                return true;
            }))
            ->willReturn($resultSet);

        $entities = TestEntity::Find();

        $this->assertIsArray($entities);
        $this->assertCount(2, $entities);
        $this->assertInstanceOf(TestEntity::class, $entities[0]);
            $this->assertSame(         3 , $entities[0]->id);
            $this->assertSame('Alice Doe', $entities[0]->name);
            $this->assertSame(        27 , $entities[0]->age);
        $this->assertInstanceOf(TestEntity::class, $entities[1]);
            $this->assertSame(         4 , $entities[1]->id);
            $this->assertSame('Bob Smith', $entities[1]->name);
            $this->assertSame(        35 , $entities[1]->age);
    }

    function testFindReturnsArrayOfEntitiesWhenAllParametersProvided()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($invokedCount = $this->exactly(3))
            ->method('Row')
            ->willReturnCallback(function() use($invokedCount) {
                switch ($invokedCount->numberOfInvocations()) {
                    case 1:
                        return ['id' => 3, 'name' => 'Alice Doe', 'age' => 27];
                    case 2:
                        return ['id' => 4, 'name' => 'Bob Smith', 'age' => 35];
                    case 3:
                        return null; // Stop iteration
                }
            });

        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame('testentity', AccessHelper::GetNonPublicProperty($query, 'table'));
                $this->assertSame('*', AccessHelper::GetNonPublicProperty($query, 'columns'));
                $this->assertSame('status = :status AND age >= :minAge', AccessHelper::GetNonPublicProperty($query, 'condition'));
                $this->assertSame('createdAt DESC', AccessHelper::GetNonPublicProperty($query, 'orderBy'));
                $this->assertSame('10 OFFSET 5', AccessHelper::GetNonPublicProperty($query, 'limit'));
                $this->assertSame(['status' => 'active', 'minAge' => 25], $query->Bindings());
                return true;
            }))
            ->willReturn($resultSet);

        $entities = TestEntity::Find(
            'status = :status AND age >= :minAge',
            ['status' => 'active', 'minAge' => 25],
            'createdAt DESC',
            10,
            5
        );

        $this->assertIsArray($entities);
        $this->assertCount(2, $entities);
        $this->assertInstanceOf(TestEntity::class, $entities[0]);
            $this->assertSame(         3 , $entities[0]->id);
            $this->assertSame('Alice Doe', $entities[0]->name);
            $this->assertSame(        27 , $entities[0]->age);
        $this->assertInstanceOf(TestEntity::class, $entities[1]);
            $this->assertSame(         4 , $entities[1]->id);
            $this->assertSame('Bob Smith', $entities[1]->name);
            $this->assertSame(        35 , $entities[1]->age);
    }

    #endregion Find

    #region tableName ----------------------------------------------------------

    function testTableNameCanBeOverridden()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(InsertQuery::class, $query);
                $this->assertSame('custom_table_name', AccessHelper::GetNonPublicProperty($query, 'table'));
                return true;
            }));

        $entity = new TestEntityWithCustomTableName(['name' => 'John', 'age' => 30]);
        $entity->Save();
    }

    #endregion tableName
}
