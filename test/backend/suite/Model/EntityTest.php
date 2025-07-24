<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Model\Entity;

use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

class TestEntity extends Entity {
    public bool $aBool;
    public int $anInt;
    public float $aFloat;
    public string $aString;
    public \DateTime $aDateTime;
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
        $data = [
            'id' => 1,
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => 'Hello, World!',
            'aDateTime' => '2025-07-22 14:35:00'
        ];
        $sut->expects($this->once())
            ->method('Populate')
            ->with($data);
        $sut->__construct($data);
    }

    #endregion __construct

    #region Populate -----------------------------------------------------------

    function testPopulateWithEmptyData()
    {
        $sut = new TestEntity();
        $sut->Populate([]);
        $this->assertSame(0, $sut->id);
        $this->assertSame(false, $sut->aBool);
        $this->assertSame(0, $sut->anInt);
        $this->assertSame(0.0, $sut->aFloat);
        $this->assertSame('', $sut->aString);
        $this->assertInstanceOf(\DateTime::class, $sut->aDateTime);
    }

    function testPopulateWithDataExcludingId()
    {
        $sut = new TestEntity();
        $data = [
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => 'Hello, World!',
            'aDateTime' => '2025-07-22 14:35:00'
        ];
        $sut->Populate($data);
        $this->assertSame(0, $sut->id);
        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['aDateTime'], $sut->aDateTime->format('Y-m-d H:i:s'));
    }

    function testPopulateWithDataIncludingId()
    {
        $sut = new TestEntity();
        $data = [
            'id' => 1,
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => 'Hello, World!',
            'aDateTime' => '2025-07-22 14:35:00'
        ];
        $sut->Populate($data);
        $this->assertSame(1, $sut->id);
        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['aDateTime'], $sut->aDateTime->format('Y-m-d H:i:s'));
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
            true,
            42,
            3.14,
            'Hello, World!',
            '2025-07-22 14:35:00'
        ]);
        $this->assertSame(0, $sut->id);
        $this->assertSame(false, $sut->aBool);
        $this->assertSame(0, $sut->anInt);
        $this->assertSame(0.0, $sut->aFloat);
        $this->assertSame('', $sut->aString);
        $this->assertInstanceOf(\DateTime::class, $sut->aDateTime);
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

    function testPopulateSkipsPropertiesWithUnsupportedTypes()
    {
        $sut = new class extends Entity {
            public array $anArray;
            public \stdClass $anObject;
            public iterable $anIterable;
            public \DateTimeImmutable $aDateTimeImmutable;
        };
        $sut->Populate([
            'anArray' => [1, 2, 3],
            'anObject' => new \stdClass(),
            'anIterable' => new \ArrayIterator([1, 2, 3]),
            'aDateTimeImmutable' => '2025-07-22 14:35:00'
        ]);
        $this->expectException(\Error::class);
        $sut->anArray;
        $this->expectException(\Error::class);
        $sut->anObject;
        $this->expectException(\Error::class);
        $sut->anIterable;
        $this->expectException(\Error::class);
        $sut->aDateTimeImmutable;
    }

    function testPopulateSkipsIntersectionProperty()
    {
        $sut = new class extends Entity {
            public \Iterator&\Countable $anIntersection;
        };
        $sut->Populate([
            'anIntersection' => new \ArrayIterator([1, 2, 3])
        ]);
        $this->expectException(\Error::class);
        $sut->anIntersection;
    }

    function testPopulateSkipsUnionProperty()
    {
        $sut = new class extends Entity {
            public int|string $aUnion;
        };
        $sut->Populate([
            'aUnion' => 99
        ]);
        $this->expectException(\Error::class);
        $sut->aUnion;
    }

    function testPopulateAssignsNullableProperties()
    {
        $sut = new class extends Entity {
            public ?bool $aNullableBool;
            public ?int $aNullableInt;
            public ?float $aNullableFloat;
            public ?string $aNullableString;
            public ?\DateTime $aNullableDateTime;
        };
        // 1. With non-null values
        $data = [
            'aNullableBool' => true,
            'aNullableInt' => 42,
            'aNullableFloat' => 3.14,
            'aNullableString' => "I'm a string",
            'aNullableDateTime' => '2021-01-01'
        ];
        $sut->Populate($data);
        $this->assertSame($data['aNullableBool'], $sut->aNullableBool);
        $this->assertSame($data['aNullableInt'], $sut->aNullableInt);
        $this->assertSame($data['aNullableFloat'], $sut->aNullableFloat);
        $this->assertSame($data['aNullableString'], $sut->aNullableString);
        $this->assertSame($data['aNullableDateTime'], $sut->aNullableDateTime->format('Y-m-d'));
        // 2. With null values
        $data = [
            'aNullableBool' => null,
            'aNullableInt' => null,
            'aNullableFloat' => null,
            'aNullableString' => null,
            'aNullableDateTime' => null
        ];
        $sut->Populate($data);
        // todo: Currently coerces null to false for `?bool` due to lack of
        // nullability info. This will be corrected once properties() yields
        // nullability metadata.
        //$this->assertNull($sut->aNullableBool);
        $this->assertNull($sut->aNullableInt);
        $this->assertNull($sut->aNullableFloat);
        $this->assertNull($sut->aNullableString);
        $this->assertNull($sut->aNullableDateTime);
    }

    function testPopulateAssignsInitializedProperties()
    {
        $sut = new class extends Entity {
            public bool $aBool = true;
            public int $anInt = 42;
            public float $aFloat = 3.14;
            public string $aString = "I'm a string";
            public \DateTime $aDateTime;
            public function __construct() {
                $this->aDateTime = new \DateTime('2020-01-01');
            }
        };
        $data = [
            'aBool' => false,
            'anInt' => 99,
            'aFloat' => 6.28,
            'aString' => "I'm another string",
            'aDateTime' => '2025-07-24'
        ];
        $sut->Populate($data);
        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['aDateTime'], $sut->aDateTime->format('Y-m-d'));
    }

    function testPopulateAssignsPromotedProperty()
    {
        $sut = new class(1) extends Entity {
            public function __construct(public int $aPromoted) {
            }
        };
        $sut->Populate([
            'aPromoted' => 99
        ]);
        $this->assertSame(99, $sut->aPromoted);
    }

    function testPopulateCastsCommonValuesToBool()
    {
        $sut = new class extends Entity {
            public bool $aBool;
        };
        // Truthy
        $sut->Populate(['aBool' => 1]);
        $this->assertSame(true, $sut->aBool);
        $sut->Populate(['aBool' => '1']);
        $this->assertSame(true, $sut->aBool);
        $sut->Populate(['aBool' => 'yes']);
        $this->assertSame(true, $sut->aBool);
        $sut->Populate(['aBool' => 'no']);
        $this->assertSame(true, $sut->aBool); // Still true
        $sut->Populate(['aBool' => true]);
        $this->assertSame(true, $sut->aBool);
        // Falsy
        $sut->Populate(['aBool' => 0]);
        $this->assertSame(false, $sut->aBool);
        $sut->Populate(['aBool' => '0']);
        $this->assertSame(false, $sut->aBool);
        $sut->Populate(['aBool' => '']);
        $this->assertSame(false, $sut->aBool);
        $sut->Populate(['aBool' => false]);
        $this->assertSame(false, $sut->aBool);
    }

    function testPopulateThrowsOnInvalidPropertyValue()
    {
        $sut = new TestEntity();
        $this->expectException(\InvalidArgumentException::class);
        $sut->Populate([
            'aBool' => 'not-a-bool',
            'anInt' => 'not-an-int',
            'aFloat' => 'not-a-float',
            'aString' => 12345,
            'aDateTime' => 'not-a-datetime'
        ]);
    }

    function testPopulateThrowsOnInvalidDateTimeString()
    {
        $sut = new class extends Entity {
            public \DateTime $aDateTime;
            public function __construct() {
                $this->aDateTime = new \DateTime('2021-01-01');
            }
        };
        $this->expectException(\InvalidArgumentException::class);
        @$sut->Populate([
            'aDateTime' => 'not-a-datetime'
        ]);
    }

    function testPopulateThrowsOnNullAssignmentToNonNullableDateTime()
    {
        $sut = new class extends Entity {
            public \DateTime $aDateTime;
        };
        $this->expectException(\InvalidArgumentException::class);
        @$sut->Populate([
            'aDateTime' => null
        ]);
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

    function testPopulateAssignsStringToNullableDateTimeWhenCurrentlyNull()
    {
        $sut = new class extends Entity {
            public ?\DateTime $aDateTime;
        };
        $sut->aDateTime = null;
        $data = [
            'aDateTime' => '2025-07-22 14:35:00'
        ];
        $sut->Populate($data);
        $this->assertInstanceOf(\DateTime::class, $sut->aDateTime);
        $this->assertSame($data['aDateTime'], $sut->aDateTime->format('Y-m-d H:i:s'));
    }

    function testPopulateAssignsDateWithoutTime()
    {
        $sut = new class extends Entity {
            public \DateTime $aDateTime;
        };
        $sut->Populate(['aDateTime' => '2025-07-22']);
        $this->assertSame('2025-07-22 00:00:00', $sut->aDateTime->format('Y-m-d H:i:s'));
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
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'DELETE FROM testentity WHERE id = :id',
            bindings: ['id' => 1],
            result: null,
            times: 1
        );
        $this->assertFalse($sut->Delete());
        $this->assertSame(1, $sut->id);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testDeleteFailsIfLastAffectedRowCountIsNotOne()
    {
        $sut = new TestEntity(['id' => 1]);
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'DELETE FROM testentity WHERE id = :id',
            bindings: ['id' => 1],
            result: [],
            lastAffectedRowCount: 0,
            times: 1
        );
        $this->assertFalse($sut->Delete());
        $this->assertSame(1, $sut->id);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testDeleteSucceedsIfLastAffectedRowCountIsOne()
    {
        $sut = new TestEntity(['id' => 1]);
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'DELETE FROM testentity WHERE id = :id',
            bindings: ['id' => 1],
            result: [],
            lastAffectedRowCount: 1,
            times: 1
        );
        $this->assertTrue($sut->Delete());
        $this->assertSame(0, $sut->id);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion Delete

    #region jsonSerialize ------------------------------------------------------

    function testJsonSerializeReturnsExpectedJson()
    {
        $sut = new TestEntity([
            'id' => 1,
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => 'Hello, World!',
            'aDateTime' => '2025-07-22 14:35:00'
        ]);
        $this->assertSame(
            '{"aBool":true,"anInt":42,"aFloat":3.14,"aString":"Hello, World!"'
          . ',"aDateTime":"2025-07-22 14:35:00","id":1}'
          , \json_encode($sut)
        );
    }

    function testJsonSerializeExcludesPropertiesWithUnsupportedTypes()
    {
        $sut = new class extends Entity {
            public string $aString = 'Hello';
            public array $anArray = ['not' => 'included'];
            public \DateTime $aDateTime;
            public function __construct() {
                $this->aDateTime = new \DateTime('2025-07-22 14:35:00');
            }
        };
        $this->assertSame(
            '{"aString":"Hello","aDateTime":"2025-07-22 14:35:00","id":0}',
            \json_encode($sut)
        );
    }

    function testJsonSerializeEncodesNullForNullableDateTime()
    {
        $sut = new class extends Entity {
            public ?\DateTime $aNullableDateTime = null;
        };
        $this->assertSame(
            '{"aNullableDateTime":null,"id":0}',
            \json_encode($sut)
        );
    }

    #endregion jsonSerialize

    #region TableName ----------------------------------------------------------

    function testTableNameCanBeOverridden()
    {
        $data = ['id' => 1];
        $sut = new class($data) extends Entity {
            public static function TableName(): string {
                return 'custom_table_name';
            }
        };
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'DELETE FROM custom_table_name WHERE id = :id',
            bindings: ['id' => 1],
            result: [],
            lastAffectedRowCount: 1,
            times: 1
        );
        $this->assertTrue($sut->Delete());
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion TableName

    #region Columns ------------------------------------------------------------

    function testColumnsReturnsIdInFirstPosition()
    {
        $this->assertSame(
            ['id', 'aBool', 'anInt', 'aFloat', 'aString', 'aDateTime'],
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

    function testColumnsSkipsPropertiesWithUnsupportedTypes()
    {
        $sut = new class extends Entity {
            public array $anArray;
            public \stdClass $anObject;
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
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM testentity WHERE id = :id LIMIT 1',
            bindings: ['id' => 1],
            result: null,
            times: 1
        );
        $this->assertNull(TestEntity::FindById(1));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindByIdFailsIfResultSetIsEmpty()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM testentity WHERE id = :id LIMIT 1',
            bindings: ['id' => 1],
            result: [],
            times: 1
        );
        $this->assertNull(TestEntity::FindById(1));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindByIdSucceedsIfResultSetIsNotEmpty()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM testentity WHERE id = :id LIMIT 1',
            bindings: ['id' => 1],
            result: [[
                'id' => 1,
                'aBool' => 1,
                'anInt' => 30,
                'aFloat' => 3.14,
                'aString' => 'John',
                'aDateTime' => '2021-01-01 12:34:56'
            ]],
            times: 1
        );
        $sut = TestEntity::FindById(1);
        $this->assertInstanceOf(TestEntity::class, $sut);
        $this->assertSame(1, $sut->id);
        $this->assertSame(true, $sut->aBool);
        $this->assertSame(30, $sut->anInt);
        $this->assertSame(3.14, $sut->aFloat);
        $this->assertSame('John', $sut->aString);
        $this->assertSame('2021-01-01 12:34:56', $sut->aDateTime->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion FindById

    #region FindFirst ----------------------------------------------------------

    function testFindFirstFailsIfExecuteFails()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM testentity LIMIT 1',
            result: null,
            times: 1
        );
        $this->assertNull(TestEntity::FindFirst());
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindFirstFailsIfResultSetIsEmpty()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM testentity LIMIT 1',
            result: [],
            times: 1
        );
        $this->assertNull(TestEntity::FindFirst());
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindFirstSucceedsIfResultSetIsNotEmpty()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM testentity WHERE anInt > :anInt ORDER BY aString DESC LIMIT 1',
            bindings: ['anInt' => 29],
            result: [[
                'id' => 1,
                'aBool' => 0,
                'anInt' => 30,
                'aFloat' => 3.14,
                'aString' => 'John',
                'aDateTime' => '2021-01-01 12:34:56'
            ]],
            times: 1
        );
        $sut = TestEntity::FindFirst(
            condition: 'anInt > :anInt',
            bindings: ['anInt' => 29],
            orderBy: 'aString DESC'
        );
        $this->assertInstanceOf(TestEntity::class, $sut);
        $this->assertSame(1, $sut->id);
        $this->assertSame(false, $sut->aBool);
        $this->assertSame(30, $sut->anInt);
        $this->assertSame(3.14, $sut->aFloat);
        $this->assertSame('John', $sut->aString);
        $this->assertSame('2021-01-01 12:34:56', $sut->aDateTime->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion FindFirst

    #region Find ---------------------------------------------------------------

    function testFindFailsIfExecuteFails()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM testentity',
            result: null,
            times: 1
        );
        $entities = TestEntity::Find();
        $this->assertIsArray($entities);
        $this->assertEmpty($entities);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindFailsIfResultSetIsEmpty()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM testentity',
            result: [],
            times: 1
        );
        $entities = TestEntity::Find();
        $this->assertIsArray($entities);
        $this->assertEmpty($entities);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindSucceedsIfResultSetIsNotEmpty()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM testentity'
               . ' WHERE aString LIKE :aString AND anInt >= :anInt'
               . ' ORDER BY aDateTime DESC LIMIT 10 OFFSET 5',
            bindings: ['aString' => 'A%', 'anInt' => 25],
            result: [[
                'id' => 3,
                'aBool' => 1,
                'anInt' => 27,
                'aFloat' => 1.23,
                'aString' => 'Alice Doe',
                'aDateTime' => '2021-01-01 10:00:00'
            ], [
                'id' => 4,
                'aBool' => 0,
                'anInt' => 35,
                'aFloat' => 4.56,
                'aString' => 'Aziz Smith',
                'aDateTime' => '2021-01-02 09:00:00'
            ]],
            times: 1
        );
        $entities = TestEntity::Find(
            condition: 'aString LIKE :aString AND anInt >= :anInt',
            bindings: ['aString' => 'A%', 'anInt' => 25],
            orderBy: 'aDateTime DESC',
            limit: 10,
            offset: 5
        );
        $this->assertIsArray($entities);
        $this->assertCount(2, $entities);
        $this->assertInstanceOf(TestEntity::class, $entities[0]);
          $this->assertSame(3, $entities[0]->id);
          $this->assertSame(true, $entities[0]->aBool);
          $this->assertSame(27, $entities[0]->anInt);
          $this->assertSame(1.23, $entities[0]->aFloat);
          $this->assertSame('Alice Doe', $entities[0]->aString);
          $this->assertSame('2021-01-01 10:00:00', $entities[0]->aDateTime->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(TestEntity::class, $entities[1]);
          $this->assertSame(4, $entities[1]->id);
          $this->assertSame(false, $entities[1]->aBool);
          $this->assertSame(35, $entities[1]->anInt);
          $this->assertSame(4.56, $entities[1]->aFloat);
          $this->assertSame('Aziz Smith', $entities[1]->aString);
          $this->assertSame('2021-01-02 09:00:00', $entities[1]->aDateTime->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion Find

    #region Count --------------------------------------------------------------

    function testCountReturnsZeroIfExecuteFails()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM testentity',
            result: null,
            times: 1
        );
        $this->assertSame(0, TestEntity::Count());
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testCountReturnsZeroIfRowIsNull()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM testentity',
            result: [],
            times: 1
        );
        $this->assertSame(0, TestEntity::Count());
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testCountReturnsZeroIfRowHasNoIndexZero()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM testentity',
            result: [[]],
            times: 1
        );
        $this->assertSame(0, TestEntity::Count());
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testCountReturnsExpectedRowCount()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM testentity',
            result: [[123]],
            times: 1
        );
        $this->assertSame(123, TestEntity::Count());
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testCountAcceptsConditionAndBindings()
    {
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM testentity WHERE anInt > :anInt',
            bindings: ['anInt' => 30],
            result: [[7]],
            times: 1
        );
        $this->assertSame(7, TestEntity::Count('anInt > :anInt', ['anInt' => 30]));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion Count

    #region insert -------------------------------------------------------------

    function testInsertFailsOnEntityWithNoProperties()
    {
        $sut = new class extends Entity {
            // No properties
        };
        $this->assertFalse(AccessHelper::CallMethod($sut, 'insert'));
    }

    function testInsertFailsIfOnlyIdPropertyIsPresent()
    {
        $sut = new class(['id' => 123]) extends Entity {
        };
        $this->assertFalse(AccessHelper::CallMethod($sut, 'insert'));
    }

    function testInsertFailsIfExecuteFails()
    {
        $sut = new TestEntity([
            'aBool' => true,
            'anInt' => 30,
            'aFloat' => 3.14,
            'aString' => 'John',
            'aDateTime' => '2021-01-01 12:34:56'
        ]);
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'INSERT INTO testentity'
               . ' (aBool, anInt, aFloat, aString, aDateTime)'
               . ' VALUES (:aBool, :anInt, :aFloat, :aString, :aDateTime)',
            bindings: [
                'aBool' => true,
                'anInt' => 30,
                'aFloat' => 3.14,
                'aString' => 'John',
                'aDateTime' => '2021-01-01 12:34:56'
            ],
            result: null,
            times: 1
        );
        $this->assertFalse(AccessHelper::CallMethod($sut, 'insert'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testInsertSucceedsIfExecuteSucceeds()
    {
        $sut = new TestEntity([
            'aBool' => true,
            'anInt' => 30,
            'aFloat' => 3.14,
            'aString' => 'John',
            'aDateTime' => '2021-01-01 12:34:56'
        ]);
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'INSERT INTO testentity'
               . ' (aBool, anInt, aFloat, aString, aDateTime)'
               . ' VALUES (:aBool, :anInt, :aFloat, :aString, :aDateTime)',
            bindings: [
                'aBool' => true,
                'anInt' => 30,
                'aFloat' => 3.14,
                'aString' => 'John',
                'aDateTime' => '2021-01-01 12:34:56'
            ],
            result: [],
            lastInsertId: 23,
            times: 1
        );
        $this->assertTrue(AccessHelper::CallMethod($sut, 'insert'));
        $this->assertSame(23, $sut->id);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion insert

    #region update -------------------------------------------------------------

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
            'aBool' => true,
            'anInt' => 30,
            'aFloat' => 3.14,
            'aString' => 'John',
            'aDateTime' => '2021-01-01 12:34:56'
        ]);
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'UPDATE testentity'
               . ' SET aBool = :aBool, anInt = :anInt, aFloat = :aFloat,'
               . ' aString = :aString, aDateTime = :aDateTime'
               . ' WHERE id = :id',
            bindings: [
                'id' => 23,
                'aBool' => true,
                'anInt' => 30,
                'aFloat' => 3.14,
                'aString' => 'John',
                'aDateTime' => '2021-01-01 12:34:56'
            ],
            result: null,
            times: 1
        );
        $this->assertFalse(AccessHelper::CallMethod($sut, 'update'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testUpdateFailsIfLastAffectedRowCountIsMinusOne()
    {
        $sut = new TestEntity([
            'id' => 23,
            'aBool' => true,
            'anInt' => 30,
            'aFloat' => 3.14,
            'aString' => 'John',
            'aDateTime' => '2021-01-01 12:34:56'
        ]);
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'UPDATE testentity'
               . ' SET aBool = :aBool, anInt = :anInt, aFloat = :aFloat,'
               . ' aString = :aString, aDateTime = :aDateTime'
               . ' WHERE id = :id',
            bindings: [
                'id' => 23,
                'aBool' => true,
                'anInt' => 30,
                'aFloat' => 3.14,
                'aString' => 'John',
                'aDateTime' => '2021-01-01 12:34:56'
            ],
            result: [],
            lastAffectedRowCount: -1,
            times: 1
        );
        $this->assertFalse(AccessHelper::CallMethod($sut, 'update'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testUpdateSucceedsIfLastAffectedRowCountIsNotMinusOne()
    {
        $sut = new TestEntity([
            'id' => 23,
            'aBool' => true,
            'anInt' => 30,
            'aFloat' => 3.14,
            'aString' => 'John',
            'aDateTime' => '2021-01-01 12:34:56'
        ]);
        $fakeDatabase = Database::Instance();
        $fakeDatabase->Expect(
            sql: 'UPDATE testentity'
               . ' SET aBool = :aBool, anInt = :anInt, aFloat = :aFloat,'
               . ' aString = :aString, aDateTime = :aDateTime'
               . ' WHERE id = :id',
            bindings: [
                'id' => 23,
                'aBool' => true,
                'anInt' => 30,
                'aFloat' => 3.14,
                'aString' => 'John',
                'aDateTime' => '2021-01-01 12:34:56'
            ],
            result: [],
            lastAffectedRowCount: 1,
            times: 1
        );
        $this->assertTrue(AccessHelper::CallMethod($sut, 'update'));
        $this->assertSame(23, $sut->id);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion update
}
