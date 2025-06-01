<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Model\Entity;

use \Harmonia\Logger;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Queries\DeleteQuery;
use \Harmonia\Systems\DatabaseSystem\Queries\InsertQuery;
use \Harmonia\Systems\DatabaseSystem\Queries\SelectQuery;
use \Harmonia\Systems\DatabaseSystem\Queries\UpdateQuery;
use \Harmonia\Systems\DatabaseSystem\ResultSet;
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
    private ?Logger $originalLogger = null;

    protected function setUp(): void
    {
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalLogger =
            Logger::ReplaceInstance($this->createStub(Logger::class));
    }

    protected function tearDown(): void
    {
        Database::ReplaceInstance($this->originalDatabase);
        Logger::ReplaceInstance($this->originalLogger);
    }

    #region __construct --------------------------------------------------------

    function testBaseClassCannotBeInstantiated()
    {
        $this->expectException(\Error::class);
        new Entity();
    }

    function testConstructorWithNoData()
    {
        $sut = new TestEntity();
        $this->assertSame(0, $sut->id);
        $this->expectException(\Error::class);
        $sut->name;
        $this->expectException(\Error::class);
        $sut->age;
        $this->expectException(\Error::class);
        $sut->createdAt;
    }

    function testConstructorWithEmptyData()
    {
        $sut = new TestEntity([]);
        $this->assertSame(0, $sut->id);
        $this->assertSame('', $sut->name);
        $this->assertSame(0, $sut->age);
        $this->assertInstanceOf(\DateTime::class, $sut->createdAt);
    }

    function testConstructorWithDataExcludingId()
    {
        $sut = new TestEntity([
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01'
        ]);
        $this->assertSame(0, $sut->id);
        $this->assertSame('John', $sut->name);
        $this->assertSame(30, $sut->age);
        $this->assertSame('2021-01-01', $sut->createdAt->format('Y-m-d'));
    }

    function testConstructorWithDataIncludingId()
    {
        $sut = new TestEntity([
            'id' => 42,
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01'
        ]);
        $this->assertSame(42, $sut->id);
        $this->assertSame('John', $sut->name);
        $this->assertSame(30, $sut->age);
        $this->assertSame('2021-01-01', $sut->createdAt->format('Y-m-d'));
    }

    function testConstructorSkipsUnknownProperty()
    {
        $sut = new TestEntity([
            'unknown' => 'value'
        ]);
        $this->assertFalse(\property_exists($sut, 'unknown'));
    }

    function testConstructorSkipsNumericKeys()
    {
        $sut = new TestEntity([
            'John',
            30,
            '2021-01-01'
        ]);
        $this->assertSame(0, $sut->id);
        $this->assertSame('', $sut->name);
        $this->assertSame(0, $sut->age);
        $this->assertInstanceOf(\DateTime::class, $sut->createdAt);
    }

    function testConstructorSkipsIncorrectTypes()
    {
        $sut = new TestEntity([
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

    function testConstructorSkipsNonPublicProperties()
    {
        $data = [
            'aPrivate' => 99,
            'aProtected' => 99
        ];
        $sut = new class($data) extends Entity {
            private int $aPrivate = 1;
            protected int $aProtected = 2;
        };
        $this->assertSame(1, AccessHelper::GetProperty($sut, 'aPrivate'));
        $this->assertSame(2, AccessHelper::GetProperty($sut, 'aProtected'));
    }

    function testConstructorSkipsStaticProperties()
    {
        $data = [
            'aStaticPrivate' => 99,
            'aStaticProtected' => 99,
            'aStaticPublic' => 99
        ];
        $sut = new class($data) extends Entity {
            static private int $aStaticPrivate = 1;
            static protected int $aStaticProtected = 2;
            static public int $aStaticPublic = 3;
        };
        $class = \get_class($sut);
        $this->assertSame(1, AccessHelper::GetStaticProperty($class, 'aStaticPrivate'));
        $this->assertSame(2, AccessHelper::GetStaticProperty($class, 'aStaticProtected'));
        $this->assertSame(3, AccessHelper::GetStaticProperty($class, 'aStaticPublic'));
    }

    function testConstructorSkipsReadonlyProperties()
    {
        $data = [
            'aPublicReadonly' => 99,
            'aProtectedReadonly' => 99,
            'aPrivateReadonly' => 99
        ];
        $sut = new class($data) extends Entity {
            public readonly int $aPublicReadonly;
            protected readonly int $aProtectedReadonly;
            private readonly int $aPrivateReadonly;
            public function __construct(?array $data = null) {
                parent::__construct($data);
                $this->aPublicReadonly = 1;
                $this->aProtectedReadonly = 2;
                $this->aPrivateReadonly = 3;
            }
        };
        $this->assertSame(1, $sut->aPublicReadonly);
        $this->assertSame(2, AccessHelper::GetProperty($sut, 'aProtectedReadonly'));
        $this->assertSame(3, AccessHelper::GetProperty($sut, 'aPrivateReadonly'));
    }

    function testConstructorSkipsUninitializableProperties()
    {
        $data = [
            'anObject' => new \stdClass(),
            'anIterable' => new \ArrayIterator([1, 2, 3])
        ];
        $sut = new class($data) extends Entity {
            public object $anObject;
            public iterable $anIterable;
        };
        $this->expectException(\Error::class);
        $sut->anObject;
        $this->expectException(\Error::class);
        $sut->anIterable;
    }

    function testConstructorSkipsNonExistentClassProperty()
    {
        $data = [
            'aNullableProperty' => null
        ];
        $sut = new class($data) extends Entity {
            public ?NonExistentClass $aNullableProperty;
            public NonExistentClass $aProperty;
        };
        $this->assertNull($sut->aNullableProperty);
        $this->expectException(\Error::class);
        $sut->aProperty;
    }

    function testConstructorSkipsNonInstantiableClassProperties()
    {
        $sut = new class([]) extends Entity {
            public NonInstantiableClass1 $aProperty1;
            public NonInstantiableClass2 $aProperty2;
            public NonInstantiableClass3 $aProperty3;
            public NonInstantiableClass4 $aProperty4;
            public NonInstantiableClass5 $aProperty5;
            public NonInstantiableClass6 $aProperty6;
        };
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

    function testConstructorSkipsIntersectionProperty()
    {
        $data = [
            'aProperty' => new \ArrayIterator([1, 2, 3])
        ];
        $sut = new class($data) extends Entity {
            public \Iterator&\Countable $aProperty;
        };
        $this->expectException(\Error::class);
        $sut->aProperty;
    }

    function testConstructorSkipsUninitializedUnionProperty()
    {
        $data = ['aProperty' => 99];
        $sut = new class($data) extends Entity {
            public int|string $aProperty;
        };
        $this->expectException(\Error::class);
        $sut->aProperty;
    }

    function testConstructorAssignsInitializedUnionProperty()
    {
        $data = ['aProperty' => 99];
        $sut = new class($data) extends Entity {
            public int|string $aProperty = 1;
        };
        $this->assertSame($data['aProperty'], $sut->aProperty);
    }

    function testConstructorAssignsPrimitiveAndMixedProperties()
    {
        $data = [
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => "I'm a string",
            'anArray' => [1, 2, 3],
            'aMixed' => "I'm a string too"
        ];
        $sut = new class($data) extends Entity {
            public bool $aBool;
            public int $anInt;
            public float $aFloat;
            public string $aString;
            public array $anArray;
            public mixed $aMixed;
        };
        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['anArray'], $sut->anArray);
        $this->assertSame($data['aMixed'], $sut->aMixed);
    }

    function testConstructorAssignsNullableProperties()
    {
        $data = [
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => "I'm a string",
            'anArray' => [1, 2, 3],
            'anObject' => new \stdClass(),
            'anIterable' => new \ArrayIterator([1, 2, 3])
        ];
        $sut = new class($data) extends Entity {
            public ?bool $aBool;
            public ?int $anInt;
            public ?float $aFloat;
            public ?string $aString;
            public ?array $anArray;
            public ?object $anObject;
            public ?iterable $anIterable;
        };
        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['anArray'], $sut->anArray);
        $this->assertSame($data['anObject'], $sut->anObject);
        $this->assertSame($data['anIterable'], $sut->anIterable);
    }

    function testConstructorAssignsInitializedProperties()
    {
        $data = [
            'aBool' => false,
            'anInt' => 99,
            'aFloat' => 6.28,
            'aString' => "I'm another string",
            'anArray' => [4, 5, 6],
            'aMixed' => "I'm another string too"
        ];
        $sut = new class($data) extends Entity {
            public bool $aBool = true;
            public int $anInt = 42;
            public float $aFloat = 3.14;
            public string $aString = "I'm a string";
            public array $anArray = [1, 2, 3];
            public mixed $aMixed = "I'm a string too";
        };
        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['anArray'], $sut->anArray);
        $this->assertSame($data['aMixed'], $sut->aMixed);
    }

    function testConstructorAssignsUntypedProperties()
    {
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
        $sut = new class($data) extends Entity {
            public $aNull;
            public $aBool;
            public $anInt;
            public $aFloat;
            public $aString;
            public $anArray;
            public $anObject;
            public $anIterable;
        };
        $this->assertSame($data['aNull'], $sut->aNull);
        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['anArray'], $sut->anArray);
        $this->assertSame($data['anObject'], $sut->anObject);
        $this->assertSame($data['anIterable'], $sut->anIterable);
    }

    function testConstructorAssignsPromotedProperty()
    {
        $data = ['aProperty' => 99];
        $sut = new class(99, $data) extends Entity {
            public function __construct(public int $aProperty, ?array $data = null) {
                parent::__construct($data);
            }
        };
        $this->assertSame($data['aProperty'], $sut->aProperty);
    }

    function testConstructorSkipsInvalidDateTimeString()
    {
        $data = ['aDateTime' => 'not-a-date'];
        $sut = new class($data) extends Entity {
            public \DateTime $aDateTime;
        };
        $this->assertInstanceOf(\DateTime::class, $sut->aDateTime);
    }

    function testConstructorSkipsNullForNonNullableDateTime()
    {
        $data = ['aDateTime' => null];
        $sut = new class($data) extends Entity {
            public \DateTime $aDateTime;
        };
        $this->assertNotNull($sut->aDateTime);
    }

    function testConstructorAssignsNullToNullableDateTime()
    {
        $data = ['aDateTime' => null];
        $sut = new class($data) extends Entity {
            public ?\DateTime $aDateTime;
        };
        $this->assertNull($sut->aDateTime);
    }

    #endregion __construct

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
        $database = Database::Instance();
        $database->expects($this->never())
            ->method('Execute');
        $sut = new TestEntity();
        $this->assertFalse($sut->Delete());
    }

    function testDeleteFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $sut = new TestEntity(['id' => 1]);
        $this->assertFalse($sut->Delete());
        $this->assertSame(1, $sut->id); // ID should remain unchanged
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
        $sut = new TestEntity(['id' => 1]);
        $this->assertFalse($sut->Delete());
        $this->assertSame(1, $sut->id); // ID should remain unchanged
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
        $sut = new TestEntity(['id' => 1]);
        $this->assertTrue($sut->Delete());
        $this->assertSame(0, $sut->id); // ID should be reset to zero
    }

    #endregion Delete

    #region FindById -----------------------------------------------------------

    function testFindByIdFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $sut = TestEntity::FindById(1);
        $this->assertNull($sut);
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
                $this->assertSame(
                    '1',
                    AccessHelper::GetProperty($query, 'limit')
                );
                $this->assertSame(
                    ['id' => 1],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $sut = TestEntity::FindById(1);
        $this->assertNull($sut);
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
                $this->assertSame(
                    '1',
                    AccessHelper::GetProperty($query, 'limit')
                );
                $this->assertSame(
                    ['id' => 1],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
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
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $sut = TestEntity::FindFirst();
        $this->assertNull($sut);
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
        $sut = TestEntity::FindFirst(); // No arguments
        $this->assertNull($sut);
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

    #region Count --------------------------------------------------------------

    function testCountReturnsZeroIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $count = TestEntity::Count();
        $this->assertSame(0, $count);
    }

    function testCountReturnsZeroIfRowIsNull()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($this->once())
            ->method('Row')
            ->with(ResultSet::ROW_MODE_NUMERIC)
            ->willReturn(null);
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn($resultSet);
        $count = TestEntity::Count();
        $this->assertSame(0, $count);
    }

    function testCountReturnsZeroIfRowHasNoIndexZero()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($this->once())
            ->method('Row')
            ->with(ResultSet::ROW_MODE_NUMERIC)
            ->willReturn([]);
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn($resultSet);
        $count = TestEntity::Count();
        $this->assertSame(0, $count);
    }

    function testCountReturnsExpectedRowCount()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($this->once())
            ->method('Row')
            ->with(ResultSet::ROW_MODE_NUMERIC)
            ->willReturn([123]);
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
                    'COUNT(*)',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertNull(
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    [],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $count = TestEntity::Count();
        $this->assertSame(123, $count);
    }

    function testCountAcceptsConditionAndBindings()
    {
        $resultSet = $this->createMock(ResultSet::class);
        $resultSet->expects($this->once())
            ->method('Row')
            ->with(ResultSet::ROW_MODE_NUMERIC)
            ->willReturn([7]);
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
                    'COUNT(*)',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    'age > :age',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    ['age' => 30],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $count = TestEntity::Count('age > :age', ['age' => 30]);
        $this->assertSame(7, $count);
    }

    #endregion Count

    #region tableName ----------------------------------------------------------

    function testTableNameCanBeOverridden()
    {
        $sut = new class(['id' => 1]) extends Entity {
            protected static function tableName(): string {
                return 'custom_table_name';
            }
        };
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
        $sut->Delete();
    }

    #endregion tableName

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
            protected static function tableName(): string {
                return 'custom_table_name';
            }
        };
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(InsertQuery::class, $query);
                $this->assertSame(
                    'custom_table_name',
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
        $this->assertTrue(AccessHelper::CallMethod($sut, 'insert'));
        \fclose($sut->aResource);
    }

    function testInsertFailsOnEntityWithNoProperties()
    {
        $sut = new class extends Entity {
            // No properties
        };
        $database = Database::Instance();
        $database->expects($this->never())
            ->method('Execute');
        $this->assertFalse(AccessHelper::CallMethod($sut, 'insert'));
    }

    function testInsertFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $sut = new TestEntity();
        $this->assertFalse(AccessHelper::CallMethod($sut, 'insert'));
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
        $sut = new TestEntity([
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01 12:34:56'
        ]);
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
            protected static function tableName(): string {
                return 'custom_table_name';
            }
        };
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(UpdateQuery::class, $query);
                $this->assertSame(
                    'custom_table_name',
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
        $this->assertTrue(AccessHelper::CallMethod($sut, 'update'));
        \fclose($sut->aResource);
    }

    function testUpdateFailsOnEntityWithNoProperties()
    {
        $sut = new class extends Entity {
            // No properties
        };
        $database = Database::Instance();
        $database->expects($this->never())
            ->method('Execute');
        $this->assertFalse(AccessHelper::CallMethod($sut, 'update'));
    }

    function testUpdateFailsIfExecuteFails()
    {
        $database = Database::Instance();
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);
        $sut = new TestEntity();
        $this->assertFalse(AccessHelper::CallMethod($sut, 'update'));
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
        $sut = new TestEntity();
        $this->assertFalse(AccessHelper::CallMethod($sut, 'update'));
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
        $sut = new TestEntity([
            'id' => 23,
            'name' => 'John',
            'age' => 30,
            'createdAt' => '2021-01-01 12:34:56'
        ]);
        $this->assertTrue(AccessHelper::CallMethod($sut, 'update'));
        $this->assertSame(23, $sut->id); // ID should remain unchanged
    }

    #endregion update
}
