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

#region Classes Under Test -----------------------------------------------------

class EntityWithNonPublicProperty extends Entity {
    private int $aPrivate = 1;
    protected int $aProtected = 2;
}

class EntityWithStaticProperty extends Entity {
    static private int $aStaticPrivate = 1;
    static protected int $aStaticProtected = 2;
    static public int $aStaticPublic = 3;
}

class EntityWithReadOnlyProperty extends Entity {
    public readonly int $property;
    public function __construct(?array $data = null) {
        parent::__construct($data);
        $this->property = 1;
    }
}

class EntityWithUninitializedProperty extends Entity {
    public bool $aBool;
    public int $anInt;
    public float $aFloat;
    public string $aString;
    public array $anArray;
    public mixed $aMixed;
    public object $anObject;
    public iterable $anIterable;
}

class EntityWithNullableUninitializedProperty extends Entity {
    public ?bool $aBool;
    public ?int $anInt;
    public ?float $aFloat;
    public ?string $aString;
    public ?array $anArray;
    public ?object $anObject;
    public ?iterable $anIterable;
}

class EntityWithInitializedProperty extends Entity {
    public bool $aBool = true;
    public int $anInt = 42;
    public float $aFloat = 3.14;
    public string $aString = "I'm a string";
    public array $anArray = [1, 2, 3];
    public mixed $aMixed = "I'm a string too";
}

class EntityWithUntypedProperty extends Entity {
    public $property;
}

class EntityWithUnionProperty extends Entity {
    public int|string $initProperty = 1;
    public int|string $uninitProperty;
}

class EntityWithIntersectionProperty extends Entity {
    public \Iterator&\Countable $property;
}

class EntityWithPromotedProperty extends Entity {
    public function __construct(public int $property, ?array $data = null) {
        parent::__construct($data);
    }
}

class EntityWithNonExistentClassProperty extends Entity {
    public ?NonExistentClass $nullableProperty;
    public NonExistentClass $property;
}

class NonInstantiableClass1 { public function __construct(int $x) {} }
class NonInstantiableClass2 { private function __construct() {} }
abstract class NonInstantiableClass3 {}
interface NonInstantiableClass4 {}
trait NonInstantiableClass5 {}
enum NonInstantiableClass6 {}

class EntityWithNonInstantiableClassProperty extends Entity {
    public NonInstantiableClass1 $property1;
    public NonInstantiableClass2 $property2;
    public NonInstantiableClass3 $property3;
    public NonInstantiableClass4 $property4;
    public NonInstantiableClass5 $property5;
    public NonInstantiableClass6 $property6;
}

class EntityWithCustomTableName extends Entity {
    protected static function tableName(): string {
        return 'custom_table_name';
    }
}

class EntityWithNoProperties extends Entity {
}

class EntityWithNonBindableProperties extends Entity {
    public array $anArray;
    public mixed $aResource;
    public \stdClass $anObjectWithoutToString;
    public string $aString; // Only this property will be bound
}

class TestEntity extends Entity {
    public string $name;
    public int $age;
    public \DateTime $createdAt;
}

