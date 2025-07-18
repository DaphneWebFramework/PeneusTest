<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Model\Entity;

use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

class NonInstantiableClass1 { public function __construct(int $x) {} }
class NonInstantiableClass2 { private function __construct() {} }
abstract class NonInstantiableClass3 {}
interface NonInstantiableClass4 {}
trait NonInstantiableClass5 {}
enum NonInstantiableClass6 {}

class TestEntity extends Entity {
    public string $name;
    public int $age;
    public \DateTime $createdAt;
}

#[CoversClass(Entity::class)]
class EntityTest extends TestCase
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

    #region __construct --------------------------------------------------------

    function testBaseClassCannotBeInstantiated()
    {
        $this->expectException(\Error::class);
        new Entity();
    }

    function testConstructorDoesNotCallPopulateWhenDataIsOmitted()
    {
        $sut = $this->getMockBuilder(TestEntity::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['Populate'])
            ->getMock();
        $sut->expects($this->never())
            ->method('Populate');

        $sut->__construct();
    }

    function testConstructorDoesNotCallPopulateWhenDataIsNull()
    {
        $sut = $this->getMockBuilder(TestEntity::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['Populate'])
            ->getMock();
        $sut->expects($this->never())
            ->method('Populate');

        $sut->__construct(null);
    }

    function testConstructorCallsPopulateWhenDataIsProvided()
    {
        $sut = $this->getMockBuilder(TestEntity::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['Populate'])
            ->getMock();
        $sut->expects($this->once())
            ->method('Populate')
            ->with(['id' => 1, 'name' => 'John']);

        $sut->__construct(['id' => 1, 'name' => 'John']);
    }

    #endregion __construct

    #region Populate -----------------------------------------------------------

    function testPopulateWithEmptyData()
    {
        $sut = new TestEntity();
        $sut->Populate([]);

        $this->assertSame(0, $sut->id);
        $this->assertSame('', $sut->name);
        $this->assertSame(0, $sut->age);
        $this->assertInstanceOf(\DateTime::class, $sut->createdAt);
    }

    function testPopulateWithDataExcludingId()
    {
        $sut = new TestEntity();
        $data = [
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01'
        ];
        $sut->Populate($data);

        $this->assertSame(0, $sut->id);
        $this->assertSame($data['name'], $sut->name);
        $this->assertSame($data['age'], $sut->age);
        $this->assertSame($data['createdAt'], $sut->createdAt->format('Y-m-d'));
    }

    function testPopulateWithDataIncludingId()
    {
        $sut = new TestEntity();
        $data = [
            'id' => 42,
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01'
        ];
        $sut->Populate($data);

        $this->assertSame($data['id'], $sut->id);
        $this->assertSame($data['name'], $sut->name);
        $this->assertSame($data['age'], $sut->age);
        $this->assertSame($data['createdAt'], $sut->createdAt->format('Y-m-d'));
    }

    function testPopulateSkipsUnknownProperty()
    {
        $sut = new TestEntity();
        $sut->Populate([
            'unknown' => 'value'
        ]);

        $this->assertFalse(\property_exists($sut, 'unknown'));
    }

    function testPopulateSkipsNumericKeys()
    {
        $sut = new TestEntity();
        $sut->Populate([
            'John',
            30,
            '2021-01-01'
        ]);

        $this->assertSame(0, $sut->id);
        $this->assertSame('', $sut->name);
        $this->assertSame(0, $sut->age);
        $this->assertInstanceOf(\DateTime::class, $sut->createdAt);
    }

    function testPopulateSkipsIncorrectTypes()
    {
        $sut = new TestEntity();
        $sut->Populate([
            'id' => '42',
            'name' => 30,
            'age' => '30',
            'createdAt' => 20210101
        ]);

        $this->assertSame(0, $sut->id);
        $this->assertSame('', $sut->name);
        $this->assertSame(0, $sut->age);
        $this->assertInstanceOf(\DateTime::class, $sut->createdAt);
    }

    function testPopulateSkipsNonPublicProperties()
    {
        $sut = new class extends Entity {
            private int $aPrivate = 1;
            protected int $aProtected = 2;
        };
        $sut->Populate([
            'aPrivate' => 99,
            'aProtected' => 99
        ]);

        $this->assertSame(1, AccessHelper::GetProperty($sut, 'aPrivate'));
        $this->assertSame(2, AccessHelper::GetProperty($sut, 'aProtected'));
    }

    function testPopulateSkipsStaticProperties()
    {
        $sut = new class extends Entity {
            static private int $aStaticPrivate = 1;
            static protected int $aStaticProtected = 2;
            static public int $aStaticPublic = 3;
        };
        $sut->Populate([
            'aStaticPrivate' => 99,
            'aStaticProtected' => 99,
            'aStaticPublic' => 99
        ]);

        $class = \get_class($sut);
        $this->assertSame(1, AccessHelper::GetStaticProperty($class, 'aStaticPrivate'));
        $this->assertSame(2, AccessHelper::GetStaticProperty($class, 'aStaticProtected'));
        $this->assertSame(3, AccessHelper::GetStaticProperty($class, 'aStaticPublic'));
    }

    function testPopulateSkipsReadonlyProperties()
    {
        $sut = new class extends Entity {
            public readonly int $aPublicReadonly;
            protected readonly int $aProtectedReadonly;
            private readonly int $aPrivateReadonly;
            public function __construct() {
                $this->aPublicReadonly = 1;
                $this->aProtectedReadonly = 2;
                $this->aPrivateReadonly = 3;
            }
        };
        $sut->Populate([
            'aPublicReadonly' => 99,
            'aProtectedReadonly' => 99,
            'aPrivateReadonly' => 99
        ]);

        $this->assertSame(1, $sut->aPublicReadonly);
        $this->assertSame(2, AccessHelper::GetProperty($sut, 'aProtectedReadonly'));
        $this->assertSame(3, AccessHelper::GetProperty($sut, 'aPrivateReadonly'));
    }

    function testPopulateSkipsUninitializableProperties()
    {
        $sut = new class extends Entity {
            public object $anObject;
            public iterable $anIterable;
        };
        $sut->Populate([
            'anObject' => new \stdClass(),
            'anIterable' => new \ArrayIterator([1, 2, 3])
        ]);

        $this->expectException(\Error::class);
        $sut->anObject;
        $this->expectException(\Error::class);
        $sut->anIterable;
    }

    function testPopulateSkipsNonExistentClassProperty()
    {
        $sut = new class extends Entity {
            public ?NonExistentClass $aNullableProperty;
            public NonExistentClass $aProperty;
        };
        $sut->Populate([
            'aNullableProperty' => null
        ]);

        $this->assertNull($sut->aNullableProperty);
        $this->expectException(\Error::class);
        $sut->aProperty;
    }

    function testPopulateSkipsNonInstantiableClassProperties()
    {
        $sut = new class extends Entity {
            public NonInstantiableClass1 $aProperty1;
            public NonInstantiableClass2 $aProperty2;
            public NonInstantiableClass3 $aProperty3;
            public NonInstantiableClass4 $aProperty4;
            public NonInstantiableClass5 $aProperty5;
            public NonInstantiableClass6 $aProperty6;
        };
        $sut->Populate([]);

        $this->expectException(\Error::class);
        $sut->aProperty1;
        $this->expectException(\Error::class);
        $sut->aProperty2;
        $this->expectException(\Error::class);
        $sut->aProperty3;
        $this->expectException(\Error::class);
        $sut->aProperty4;
        $this->expectException(\Error::class);
        $sut->aProperty5;
        $this->expectException(\Error::class);
        $sut->aProperty6;
    }

    function testPopulateSkipsIntersectionProperty()
    {
        $sut = new class extends Entity {
            public \Iterator&\Countable $aProperty;
        };
        $sut->Populate([
            'aProperty' => new \ArrayIterator([1, 2, 3])
        ]);

        $this->expectException(\Error::class);
        $sut->aProperty;
    }

    function testPopulateSkipsUninitializedUnionProperty()
    {
        $sut = new class extends Entity {
            public int|string $aProperty;
        };
        $sut->Populate([
            'aProperty' => 99
        ]);

        $this->expectException(\Error::class);
        $sut->aProperty;
    }

    function testPopulateAssignsInitializedUnionProperty()
    {
        $sut = new class extends Entity {
            public int|string $aProperty = 1;
        };
        $sut->Populate([
            'aProperty' => 99
        ]);

        $this->assertSame(99, $sut->aProperty);
    }

    function testPopulateAssignsPrimitiveAndMixedProperties()
    {
        $sut = new class extends Entity {
            public bool $aBool;
            public int $anInt;
            public float $aFloat;
            public string $aString;
            public array $anArray;
            public mixed $aMixed;
        };
        $data = [
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => "I'm a string",
            'anArray' => [1, 2, 3],
            'aMixed' => "I'm a string too"
        ];
        $sut->Populate($data);

        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['anArray'], $sut->anArray);
        $this->assertSame($data['aMixed'], $sut->aMixed);
    }

    function testPopulateAssignsNullableProperties()
    {
        $sut = new class extends Entity {
            public ?bool $aBool;
            public ?int $anInt;
            public ?float $aFloat;
            public ?string $aString;
            public ?array $anArray;
            public ?object $anObject;
            public ?iterable $anIterable;
        };
        $data = [
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => "I'm a string",
            'anArray' => [1, 2, 3],
            'anObject' => new \stdClass(),
            'anIterable' => new \ArrayIterator([1, 2, 3])
        ];
        $sut->Populate($data);

        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['anArray'], $sut->anArray);
        $this->assertSame($data['anObject'], $sut->anObject);
        $this->assertSame($data['anIterable'], $sut->anIterable);
    }

    function testPopulateOverridesInitializedProperties()
    {
        $sut = new class extends Entity {
            public bool $aBool = true;
            public int $anInt = 42;
            public float $aFloat = 3.14;
            public string $aString = "I'm a string";
            public array $anArray = [1, 2, 3];
            public mixed $aMixed = "I'm a string too";
        };
        $data = [
            'aBool' => false,
            'anInt' => 99,
            'aFloat' => 6.28,
            'aString' => "I'm another string",
            'anArray' => [4, 5, 6],
            'aMixed' => "I'm another string too"
        ];
        $sut->Populate($data);

        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['anArray'], $sut->anArray);
        $this->assertSame($data['aMixed'], $sut->aMixed);
    }

    function testPopulateAssignsUntypedProperties()
    {
        $sut = new class extends Entity {
            public $aNull;
            public $aBool;
            public $anInt;
            public $aFloat;
            public $aString;
            public $anArray;
            public $anObject;
            public $anIterable;
        };
        $data = [
            'aNull' => null,
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => "I'm a string",
            'anArray' => [1, 2, 3],
            'anObject' => new \stdClass(),
            'anIterable' => new \ArrayIterator([1, 2, 3])
        ];
        $sut->Populate($data);

        $this->assertSame($data['aNull'], $sut->aNull);
        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['anArray'], $sut->anArray);
        $this->assertSame($data['anObject'], $sut->anObject);
        $this->assertSame($data['anIterable'], $sut->anIterable);
    }

    function testPopulateOverridesPromotedProperty()
    {
        $sut = new class(1) extends Entity {
            public function __construct(public int $aProperty) {
            }
        };
        $sut->Populate([
            'aProperty' => 99
        ]);

        $this->assertSame(99, $sut->aProperty);
    }

    function testPopulateSkipsInvalidDateTimeString()
    {
        $sut = new class extends Entity {
            public \DateTime $aDateTime;
        };
        $sut->Populate([
            'aDateTime' => 'not-a-datetime'
        ]);

        $this->assertInstanceOf(\DateTime::class, $sut->aDateTime);
    }

    function testPopulateSkipsNullForNonNullableDateTime()
    {
        $sut = new class extends Entity {
            public \DateTime $aDateTime;
        };
        $sut->Populate([
            'aDateTime' => null
        ]);

        $this->assertNotNull($sut->aDateTime);
    }

    function testPopulateAssignsNullToNullableDateTime()
    {
        $sut = new class extends Entity {
            public ?\DateTime $aDateTime;
        };
        $sut->Populate([
            'aDateTime' => null
        ]);

        $this->assertNull($sut->aDateTime);
    }

    #endregion Populate

    #region Save ---------------------------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testSaveCallsInsertWhenIdIsZero($returnValue)
    {
        $sut = $this->getMockBuilder(TestEntity::class)
            ->setConstructorArgs([[]]) // i.e., id = 0
            ->onlyMethods(['insert'])
            ->getMock();
        $sut->expects($this->once())
            ->method('insert')
            ->willReturn($returnValue);
        $this->assertSame($returnValue, $sut->Save());
    }

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testSaveCallsUpdateWhenIdIsNotZero($returnValue)
    {
        $sut = $this->getMockBuilder(TestEntity::class)
            ->setConstructorArgs([['id' => 1]])
            ->onlyMethods(['update'])
            ->getMock();
        $sut->expects($this->once())
            ->method('update')
            ->willReturn($returnValue);
        $this->assertSame($returnValue, $sut->Save());
    }

    #endregion Save

    #region Delete -------------------------------------------------------------

    function testDeleteFailsIfIdIsZero()
    {
        $sut = new TestEntity();
        $this->assertFalse($sut->Delete());
    }

    function testDeleteFailsIfExecuteFails()
    {
        $sut = new TestEntity(['id' => 1]);
        Database::Instance()->Expect(
            sql: 'DELETE FROM testentity WHERE id = :id',
            bindings: ['id' => 1],
            result: null,
            times: 1
        );
        $this->assertFalse($sut->Delete());
        $this->assertSame(1, $sut->id);
    }

    function testDeleteFailsIfLastAffectedRowCountIsNotOne()
    {
        $sut = new TestEntity(['id' => 1]);
        Database::Instance()->Expect(
            sql: 'DELETE FROM testentity WHERE id = :id',
            bindings: ['id' => 1],
            result: [],
            lastAffectedRowCount: 0,
            times: 1
        );
        $this->assertFalse($sut->Delete());
        $this->assertSame(1, $sut->id);
    }

    function testDeleteSucceedsIfLastAffectedRowCountIsOne()
    {
        $sut = new TestEntity(['id' => 1]);
        Database::Instance()->Expect(
            sql: 'DELETE FROM testentity WHERE id = :id',
            bindings: ['id' => 1],
            result: [],
            lastAffectedRowCount: 1,
            times: 1
        );
        $this->assertTrue($sut->Delete());
        $this->assertSame(0, $sut->id);
    }

    #endregion Delete

    #region TableName ----------------------------------------------------------

    function testTableNameCanBeOverridden()
    {
        $data = ['id' => 1];
        $sut = new class($data) extends Entity {
            public static function TableName(): string {
                return 'custom_table_name';
            }
        };
        Database::Instance()->Expect(
            sql: 'DELETE FROM custom_table_name WHERE id = :id',
            bindings: ['id' => 1],
            result: [],
            lastAffectedRowCount: 1,
            times: 1
        );
        $this->assertTrue($sut->Delete());
    }

    #endregion TableName

    #region Columns ------------------------------------------------------------

    function testColumnsReturnsIdInFirstPosition()
    {
        $this->assertSame(
            ['id', 'name', 'age', 'createdAt'],
            TestEntity::Columns()
        );
    }

    function testColumnsSkipsInaccessibleProperties()
    {
        $sut = new class extends Entity {
            public string $aString;
            protected string $aProtected;
            private string $aPrivate;
            public readonly string $aPublicReadonly;
            protected readonly string $aProtectedReadonly;
            private readonly string $aPrivateReadonly;
            static public string $aStaticPublic;
            static protected string $aStaticProtected;
            static private string $aStaticPrivate;
            public function __construct() {
                $this->aPublicReadonly = '';
                $this->aProtectedReadonly = '';
                $this->aPrivateReadonly = '';
                parent::__construct();
            }
        };
        $this->assertSame(
            ['id', 'aString'],
            (\get_class($sut))::Columns()
        );
    }

    function testColumnsSkipsNonBindableProperties()
    {
        $sut = new class extends Entity {
            public array $anArray;
            public \stdClass $anObjectWithoutToString;
            public string $aString;
        };
        $this->assertSame(
            ['id', 'aString'],
            (\get_class($sut))::Columns()
        );
    }

    #endregion Columns

    #region FindById -----------------------------------------------------------

    function testFindByIdFailsIfExecuteFails()
    {
        Database::Instance()->Expect(
            sql: 'SELECT * FROM testentity WHERE id = :id LIMIT 1',
            bindings: ['id' => 1],
            result: null,
            times: 1
        );
        $this->assertNull(TestEntity::FindById(1));
    }

    function testFindByIdFailsIfResultSetIsEmpty()
    {
        Database::Instance()->Expect(
            sql: 'SELECT * FROM testentity WHERE id = :id LIMIT 1',
            bindings: ['id' => 1],
            result: [],
            times: 1
        );
        $this->assertNull(TestEntity::FindById(1));
    }

    function testFindByIdSucceedsIfResultSetIsNotEmpty()
    {
        Database::Instance()->Expect(
            sql: 'SELECT * FROM testentity WHERE id = :id LIMIT 1',
            bindings: ['id' => 1],
            result: [[
                'id' => 1,
                'name' => 'John',
                'age' => 30,
                'createdAt' => '2021-01-01'
            ]],
            times: 1
        );
        $sut = TestEntity::FindById(1);
        $this->assertInstanceOf(TestEntity::class, $sut);
        $this->assertSame(1, $sut->id);
        $this->assertSame('John', $sut->name);
        $this->assertSame(30, $sut->age);
        $this->assertSame('2021-01-01', $sut->createdAt->format('Y-m-d'));
    }

    #endregion FindById

    #region FindFirst ----------------------------------------------------------

    function testFindFirstFailsIfExecuteFails()
    {
        Database::Instance()->Expect(
            sql: 'SELECT * FROM testentity LIMIT 1',
            bindings: [],
            result: null,
            times: 1
        );
        $this->assertNull(TestEntity::FindFirst());
    }

    function testFindFirstFailsIfResultSetIsEmpty()
    {
        Database::Instance()->Expect(
            sql: 'SELECT * FROM testentity LIMIT 1',
            bindings: [],
            result: [],
            times: 1
        );
        $this->assertNull(TestEntity::FindFirst());
    }

    function testFindFirstSucceedsIfResultSetIsNotEmpty()
    {
        Database::Instance()->Expect(
            sql: 'SELECT * FROM testentity WHERE age > :age ORDER BY name DESC LIMIT 1',
            bindings: ['age' => 29],
            result: [[
                'id' => 1,
                'name' => 'John',
                'age' => 30,
                'createdAt' => '2021-01-01'
            ]],
            times: 1
        );
        $sut = TestEntity::FindFirst(
            condition: 'age > :age',
            bindings: ['age' => 29],
            orderBy: 'name DESC'
        );
        $this->assertInstanceOf(TestEntity::class, $sut);
        $this->assertSame(1, $sut->id);
        $this->assertSame('John', $sut->name);
        $this->assertSame(30, $sut->age);
        $this->assertSame('2021-01-01', $sut->createdAt->format('Y-m-d'));
    }

    #endregion FindFirst

    #region Find ---------------------------------------------------------------

    function testFindFailsIfExecuteFails()
    {
        Database::Instance()->Expect(
            sql: 'SELECT * FROM testentity',
            result: null,
            times: 1
        );
        $entities = TestEntity::Find();
        $this->assertIsArray($entities);
        $this->assertEmpty($entities);
    }

    function testFindFailsIfResultSetIsEmpty()
    {
        Database::Instance()->Expect(
            sql: 'SELECT * FROM testentity',
            result: [],
            times: 1
        );
        $entities = TestEntity::Find();
        $this->assertIsArray($entities);
        $this->assertEmpty($entities);
    }

    function testFindSucceedsIfResultSetIsNotEmpty()
    {
        Database::Instance()->Expect(
            sql: 'SELECT * FROM testentity'
            . ' WHERE name LIKE :name AND age >= :age'
            . ' ORDER BY createdAt DESC LIMIT 10 OFFSET 5',
            bindings: ['name' => 'A%', 'age' => 25],
            result: [[
                'id' => 3,
                'name' => 'Alice Doe',
                'age' => 27,
                'createdAt' => '2021-01-01'
            ], [
                'id' => 4,
                'name' => 'Aziz Smith',
                'age' => 35,
                'createdAt' => '2021-01-02'
            ]],
            times: 1
        );
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

    #region Count --------------------------------------------------------------

    function testCountReturnsZeroIfExecuteFails()
    {
        Database::Instance()->Expect(
            sql: 'SELECT COUNT(*) FROM testentity',
            result: null,
            times: 1
        );
        $this->assertSame(0, TestEntity::Count());
    }

    function testCountReturnsZeroIfRowIsNull()
    {
        Database::Instance()->Expect(
            sql: 'SELECT COUNT(*) FROM testentity',
            result: [],
            times: 1
        );
        $this->assertSame(0, TestEntity::Count());
    }

    function testCountReturnsZeroIfRowHasNoIndexZero()
    {
        Database::Instance()->Expect(
            sql: 'SELECT COUNT(*) FROM testentity',
            result: [[]],
            times: 1
        );
        $this->assertSame(0, TestEntity::Count());
    }

    function testCountReturnsExpectedRowCount()
    {
        Database::Instance()->Expect(
            sql: 'SELECT COUNT(*) FROM testentity',
            result: [[123]],
            times: 1
        );
        $this->assertSame(123, TestEntity::Count());
    }

    function testCountAcceptsConditionAndBindings()
    {
        Database::Instance()->Expect(
            sql: 'SELECT COUNT(*) FROM testentity WHERE age > :age',
            bindings: ['age' => 30],
            result: [[7]],
            times: 1
        );
        $this->assertSame(7, TestEntity::Count('age > :age', ['age' => 30]));
    }

    #endregion Count

    #region insert -------------------------------------------------------------

    function testInsertSkipsNonBindableProperties()
    {
        $data = [
            'anArray' => ['key' => 'value'],
            'aResource' => \fopen('php://memory', 'r'),
            'anObjectWithoutToString' => new \stdClass(),
            'aString' => 'Hello, World!'
        ];
        $sut = new class($data) extends Entity {
            public array $anArray;
            public mixed $aResource;
            public \stdClass $anObjectWithoutToString;
            public string $aString;
            public static function TableName(): string {
                return 'custom_table_name';
            }
        };
        Database::Instance()->Expect(
            sql: 'INSERT INTO custom_table_name (aString) VALUES (:aString)',
            bindings: ['aString' => 'Hello, World!'],
            result: [],
            lastInsertId: 1,
            times: 1
        );
        $this->assertTrue(AccessHelper::CallMethod($sut, 'insert'));
        \fclose($sut->aResource);
    }

    function testInsertFailsOnEntityWithNoProperties()
    {
        $sut = new class extends Entity {
            // No properties
        };
        $this->assertFalse(AccessHelper::CallMethod($sut, 'insert'));
    }

    function testInsertFailsIfExecuteFails()
    {
        $sut = new TestEntity([
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01 12:34:56'
        ]);
        Database::Instance()->Expect(
            sql: 'INSERT INTO testentity'
               . ' (name, age, createdAt)'
               . ' VALUES (:name, :age, :createdAt)',
            bindings: [
                'name' => 'John',
                'age' => 30,
                'createdAt' => '2021-01-01 12:34:56'
            ],
            result: null,
            times: 1
        );
        $this->assertFalse(AccessHelper::CallMethod($sut, 'insert'));
    }

    function testInsertSucceedsIfExecuteSucceeds()
    {
        $sut = new TestEntity([
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01 12:34:56'
        ]);
        Database::Instance()->Expect(
            sql: 'INSERT INTO testentity'
               . ' (name, age, createdAt)'
               . ' VALUES (:name, :age, :createdAt)',
            bindings: [
                'name' => 'John',
                'age' => 30,
                'createdAt' => '2021-01-01 12:34:56'
            ],
            result: [],
            lastInsertId: 23,
            times: 1
        );
        $this->assertTrue(AccessHelper::CallMethod($sut, 'insert'));
        $this->assertSame(23, $sut->id);
    }

    #endregion insert

    #region update -------------------------------------------------------------

    function testUpdateSkipsNonBindableProperties()
    {
        $data = [
            'id' => 23,
            'anArray' => ['key' => 'value'],
            'aResource' => \fopen('php://memory', 'r'),
            'anObjectWithoutToString' => new \stdClass(),
            'aString' => 'Hello, World!'
        ];
        $sut = new class($data) extends Entity {
            public array $anArray;
            public mixed $aResource;
            public \stdClass $anObjectWithoutToString;
            public string $aString;
            public static function TableName(): string {
                return 'custom_table_name';
            }
        };
        Database::Instance()->Expect(
            sql: 'UPDATE custom_table_name SET aString = :aString WHERE id = :id',
            bindings: ['id' => 23, 'aString' => 'Hello, World!'],
            result: [],
            lastAffectedRowCount: 1,
            times: 1
        );
        $this->assertTrue(AccessHelper::CallMethod($sut, 'update'));
        \fclose($sut->aResource);
    }

    function testUpdateFailsOnEntityWithNoProperties()
    {
        $sut = new class extends Entity {
            // No properties
        };
        $this->assertFalse(AccessHelper::CallMethod($sut, 'update'));
    }

    function testUpdateFailsIfExecuteFails()
    {
        $sut = new TestEntity([
            'id' => 23,
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01 12:34:56'
        ]);
        Database::Instance()->Expect(
            sql: 'UPDATE testentity'
               . ' SET name = :name, age = :age, createdAt = :createdAt'
               . ' WHERE id = :id',
            bindings: [
                'id' => 23,
                'name' => 'John',
                'age' => 30,
                'createdAt' => '2021-01-01 12:34:56'
            ],
            result: null,
            times: 1
        );
        $this->assertFalse(AccessHelper::CallMethod($sut, 'update'));
    }

    function testUpdateFailsIfLastAffectedRowCountIsMinusOne()
    {
        $sut = new TestEntity([
            'id' => 23,
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01 12:34:56'
        ]);
        Database::Instance()->Expect(
            sql: 'UPDATE testentity'
               . ' SET name = :name, age = :age, createdAt = :createdAt'
               . ' WHERE id = :id',
            bindings: [
                'id' => 23,
                'name' => 'John',
                'age' => 30,
                'createdAt' => '2021-01-01 12:34:56'
            ],
            result: [],
            lastAffectedRowCount: -1,
            times: 1
        );
        $this->assertFalse(AccessHelper::CallMethod($sut, 'update'));
    }

    function testUpdateSucceedsIfLastAffectedRowCountIsNotMinusOne()
    {
        $sut = new TestEntity([
            'id' => 23,
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01 12:34:56'
        ]);
        Database::Instance()->Expect(
            sql: 'UPDATE testentity'
               . ' SET name = :name, age = :age, createdAt = :createdAt'
               . ' WHERE id = :id',
            bindings: [
                'id' => 23,
                'name' => 'John',
                'age' => 30,
                'createdAt' => '2021-01-01 12:34:56'
            ],
            result: [],
            lastAffectedRowCount: 1,
            times: 1
        );
        $this->assertTrue(AccessHelper::CallMethod($sut, 'update'));
        $this->assertSame(23, $sut->id);
    }

    #endregion update
}