#endregion Classes Under Test

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

    function testBaseClassCannotBeInstantiated()
    {
        $this->expectException(\Error::class);
        $entity = new Entity();
    }

    function testConstructWithNonPublicProperty()
    {
        $entity = new EntityWithNonPublicProperty([
            'aPrivate' => 99,
            'aProtected' => 99
        ]);
        $this->assertSame(1, AccessHelper::GetProperty($entity, 'aPrivate'));
        $this->assertSame(2, AccessHelper::GetProperty($entity, 'aProtected'));
    }

    function testConstructWithStaticProperty()
    {
        $entity = new EntityWithStaticProperty([
            'aStaticPrivate' => 99,
            'aStaticProtected' => 99,
            'aStaticPublic' => 99
        ]);
        $this->assertSame(1, AccessHelper::GetStaticProperty(
                             EntityWithStaticProperty::class, 'aStaticPrivate'));
        $this->assertSame(2, AccessHelper::GetStaticProperty(
                             EntityWithStaticProperty::class, 'aStaticProtected'));
        $this->assertSame(3, AccessHelper::GetStaticProperty(
                             EntityWithStaticProperty::class, 'aStaticPublic'));
    }

    function testConstructWithReadOnlyProperty()
    {
        $entity = new EntityWithReadOnlyProperty([
            'property' => 99
        ]);
        $this->assertSame(1, $entity->property);
    }

    function testConstructWithUninitializedProperty()
    {
        $entity = new EntityWithUninitializedProperty([
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => "I'm a string",
            'anArray' => [1, 2, 3],
            'aMixed' => "I'm a string too",
            'anObject' => new \stdClass(),
            'anIterable' => new \ArrayIterator([1, 2, 3])
        ]);
        $this->assertTrue($entity->aBool);
        $this->assertSame(42, $entity->anInt);
        $this->assertSame(3.14, $entity->aFloat);
        $this->assertSame("I'm a string", $entity->aString);
        $this->assertSame([1, 2, 3], $entity->anArray);
        $this->assertSame("I'm a string too", $entity->aMixed);
        // Error: Typed property must not be accessed before initialization.
        $this->expectException(\Error::class);
        $entity->anObject;
        $entity->anIterable;
    }

    function testConstructWithNullableUninitializedProperty()
    {
        $entity = new EntityWithNullableUninitializedProperty([
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => "I'm a string",
            'anArray' => [1, 2, 3],
            'anObject' => new \stdClass(),
            'anIterable' => new \ArrayIterator([1, 2, 3])
        ]);
        $this->assertTrue($entity->aBool);
        $this->assertSame(42, $entity->anInt);
        $this->assertSame(3.14, $entity->aFloat);
        $this->assertSame("I'm a string", $entity->aString);
        $this->assertSame([1, 2, 3], $entity->anArray);
        $this->assertInstanceOf(\stdClass::class, $entity->anObject);
        $this->assertInstanceOf(\ArrayIterator::class, $entity->anIterable);
    }

    function testConstructWithInitializedProperty()
    {
        $entity = new EntityWithInitializedProperty([
            'aBool' => false,
            'anInt' => 99,
            'aFloat' => 6.28,
            'aString' => "I'm another string",
            'anArray' => [4, 5, 6],
            'aMixed' => "I'm another string too"
        ]);
        $this->assertFalse($entity->aBool);
        $this->assertSame(99, $entity->anInt);
        $this->assertSame(6.28, $entity->aFloat);
        $this->assertSame("I'm another string", $entity->aString);
        $this->assertSame([4, 5, 6], $entity->anArray);
        $this->assertSame("I'm another string too", $entity->aMixed);
    }

    function testConstructWithUntypedProperty()
    {
        $entity = new EntityWithUntypedProperty([
            'property' => 'any value'
        ]);
        $this->assertSame('any value', $entity->property);
    }

    function testConstructWithUnionProperty()
    {
        $entity = new EntityWithUnionProperty([
            'initProperty' => 99,
            'uninitProperty' => 99
        ]);
        $this->assertSame(99, $entity->initProperty);
        // Error: Typed property must not be accessed before initialization.
        $this->expectException(\Error::class);
        $entity->uninitProperty;
    }

    function testConstructWithIntersectionProperty()
    {
        $entity = new EntityWithIntersectionProperty([
            'property' => new \ArrayIterator([1, 2, 3])
        ]);
        // Error: Typed property must not be accessed before initialization.
        $this->expectException(\Error::class);
        $entity->property;
    }

    function testConstructWithPromotedProperty()
    {
        $entity = new EntityWithPromotedProperty(99, [
            'property' => 99
        ]);
        $this->assertSame(99, $entity->property);
    }

    function testConstructWithNonExistentClassProperty()
    {
        $entity = new EntityWithNonExistentClassProperty([
            'nullableProperty' => null,
          //'property' => new NonExistentClass()
        ]);
        $this->assertNull($entity->nullableProperty);
        // Error: Typed property must not be accessed before initialization.
        $this->expectException(\Error::class);
        $entity->property;
    }


    function testConstructWithNonInstantiableClassProperty()
    {
        $entity = new EntityWithNonInstantiableClassProperty([
          //'property1' => new NonInstantiableClass1(1),
          //'property2' => new NonInstantiableClass2(),
          //'property3' => new NonInstantiableClass3(),
          //'property4' => new NonInstantiableClass4(),
          //'property5' => new NonInstantiableClass5(),
          //'property6' => new NonInstantiableClass6()
        ]);
        // Error: Typed property must not be accessed before initialization.
        $this->expectException(\Error::class);
        $entity->property1;
        $entity->property2;
        $entity->property3;
        $entity->property4;
        $entity->property5;
        $entity->property6;
    }

    function testConstructWithoutData()
    {
        $entity = new TestEntity();
        $this->assertSame(0, $entity->id);
        // Error: Typed property must not be accessed before initialization.
        $this->expectException(\Error::class);
        $entity->name;
        $entity->age;
        $entity->createdAt;
    }

    function testConstructWithEmptyData()
    {
        $entity = new TestEntity([]);
        $this->assertSame(0, $entity->id);
        $this->assertSame('', $entity->name);
        $this->assertSame(0, $entity->age);
        $this->assertInstanceOf(\DateTime::class, $entity->createdAt);
    }

    function testConstructWithDataExcludingId()
    {
        $entity = new TestEntity([
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01'
        ]);
        $this->assertSame(0, $entity->id);
        $this->assertSame('John', $entity->name);
        $this->assertSame(30, $entity->age);
        $this->assertInstanceOf(\DateTime::class, $entity->createdAt);
        $this->assertSame('2021-01-01', $entity->createdAt->format('Y-m-d'));
    }

    function testConstructWithDataIncludingId()
    {
        $entity = new TestEntity([
            'id' => 42,
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01'
        ]);
        $this->assertSame(42, $entity->id);
        $this->assertSame('John', $entity->name);
        $this->assertSame(30, $entity->age);
        $this->assertInstanceOf(\DateTime::class, $entity->createdAt);
        $this->assertSame('2021-01-01', $entity->createdAt->format('Y-m-d'));
    }

    function testConstructWithUnknownProperty()
    {
        $entity = new TestEntity([
            'unknown' => 'value'
        ]);
        $this->assertFalse(\property_exists($entity, 'unknown'));
    }

    function testConstructWithIntegerKeys()
    {
        $entity = new TestEntity([
            'John',
            30,
            '2021-01-01'
        ]);
        $this->assertSame(0, $entity->id);
        $this->assertSame('', $entity->name);
        $this->assertSame(0, $entity->age);
        $this->assertInstanceOf(\DateTime::class, $entity->createdAt);
    }

    function testConstructWithIncorrectTypes()
    {
        $entity = new TestEntity([
            'id' => '42',
            'name' => 30,
            'age' => '30',
            'createdAt' => 20210101
        ]);
        $this->assertSame(0, $entity->id);
        $this->assertSame('', $entity->name);
        $this->assertSame(0, $entity->age);
        $this->assertInstanceOf(\DateTime::class, $entity->createdAt);
    }

    #endregion __construct

    #region Save ---------------------------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testSaveCallsInsertWhenIdIsZero($returnValue)
    {
        $entity = $this->getMockBuilder(TestEntity::class)
            ->setConstructorArgs([[]]) // i.e., id = 0
            ->onlyMethods(['insert'])
            ->getMock();
        $entity->expects($this->once())
            ->method('insert')
            ->willReturn($returnValue);
        $this->assertSame($returnValue, $entity->Save());
    }

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testSaveCallsUpdateWhenIdIsNotZero($returnValue)
    {
        $entity = $this->getMockBuilder(TestEntity::class)
            ->setConstructorArgs([['id' => 1]])
            ->onlyMethods(['update'])
            ->getMock();
        $entity->expects($this->once())
            ->method('update')
            ->willReturn($returnValue);
        $this->assertSame($returnValue, $entity->Save());
    }

    #endregion Save

    #region Delete -------------------------------------------------------------

    function testDeleteFailsIfIdIsZero()
    {
        $database = Database::Instance();
        $database->expects($this->never())
            ->method('Execute');
        $entity = new TestEntity();
        $this->assertFalse($entity->Delete());
    }

    function testDeleteFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $entity = new TestEntity(['id' => 1]);
        $this->assertFalse($entity->Delete());
        $this->assertSame(1, $entity->id); // ID should remain unchanged
    }

    function testDeleteFailsIfLastAffectedRowCountIsNotOne()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->isInstanceOf(DeleteQuery::class))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastAffectedRowCount')
            ->willReturn(0);
        $entity = new TestEntity(['id' => 1]);
        $this->assertFalse($entity->Delete());
        $this->assertSame(1, $entity->id); // ID should remain unchanged
    }

    function testDeleteSucceedsIfLastAffectedRowCountIsOne()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(DeleteQuery::class, $query);
                $this->assertSame(
                    'testentity',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    'id = :id',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    ['id' => 1],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastAffectedRowCount')
            ->willReturn(1);
        $entity = new TestEntity(['id' => 1]);
        $this->assertTrue($entity->Delete());
        $this->assertSame(0, $entity->id); // ID should be reset to zero
    }

    #endregion Delete

    #region FindById -----------------------------------------------------------

    function testFindByIdFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $entity = TestEntity::FindById(1);
        $this->assertNull($entity);
    }

    function testFindByIdFailsIfResultSetIsEmpty()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn(null);
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame(
                    'testentity',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    '*',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    'id = :id',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertNull(
                    AccessHelper::GetProperty($query, 'orderBy')
                );
                $this->assertNull(
                    AccessHelper::GetProperty($query, 'limit')
                );
                $this->assertSame(
                    ['id' => 1],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $entity = TestEntity::FindById(1);
        $this->assertNull($entity);
    }

    function testFindByIdSucceedsIfResultSetIsNotEmpty()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn([
                'id' => 1,
                'name' => 'John',
                'age' => 30,
                'createdAt' => '2021-01-01'
            ]);
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame(
                    'testentity',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    '*',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    'id = :id',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertNull(
                    AccessHelper::GetProperty($query, 'orderBy')
                );
                $this->assertNull(
                    AccessHelper::GetProperty($query, 'limit')
                );
                $this->assertSame(
                    ['id' => 1],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $entity = TestEntity::FindById(1);
        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertSame(1, $entity->id);
        $this->assertSame('John', $entity->name);
        $this->assertSame(30, $entity->age);
        $this->assertSame('2021-01-01', $entity->createdAt->format('Y-m-d'));
    }

    #endregion FindById

    #region FindFirst ----------------------------------------------------------

    function testFindFirstFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $entity = TestEntity::FindFirst();
        $this->assertNull($entity);
    }

    function testFindFirstFailsIfResultSetIsEmpty()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn(null);
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame(
                    'testentity',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    '*',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertNull(
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertNull(
                    AccessHelper::GetProperty($query, 'orderBy')
                );
                $this->assertSame(
                    '1',
                    AccessHelper::GetProperty($query, 'limit')
                );
                $this->assertSame(
                    [],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $entity = TestEntity::FindFirst(); // No arguments
        $this->assertNull($entity);
    }

    function testFindFirstSucceedsIfResultSetIsNotEmpty()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn([
                'id' => 1,
                'name' => 'John',
                'age' => 30,
                'createdAt' => '2021-01-01'
            ]);
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame(
                    'testentity',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    '*',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    'age > :age',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    'name DESC',
                    AccessHelper::GetProperty($query, 'orderBy')
                );
                $this->assertSame(
                    '1',
                    AccessHelper::GetProperty($query, 'limit')
                );
                $this->assertSame(
                    ['age' => 29],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $entity = TestEntity::FindFirst(
            condition: 'age > :age',
            bindings: ['age' => 29],
            orderBy: 'name DESC'
        );
        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertSame(1, $entity->id);
        $this->assertSame('John', $entity->name);
        $this->assertSame(30, $entity->age);
        $this->assertSame('2021-01-01', $entity->createdAt->format('Y-m-d'));
    }

    #endregion FindFirst

    #region Find ---------------------------------------------------------------

    function testFindFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $entities = TestEntity::Find();
        $this->assertIsArray($entities);
        $this->assertEmpty($entities);
    }

    function testFindFailsIfResultSetIsEmpty()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($this->once())
            ->method('Row')
            ->willReturn(null);
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame(
                    'testentity',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    '*',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertNull(
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertNull(
                    AccessHelper::GetProperty($query, 'orderBy')
                );
                $this->assertNull(
                    AccessHelper::GetProperty($query, 'limit')
                );
                $this->assertSame(
                    [],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $entities = TestEntity::Find(); // No arguments
        $this->assertIsArray($entities);
        $this->assertEmpty($entities);
    }

    function testFindSucceedsIfResultSetIsNotEmpty()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($invokedCount = $this->exactly(3))
            ->method('Row')
            ->willReturnCallback(function() use($invokedCount) {
                switch ($invokedCount->numberOfInvocations()) {
                case 1:
                    return [
                        'id' => 3,
                        'name' => 'Alice Doe',
                        'age' => 27,
                        'createdAt' => '2021-01-01'
                    ];
                case 2:
                    return [
                        'id' => 4,
                        'name' => 'Aziz Smith',
                        'age' => 35,
                        'createdAt' => '2021-01-02'
                    ];
                case 3:
                    return null; // Stop iteration
                }
            });
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame(
                    'testentity',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    '*',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    'name LIKE :name AND age >= :age',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    'createdAt DESC',
                    AccessHelper::GetProperty($query, 'orderBy')
                );
                $this->assertSame(
                    '10 OFFSET 5',
                    AccessHelper::GetProperty($query, 'limit')
                );
                $this->assertSame(
                    ['name' => 'A%', 'age' => 25],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $entities = TestEntity::Find(
            condition: 'name LIKE :name AND age >= :age',
            bindings: ['name' => 'A%', 'age' => 25],
            orderBy: 'createdAt DESC',
            limit: 10,
            offset: 5
        );
        $this->assertIsArray($entities);
        $this->assertCount(2, $entities);
        $this->assertInstanceOf(TestEntity::class, $entities[0]);
        $this->assertSame(3           , $entities[0]->id);
        $this->assertSame('Alice Doe' , $entities[0]->name);
        $this->assertSame(27          , $entities[0]->age);
        $this->assertSame('2021-01-01', $entities[0]->createdAt->format('Y-m-d'));
        $this->assertInstanceOf(TestEntity::class, $entities[1]);
        $this->assertSame(4           , $entities[1]->id);
        $this->assertSame('Aziz Smith', $entities[1]->name);
        $this->assertSame(35          , $entities[1]->age);
        $this->assertSame('2021-01-02', $entities[1]->createdAt->format('Y-m-d'));
    }

    #endregion Find

    #region tableName ----------------------------------------------------------

    function testTableNameCanBeOverridden()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(DeleteQuery::class, $query);
                $this->assertSame(
                    'custom_table_name',
                    AccessHelper::GetProperty($query, 'table')
                );
                return true;
            }));
        $entity = new EntityWithCustomTableName(['id' => 1]);
        $entity->Delete();
    }

    #endregion tableName

    #region insert -------------------------------------------------------------

    function testInsertSkipsNonBindableProperties()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(InsertQuery::class, $query);
                $this->assertSame(
                    'entitywithnonbindableproperties',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    'aString',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    ':aString',
                    AccessHelper::GetProperty($query, 'values')
                );
                $this->assertSame(
                    ['aString' => 'Hello, World!'],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastInsertId')
            ->willReturn(1);
        $entity = new EntityWithNonBindableProperties([
            'anArray' => ['key' => 'value'],
            'aResource' => \fopen('php://memory', 'r'),
            'anObjectWithoutToString' => new \stdClass(),
            'aString' => 'Hello, World!'
        ]);
        $this->assertTrue(AccessHelper::CallMethod($entity, 'insert'));
        \fclose($entity->aResource);
    }

    function testInsertFailsOnEntityWithNoProperties()
    {
        $database = Database::Instance();
        $database->expects($this->never())
            ->method('Execute');
        $entity = new EntityWithNoProperties();
        $this->assertFalse(AccessHelper::CallMethod($entity, 'insert'));
    }

    function testInsertFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $entity = new TestEntity();
        $this->assertFalse(AccessHelper::CallMethod($entity, 'insert'));
    }

    function testInsertSucceedsIfExecuteSucceeds()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(InsertQuery::class, $query);
                $this->assertSame(
                    'testentity',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    'name, age, createdAt',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    ':name, :age, :createdAt',
                    AccessHelper::GetProperty($query, 'values')
                );
                $this->assertSame(
                    ['name' => 'John', 'age' => 30, 'createdAt' => '2021-01-01 12:34:56'],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastInsertId')
            ->willReturn(23);
        $entity = new TestEntity([
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01 12:34:56'
        ]);
        $this->assertTrue(AccessHelper::CallMethod($entity, 'insert'));
        $this->assertSame(23, $entity->id);
    }

    #endregion insert

    #region update -------------------------------------------------------------

    function testSaveUpdateSkipsNonBindableProperties()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(UpdateQuery::class, $query);
                $this->assertSame(
                    'entitywithnonbindableproperties',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    ['aString'],
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    [':aString'],
                    AccessHelper::GetProperty($query, 'values')
                );
                $this->assertSame(
                    'id = :id',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    ['id' => 23, 'aString' => 'Hello, World!'],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));
        $entity = new EntityWithNonBindableProperties([
            'id' => 23,
            'anArray' => ['key' => 'value'],
            'aResource' => \fopen('php://memory', 'r'),
            'anObjectWithoutToString' => new \stdClass(),
            'aString' => 'Hello, World!'
        ]);
        $this->assertTrue(AccessHelper::CallMethod($entity, 'update'));
        \fclose($entity->aResource);
    }

    function testUpdateFailsOnEntityWithNoProperties()
    {
        $database = Database::Instance();
        $database->expects($this->never())
            ->method('Execute');
        $entity = new EntityWithNoProperties();
        $this->assertFalse(AccessHelper::CallMethod($entity, 'update'));
    }

    function testUpdateFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $entity = new TestEntity();
        $this->assertFalse(AccessHelper::CallMethod($entity, 'update'));
    }

    function testUpdateFailsIfLastAffectedRowCountIsMinusOne()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->isInstanceOf(UpdateQuery::class))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastAffectedRowCount')
            ->willReturn(-1);
        $entity = new TestEntity();
        $this->assertFalse(AccessHelper::CallMethod($entity, 'update'));
    }

    function testUpdateSucceedsIfLastAffectedRowCountIsNotMinusOne()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(UpdateQuery::class, $query);
                $this->assertSame(
                    'testentity',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    ['name', 'age', 'createdAt'],
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    [':name', ':age', ':createdAt'],
                    AccessHelper::GetProperty($query, 'values')
                );
                $this->assertSame(
                    'id = :id',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    ['id' => 23, 'name' => 'John', 'age' => 30, 'createdAt' => '2021-01-01 12:34:56'],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastAffectedRowCount')
            ->willReturn(1);
        $entity = new TestEntity([
            'id' => 23,
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01 12:34:56'
        ]);
        $this->assertTrue(AccessHelper::CallMethod($entity, 'update'));
        $this->assertSame(23, $entity->id); // ID should remain unchanged
    }

    #endregion update
}
