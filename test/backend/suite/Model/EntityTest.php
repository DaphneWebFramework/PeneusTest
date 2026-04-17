<?php declare(strict_types=1);
namespace suite\Model;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Model\Entity;

use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\ViewEntity;
use \TestToolkit\AccessHelper as ah;

enum TPureEnum {
    case Zero;
    case One;
    case Two;
}

enum TEmptyIntegerEnum: int {}

enum TEmptyStringEnum: string {}

enum TIntegerEnum: int {
    case Zero = 0;
    case One = 1;
    case Two = 2;
}

enum TStringEnum: string {
    case Zero = 'zero';
    case One = 'one';
    case Two = 'two';
}

class TEmptyEntity extends Entity {}

class TEntity extends Entity {
    public bool           $aBool;
    public ?bool          $aNullableBool;
    public int            $anInt;
    public ?int           $aNullableInt;
    public float          $aFloat;
    public ?float         $aNullableFloat;
    public string         $aString;
    public ?string        $aNullableString;
    public \DateTime      $aDateTime;
    public ?\DateTime     $aNullableDateTime;
    public TIntegerEnum   $anIntegerEnum;
    public ?TIntegerEnum  $aNullableIntegerEnum;
    public TStringEnum    $aStringEnum;
    public ?TStringEnum   $aNullableStringEnum;
}

class TViewEntity extends ViewEntity {
    public static function ViewDefinition(): string {
        return 'SELECT 1';
    }
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

    private function systemUnderTest(string ...$mockedMethods): Entity
    {
        return $this->getMockBuilder(Entity::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    private function assertProperties(Entity $sut, array $extras): void
    {
        $expected = [
            ...$extras,
            'id' => [
                'type' => 'int',
                'nullable' => false
            ]
        ];
        $actual = \iterator_to_array(ah::CallMethod($sut, 'properties'));
        $this->assertSame($expected, $actual);
    }

    #region __construct --------------------------------------------------------

    function testBaseClassCannotBeInstantiated()
    {
        $this->expectException(\Error::class);

        new Entity();
    }

    function testConstructorDoesNotCallPopulateWhenDataIsOmitted()
    {
        $sut = $this->systemUnderTest('Populate');

        $sut->expects($this->never())
            ->method('Populate');

        $sut->__construct();
    }

    function testConstructorDoesNotCallPopulateWhenDataIsNull()
    {
        $sut = $this->systemUnderTest('Populate');

        $sut->expects($this->never())
            ->method('Populate');

        $sut->__construct(null);
    }

    function testConstructorCallsPopulateWhenArrayDataIsProvided()
    {
        $sut = $this->systemUnderTest('Populate');
        $data = [];

        $sut->expects($this->once())
            ->method('Populate')
            ->with($data);

        $sut->__construct($data);
    }

    function testConstructorCallsPopulateWhenObjectDataIsProvided()
    {
        $sut = $this->systemUnderTest('Populate');
        $data = new \stdClass();

        $sut->expects($this->once())
            ->method('Populate')
            ->with($data);

        $sut->__construct($data);
    }

    #endregion __construct

    #region Populate -----------------------------------------------------------

    function testPopulateWithEmptyData()
    {
        $sut = new TEntity();

        $sut->Populate([]);

        $this->assertSame(0, $sut->id);
        $this->assertSame(false, $sut->aBool);
        $this->assertNull($sut->aNullableBool);
        $this->assertSame(0, $sut->anInt);
        $this->assertNull($sut->aNullableInt);
        $this->assertSame(0.0, $sut->aFloat);
        $this->assertNull($sut->aNullableFloat);
        $this->assertSame('', $sut->aString);
        $this->assertNull($sut->aNullableString);
        $this->assertInstanceOf(\DateTime::class, $sut->aDateTime);
        $this->assertEqualsWithDelta(\time(), $sut->aDateTime->getTimestamp(), 1);
        $this->assertNull($sut->aNullableDateTime);
        $this->assertSame(TIntegerEnum::Zero, $sut->anIntegerEnum);
        $this->assertNull($sut->aNullableIntegerEnum);
        $this->assertSame(TStringEnum::Zero, $sut->aStringEnum);
        $this->assertNull($sut->aNullableStringEnum);
    }

    function testPopulateWithArrayData()
    {
        $sut = new TEntity();
        $data = [
            'id' => 17,
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => 'Hello, World!',
            'aDateTime' => '2025-07-22 14:35:00'
        ];

        $sut->Populate($data);

        $this->assertSame($data['id'], $sut->id);
        $this->assertSame($data['aBool'], $sut->aBool);
        $this->assertSame($data['anInt'], $sut->anInt);
        $this->assertSame($data['aFloat'], $sut->aFloat);
        $this->assertSame($data['aString'], $sut->aString);
        $this->assertSame($data['aDateTime'], $sut->aDateTime->format('Y-m-d H:i:s'));
    }

    function testPopulateWithStdClassData()
    {
        $sut = new TEntity();
        $data = (object)[
            'id' => 17,
            'aBool' => true,
            'anInt' => 42,
            'aFloat' => 3.14,
            'aString' => 'Hello, World!',
            'aDateTime' => '2025-07-22 14:35:00'
        ];

        $sut->Populate($data);

        $this->assertSame($data->id, $sut->id);
        $this->assertSame($data->aBool, $sut->aBool);
        $this->assertSame($data->anInt, $sut->anInt);
        $this->assertSame($data->aFloat, $sut->aFloat);
        $this->assertSame($data->aString, $sut->aString);
        $this->assertSame($data->aDateTime, $sut->aDateTime->format('Y-m-d H:i:s'));
    }

    function testPopulateWithClassInstanceData()
    {
        $sut = new TEntity();
        $data = new class {
            public int $id = 17;
            public bool $aBool = true;
            private int $anInt = 42; // private
            public float $aFloat = 3.14;
            public static string $aString = 'Hello, World!'; // static
            public string $aDateTime = '2025-07-22 14:35:00';
            protected TIntegerEnum $anIntegerEnum = TIntegerEnum::One; // protected
            public TStringEnum $aStringEnum = TStringEnum::Two;
        };

        $sut->Populate($data);

        $this->assertSame($data->id, $sut->id);
        $this->assertSame($data->aBool, $sut->aBool);
        $this->assertSame(0, $sut->anInt); // should not be assigned
        $this->assertSame($data->aFloat, $sut->aFloat);
        $this->assertSame('', $sut->aString); // should not be assigned
        $this->assertSame($data->aDateTime, $sut->aDateTime->format('Y-m-d H:i:s'));
        $this->assertSame(TIntegerEnum::Zero, $sut->anIntegerEnum); // should not be assigned
        $this->assertSame(TStringEnum::Two, $sut->aStringEnum);
    }

    function testPopulateAssignsNullToNullable()
    {
        $sut = new TEntity();
        $sut->aNullableBool = true;
        $sut->aNullableInt = 42;
        $sut->aNullableFloat = 3.14;
        $sut->aNullableString = 'Hello, World!';
        $sut->aNullableDateTime = new \DateTime('2025-07-22 14:35:00');
        $sut->aNullableIntegerEnum = TIntegerEnum::One;
        $sut->aNullableStringEnum = TStringEnum::Two;
        $data = [
            'aNullableBool' => null,
            'aNullableInt' => null,
            'aNullableFloat' => null,
            'aNullableString' => null,
            'aNullableDateTime' => null,
            'aNullableIntegerEnum' => null,
            'aNullableStringEnum' => null
        ];

        $sut->Populate($data);

        $this->assertNull($sut->aNullableBool);
        $this->assertNull($sut->aNullableInt);
        $this->assertNull($sut->aNullableFloat);
        $this->assertNull($sut->aNullableString);
        $this->assertNull($sut->aNullableDateTime);
        $this->assertNull($sut->aNullableIntegerEnum);
        $this->assertNull($sut->aNullableStringEnum);
    }

    #[TestWith(['aBool'])]
    #[TestWith(['anInt'])]
    #[TestWith(['aFloat'])]
    #[TestWith(['aString'])]
    #[TestWith(['aDateTime'])]
    #[TestWith(['anIntegerEnum'])]
    #[TestWith(['aStringEnum'])]
    function testPopulateThrowsWhenAssigningNullToNonNullable(string $property)
    {
        $sut = new TEntity();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Failed to assign value to property '$property'.");

        $sut->Populate([$property => null]);
    }

    #[TestWith(['anInt', '123'])]
    #[TestWith(['aFloat', '1.23'])]
    #[TestWith(['aString', 123])]
    #[TestWith(['aDateTime', 123])]
    #[TestWith(['anIntegerEnum', '123'])]
    #[TestWith(['aStringEnum', 123])]
    function testPopulateThrowsOnIncompatibleTypes(string $property, mixed $value)
    {
        $sut = new TEntity();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Failed to assign value to property '{$property}'.");

        $sut->Populate([$property => $value]);
    }

    #[TestWith([false, false])]
    #[TestWith([true, true])]
    #[TestWith([false, 0])]
    #[TestWith([true, 1])]
    #[TestWith([true, -1])]
    #[TestWith([false, '0'])]
    #[TestWith([true, '1'])]
    #[TestWith([true, 'false'])]
    #[TestWith([true, 'true'])]
    #[TestWith([true, 'no'])]
    #[TestWith([true, 'yes'])]
    #[TestWith([false, ''])]
    #[TestWith([true, ' '])]
    #[TestWith([false, 0.0])]
    #[TestWith([true, 0.1])]
    #[TestWith([false, []])]
    #[TestWith([true, [1]])]
    function testPopulateAssignsBoolean(bool $expected, mixed $value)
    {
        $sut = new TEntity();

        $sut->Populate(['aBool' => $value]);

        $this->assertSame($expected, $sut->aBool);
    }

    #[TestWith(['2025-07-22 00:00:00', '2025-07-22'])]
    #[TestWith(['2025-07-22 00:00:00', '2025-07-22 00:00:00'])]
    #[TestWith(['2025-07-22 14:35:00', '2025-07-22 14:35:00'])]
    #[TestWith(['2024-07-22 14:35:00', '@1721658900'])]
    function testPopulateAssignsDateTimeString(string $expected, string $value)
    {
        $sut = new TEntity();

        $sut->Populate(['aDateTime' => $value]);

        $this->assertSame($expected, $sut->aDateTime->format('Y-m-d H:i:s'));
    }

    function testPopulateAssignsDateTimeInstance()
    {
        $sut = new TEntity();
        $expected = new \DateTime('2026-04-16 10:00:00');

        $sut->Populate(['aDateTime' => $expected]);

        $this->assertSame($expected, $sut->aDateTime);
    }

    #[TestWith(['invalid-datetime'])]
    #[TestWith(['2025-13-01'])]
    #[TestWith(['2025-07-45'])]
    function testPopulateThrowsWhenAssigningInvalidDateTime(string $value)
    {
        $sut = new TEntity();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Failed to assign value to property 'aDateTime'.");

        $sut->Populate(['aDateTime' => $value]);
    }

    #[TestWith([TIntegerEnum::Zero, 0])]
    #[TestWith([TIntegerEnum::One, 1])]
    #[TestWith([TIntegerEnum::Two, 2])]
    #[TestWith([TIntegerEnum::Zero, TIntegerEnum::Zero])]
    #[TestWith([TIntegerEnum::One, TIntegerEnum::One])]
    #[TestWith([TIntegerEnum::Two, TIntegerEnum::Two])]
    function testPopulateAssignsIntegerEnum(mixed $expected, mixed $value)
    {
        $sut = new TEntity();

        $sut->Populate(['anIntegerEnum' => $value]);

        $this->assertSame($expected, $sut->anIntegerEnum);
    }

    #[TestWith([TStringEnum::Zero, 'zero'])]
    #[TestWith([TStringEnum::One, 'one'])]
    #[TestWith([TStringEnum::Two, 'two'])]
    #[TestWith([TStringEnum::Zero, TStringEnum::Zero])]
    #[TestWith([TStringEnum::One, TStringEnum::One])]
    #[TestWith([TStringEnum::Two, TStringEnum::Two])]
    function testPopulateAssignsStringEnum(mixed $expected, mixed $value)
    {
        $sut = new TEntity();

        $sut->Populate(['aStringEnum' => $value]);

        $this->assertSame($expected, $sut->aStringEnum);
    }

    function testPopulateSkipsNonEligibleProperties()
    {
        $sut = new class extends Entity {
            protected int       $aProtected = 1;
            private int         $aPrivate = 2;
            public static int   $aStatic = 3;
            public readonly int $aReadonly;
            public              $anUntyped = 5;
            public int|string   $aUnion = 6;
            public array        $anArray = [7];

            public function __construct() {
                $this->aReadonly = 4;
            }
        };

        $sut->Populate([
            'aProtected' => 99,
            'aPrivate'   => 99,
            'aStatic'    => 99,
            'aReadonly'  => 99,
            'anUntyped'  => 99,
            'aUnion'     => 99,
            'anArray'    => [99]
        ]);

        $this->assertSame(0, $sut->id);
        $this->assertSame(1, ah::GetProperty($sut, 'aProtected'));
        $this->assertSame(2, ah::GetProperty($sut, 'aPrivate'));
        $this->assertSame(3, \get_class($sut)::$aStatic);
        $this->assertSame(4, $sut->aReadonly);
        $this->assertSame(5, $sut->anUntyped);
        $this->assertSame(6, $sut->aUnion);
        $this->assertSame([7], $sut->anArray);
    }

    #endregion Populate

    #region Save ---------------------------------------------------------------

    #[TestWith([true])]
    #[TestWith([false])]
    function testSaveCallsInsertWhenIdIsZero(bool $returnValue)
    {
        $sut = $this->systemUnderTest('insert', 'update');

        $sut->expects($this->once())
            ->method('insert')
            ->willReturn($returnValue);
        $sut->expects($this->never())
            ->method('update');

        $this->assertSame($returnValue, $sut->Save());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    function testSaveCallsUpdateWhenIdIsNotZero(bool $returnValue)
    {
        $sut = $this->systemUnderTest('insert', 'update');
        $sut->id = 1;

        $sut->expects($this->never())
            ->method('insert');
        $sut->expects($this->once())
            ->method('update')
            ->willReturn($returnValue);

        $this->assertSame($returnValue, $sut->Save());
    }

    #endregion Save

    #region Delete -------------------------------------------------------------

    function testDeleteFailsIfIdIsZero()
    {
        $sut = new TEntity();

        $this->assertFalse($sut->Delete());
    }

    #[TestWith([false, null, 0, 1])]
    #[TestWith([false, [],   0, 1])]
    #[TestWith([false, [],   2, 1])] // lastAffectedRowCount is more than 1
    #[TestWith([true,  [],   1, 0])]
    function testDelete($expected, $result, $lastAffectedRowCount, $finalId)
    {
        $sut = new TEntity(['id' => 1]);
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'DELETE FROM `tentity`'
               . ' WHERE `id` = :id',
            bindings: ['id' => 1],
            result: $result,
            lastAffectedRowCount: $lastAffectedRowCount,
            times: 1
        );

        $actual = $sut->Delete();

        $this->assertSame($expected, $actual);
        $this->assertSame($finalId, $sut->id);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion Delete

    #region jsonSerialize ------------------------------------------------------

    function testJsonSerialize()
    {
        $sut = new TEntity([
            'id'            => 1,
            'aBool'         => true,
            'anInt'         => 42,
            'aFloat'        => 3.14,
            'aString'       => 'Hello, World!',
            'aDateTime'     => '2025-07-22 14:35:00',
            'anIntegerEnum' => TIntegerEnum::One,
            'aStringEnum'   => TStringEnum::Two
        ]);
        $expected = [
            'id'                   => 1,
            'aBool'                => true,
            'aNullableBool'        => null,
            'anInt'                => 42,
            'aNullableInt'         => null,
            'aFloat'               => 3.14,
            'aNullableFloat'       => null,
            'aString'              => 'Hello, World!',
            'aNullableString'      => null,
            'aDateTime'            => '2025-07-22 14:35:00',
            'aNullableDateTime'    => null,
            'anIntegerEnum'        => TIntegerEnum::One->value,
            'aNullableIntegerEnum' => null,
            'aStringEnum'          => TStringEnum::Two->value,
            'aNullableStringEnum'  => null
        ];

        $actual = $sut->jsonSerialize();

        $this->assertSame($expected, $actual);
    }

    #endregion jsonSerialize

    #region Without ------------------------------------------------------------

    function testWithoutExcludesSpecifiedProperties()
    {
        $sut = $this->systemUnderTest('jsonSerialize');
        $serialized = ['kept' => 0, 'excluded' => 0];
        $expected = ['kept' => 0];

        $sut->expects($this->once())
            ->method('jsonSerialize')
            ->willReturn($serialized);

        $actual = $sut->Without('excluded');

        $this->assertSame($expected, $actual);
    }

    function testWithoutReturnsOriginalIfNoPropertiesAreExcluded()
    {
        $sut = $this->systemUnderTest('jsonSerialize');
        $serialized = ['kept' => 0];
        $expected = $serialized;

        $sut->expects($this->once())
            ->method('jsonSerialize')
            ->willReturn($serialized);

        $actual = $sut->Without();

        $this->assertSame($expected, $actual);
    }

    function testWithoutIgnoresNonExistentProperties()
    {
        $sut = $this->systemUnderTest('jsonSerialize');
        $serialized = ['kept' => 0, 'excluded' => 0];
        $expected = ['kept' => 0];

        $sut->expects($this->once())
            ->method('jsonSerialize')
            ->willReturn($serialized);

        $actual = $sut->Without('non-existent', 'excluded');

        $this->assertSame($expected, $actual);
    }

    #endregion Without

    #region IsView -------------------------------------------------------------

    function testIsViewReturnsTrueForViewEntity()
    {
        $this->assertTrue(TViewEntity::IsView());
    }

    function testIsViewReturnsFalseForRegularEntity()
    {
        $this->assertFalse(TEntity::IsView());
    }

    #endregion IsView

    #region TableName ----------------------------------------------------------

    function testTableNameReturnsLowercaseClassNameByDefault()
    {
        $this->assertSame('tentity', TEntity::TableName());
    }

    function testTableNameCanBeOverridden()
    {
        $customEntity = new class extends Entity {
            public static function TableName(): string {
                return 'custom_table_name';
            }
        };

        $this->assertSame('custom_table_name', $customEntity::TableName());
    }

    #endregion TableName

    #region Metadata -----------------------------------------------------------

    function testMetadata()
    {
        $expected = [
            ['name' => 'id',                   'type' => 'INT',      'nullable' => false],
            ['name' => 'aBool',                'type' => 'BIT',      'nullable' => false],
            ['name' => 'aNullableBool',        'type' => 'BIT',      'nullable' => true],
            ['name' => 'anInt',                'type' => 'INT',      'nullable' => false],
            ['name' => 'aNullableInt',         'type' => 'INT',      'nullable' => true],
            ['name' => 'aFloat',               'type' => 'DOUBLE',   'nullable' => false],
            ['name' => 'aNullableFloat',       'type' => 'DOUBLE',   'nullable' => true],
            ['name' => 'aString',              'type' => 'TEXT',     'nullable' => false],
            ['name' => 'aNullableString',      'type' => 'TEXT',     'nullable' => true],
            ['name' => 'aDateTime',            'type' => 'DATETIME', 'nullable' => false],
            ['name' => 'aNullableDateTime',    'type' => 'DATETIME', 'nullable' => true],
            ['name' => 'anIntegerEnum',        'type' => 'INT',      'nullable' => false],
            ['name' => 'aNullableIntegerEnum', 'type' => 'INT',      'nullable' => true],
            ['name' => 'aStringEnum',          'type' => 'TEXT',     'nullable' => false],
            ['name' => 'aNullableStringEnum',  'type' => 'TEXT',     'nullable' => true],
        ];

        $actual = TEntity::Metadata();

        $this->assertSame($expected, $actual);
    }

    #endregion Metadata

    #region TableExists --------------------------------------------------------

    #[TestWith([false, null         ])] // failed
    #[TestWith([false, []           ])] // not found
    #[TestWith([true,  [['tentity']]])] // exists
    function testTableExists(bool $expected, ?array $result)
    {
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: "SHOW TABLES LIKE 'tentity'",
            result: $result,
            times: 1
        );

        $this->assertSame($expected, TEntity::TableExists());
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion TableExists

    #region CreateTable --------------------------------------------------------

    function testCreateTableFailsOnEmptyEntity()
    {
        $sut = new TEmptyEntity();

        $this->assertFalse($sut::CreateTable());
    }

    #[TestWith([true,  []])]
    #[TestWith([false, null])]
    function testCreateTableForViewEntity($expected, $result)
    {
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'CREATE OR REPLACE VIEW `tviewentity` AS SELECT 1',
            result: $result,
            times: 1
        );

        $actual = TViewEntity::CreateTable();

        $this->assertSame($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #[TestWith([true,  []])]
    #[TestWith([false, null])]
    function testCreateTableForRegularEntity($expected, $result)
    {
        $fakeDatabase = Database::Instance();

        $sql = 'CREATE TABLE `tentity` ('
             . '`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, '
             . '`aBool` BIT NOT NULL, '
             . '`aNullableBool` BIT NULL, '
             . '`anInt` INT NOT NULL, '
             . '`aNullableInt` INT NULL, '
             . '`aFloat` DOUBLE NOT NULL, '
             . '`aNullableFloat` DOUBLE NULL, '
             . '`aString` TEXT NOT NULL, '
             . '`aNullableString` TEXT NULL, '
             . '`aDateTime` DATETIME NOT NULL, '
             . '`aNullableDateTime` DATETIME NULL, '
             . '`anIntegerEnum` INT NOT NULL, '
             . '`aNullableIntegerEnum` INT NULL, '
             . '`aStringEnum` TEXT NOT NULL, '
             . '`aNullableStringEnum` TEXT NULL'
             . ') ENGINE=InnoDB';

        $fakeDatabase->Expect(
            sql: $sql,
            result: $result,
            times: 1
        );

        $actual = TEntity::CreateTable();

        $this->assertSame($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion CreateTable

    #region DropTable ----------------------------------------------------------

    #[TestWith([true,  []])]
    #[TestWith([false, null])]
    function testDropTableForViewEntity($expected, $result)
    {
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'DROP VIEW `tviewentity`',
            result: $result,
            times: 1
        );

        $actual = TViewEntity::DropTable();

        $this->assertSame($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #[TestWith([true,  []])]
    #[TestWith([false, null])]
    function testDropTableForRegularEntity($expected, $result)
    {
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'DROP TABLE `tentity`',
            result: $result,
            times: 1
        );

        $actual = TEntity::DropTable();

        $this->assertSame($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion DropTable

    #region FindById -----------------------------------------------------------

    #[TestWith([null])] // failed
    #[TestWith([[]])]   // not found
    function testFindByIdFails($result)
    {
        $id = 17;
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `tentity`'
               . ' WHERE `id` = :id LIMIT 1',
            bindings: ['id' => $id],
            result: $result,
            times: 1
        );

        $actual = TEntity::FindById($id);

        $this->assertNull($actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindByIdSucceeds()
    {
        $id = 17;
        $data = [
            'id'                   => $id,
            'aBool'                => 1,
            'aNullableBool'        => null,
            'anInt'                => 42,
            'aNullableInt'         => null,
            'aFloat'               => 3.14,
            'aNullableFloat'       => null,
            'aString'              => 'Hello, World!',
            'aNullableString'      => null,
            'aDateTime'            => '2026-04-17 10:00:00',
            'aNullableDateTime'    => null,
            'anIntegerEnum'        => 1,
            'aNullableIntegerEnum' => null,
            'aStringEnum'          => 'two',
            'aNullableStringEnum'  => null
        ];
        $expected = new TEntity($data);
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `tentity`'
               . ' WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 1],
            result: [$data],
            times: 1
        );

        $actual = TEntity::FindById(1);

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion FindById

    #region FindFirst ----------------------------------------------------------

    #[TestWith([null])] // failed
    #[TestWith([[]])]   // not found
    function testFindFirstFails($result)
    {
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `tentity`'
               . ' LIMIT 1',
            result: $result,
            times: 1
        );

        $actual = TEntity::FindFirst();

        $this->assertNull($actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindFirstSucceeds()
    {
        $data = [
            'id'                   => 17,
            'aBool'                => 1,
            'aNullableBool'        => null,
            'anInt'                => 42,
            'aNullableInt'         => null,
            'aFloat'               => 3.14,
            'aNullableFloat'       => null,
            'aString'              => 'Hello, World!',
            'aNullableString'      => null,
            'aDateTime'            => '2026-04-17 10:00:00',
            'aNullableDateTime'    => null,
            'anIntegerEnum'        => 1,
            'aNullableIntegerEnum' => null,
            'aStringEnum'          => 'two',
            'aNullableStringEnum'  => null
        ];
        $expected = new TEntity($data);
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `tentity`'
               . ' WHERE anInt > :anInt'
               . ' ORDER BY aString DESC'
               . ' LIMIT 1',
            bindings: ['anInt' => 29],
            result: [$data],
            times: 1
        );

        $actual = TEntity::FindFirst(
            condition: 'anInt > :anInt',
            bindings: ['anInt' => 29],
            orderBy: 'aString DESC'
        );

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion FindFirst

    #region Find ---------------------------------------------------------------

    #[TestWith([null])] // failed
    #[TestWith([[]])]   // empty
    function testFindFails($result)
    {
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `tentity`',
            result: $result,
            times: 1
        );

        $actual = TEntity::Find();

        $this->assertSame([], $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindSucceeds()
    {
        $data = [[
            'id'                   => 17,
            'aBool'                => 1,
            'aNullableBool'        => null,
            'anInt'                => 42,
            'aNullableInt'         => null,
            'aFloat'               => 3.14,
            'aNullableFloat'       => null,
            'aString'              => 'Hello, World!',
            'aNullableString'      => null,
            'aDateTime'            => '2026-04-17 10:00:00',
            'aNullableDateTime'    => null,
            'anIntegerEnum'        => 1,
            'aNullableIntegerEnum' => null,
            'aStringEnum'          => 'two',
            'aNullableStringEnum'  => null
        ], [
            'id'                   => 18,
            'aBool'                => 0,
            'aNullableBool'        => 1,
            'anInt'                => 42,
            'aNullableInt'         => 84,
            'aFloat'               => 3.14,
            'aNullableFloat'       => 6.28,
            'aString'              => 'Alice Doe',
            'aNullableString'      => 'Aziz Smith',
            'aDateTime'            => '2026-04-17',
            'aNullableDateTime'    => '2026-04-17 10:00:00',
            'anIntegerEnum'        => 1,
            'aNullableIntegerEnum' => 2,
            'aStringEnum'          => 'zero',
            'aNullableStringEnum'  => 'two'
        ]];
        $expected = [new TEntity($data[0]), new TEntity($data[1])];
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `tentity`'
               . ' WHERE aString LIKE :aString AND anInt >= :anInt'
               . ' ORDER BY aDateTime DESC'
               . ' LIMIT 10 OFFSET 5',
            bindings: ['aString' => 'A%', 'anInt' => 25],
            result: $data,
            times: 1
        );

        $actual = TEntity::Find(
            condition: 'aString LIKE :aString AND anInt >= :anInt',
            bindings: ['aString' => 'A%', 'anInt' => 25],
            orderBy: 'aDateTime DESC',
            limit: 10,
            offset: 5
        );

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion Find

    #region Count --------------------------------------------------------------

    #[TestWith([null])] // failed
    #[TestWith([[]])]   // empty
    #[TestWith([[[]]])] // count missing
    function testCountFails($result)
    {
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `tentity`',
            result: $result,
            times: 1
        );

        $actual = TEntity::Count();

        $this->assertSame(0, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testCountSucceedsWithDefaultParameters()
    {
        $expected = 123;
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `tentity`',
            result: [[$expected]],
            times: 1
        );

        $actual = TEntity::Count();

        $this->assertSame($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testCountSucceedsWithConditionAndBindings()
    {
        $expected = 7;
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `tentity`'
               . ' WHERE anInt > :anInt',
            bindings: ['anInt' => 30],
            result: [[$expected]],
            times: 1
        );

        $actual = TEntity::Count(
            condition: 'anInt > :anInt',
            bindings: ['anInt' => 30]
        );

        $this->assertSame($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion Count

    #region insert -------------------------------------------------------------

    function testInsertFailsOnEmptyEntity()
    {
        $sut = new TEmptyEntity();

        $this->assertFalse(ah::CallMethod($sut, 'insert'));
    }

    #[TestWith([false, null, 0])]
    #[TestWith([true,  [],   1])]
    function testInsert($expected, $result, $lastInsertId)
    {
        $sut = new TEntity([
            'aBool'         => true,
            'anInt'         => 42,
            'aFloat'        => 3.14,
            'aString'       => 'Hello, World!',
            'aDateTime'     => '2025-07-22 14:35:00',
            'anIntegerEnum' => TIntegerEnum::One,
            'aStringEnum'   => TStringEnum::Two
        ]);
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'INSERT INTO `tentity` ('
               . '`aBool`, `aNullableBool`, '
               . '`anInt`, `aNullableInt`, '
               . '`aFloat`, `aNullableFloat`, '
               . '`aString`, `aNullableString`, '
               . '`aDateTime`, `aNullableDateTime`, '
               . '`anIntegerEnum`, `aNullableIntegerEnum`, '
               . '`aStringEnum`, `aNullableStringEnum`'
               . ') VALUES ('
               . ':aBool, :aNullableBool, '
               . ':anInt, :aNullableInt, '
               . ':aFloat, :aNullableFloat, '
               . ':aString, :aNullableString, '
               . ':aDateTime, :aNullableDateTime, '
               . ':anIntegerEnum, :aNullableIntegerEnum, '
               . ':aStringEnum, :aNullableStringEnum'
               . ')',
            bindings: [
                'aBool'                => true,
                'aNullableBool'        => null,
                'anInt'                => 42,
                'aNullableInt'         => null,
                'aFloat'               => 3.14,
                'aNullableFloat'       => null,
                'aString'              => 'Hello, World!',
                'aNullableString'      => null,
                'aDateTime'            => '2025-07-22 14:35:00',
                'aNullableDateTime'    => null,
                'anIntegerEnum'        => TIntegerEnum::One->value,
                'aNullableIntegerEnum' => null,
                'aStringEnum'          => TStringEnum::Two->value,
                'aNullableStringEnum'  => null
            ],
            result: $result,
            lastInsertId: $lastInsertId,
            times: 1
        );

        $actual = ah::CallMethod($sut, 'insert');

        $this->assertSame($expected, $actual);
        $this->assertSame($lastInsertId, $sut->id);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion insert

    #region update -------------------------------------------------------------

    function testUpdateFailsOnEmptyEntity()
    {
        $sut = new TEmptyEntity();

        $this->assertFalse(ah::CallMethod($sut, 'update'));
    }

    #[TestWith([false, null, 0])]
    #[TestWith([false, [],  -1])]
    #[TestWith([true,  [],   0])]
    #[TestWith([true,  [],   1])]
    function testUpdate($expected, $result, $lastAffectedRowCount)
    {
        $sut = new TEntity([
            'id'            => 23,
            'aBool'         => true,
            'anInt'         => 42,
            'aFloat'        => 3.14,
            'aString'       => 'Hello, World!',
            'aDateTime'     => '2025-07-22 14:35:00',
            'anIntegerEnum' => TIntegerEnum::One,
            'aStringEnum'   => TStringEnum::Two
        ]);
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'UPDATE `tentity` SET '
               . '`aBool` = :aBool, `aNullableBool` = :aNullableBool, '
               . '`anInt` = :anInt, `aNullableInt` = :aNullableInt, '
               . '`aFloat` = :aFloat, `aNullableFloat` = :aNullableFloat, '
               . '`aString` = :aString, `aNullableString` = :aNullableString, '
               . '`aDateTime` = :aDateTime, `aNullableDateTime` = :aNullableDateTime, '
               . '`anIntegerEnum` = :anIntegerEnum, `aNullableIntegerEnum` = :aNullableIntegerEnum, '
               . '`aStringEnum` = :aStringEnum, `aNullableStringEnum` = :aNullableStringEnum '
               . 'WHERE `id` = :id',
            bindings: [
                'id'                   => 23,
                'aBool'                => true,
                'aNullableBool'        => null,
                'anInt'                => 42,
                'aNullableInt'         => null,
                'aFloat'               => 3.14,
                'aNullableFloat'       => null,
                'aString'              => 'Hello, World!',
                'aNullableString'      => null,
                'aDateTime'            => '2025-07-22 14:35:00',
                'aNullableDateTime'    => null,
                'anIntegerEnum'        => TIntegerEnum::One->value,
                'aNullableIntegerEnum' => null,
                'aStringEnum'          => TStringEnum::Two->value,
                'aNullableStringEnum'  => null
            ],
            result: $result,
            lastAffectedRowCount: $lastAffectedRowCount,
            times: 1
        );

        $actual = ah::CallMethod($sut, 'update');

        $this->assertSame($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion update

    #region properties ---------------------------------------------------------

    function testPropertiesWithNoProperties()
    {
        $sut = new class extends Entity {
            // No properties
        };

        $this->assertProperties($sut, []);
    }

    function testPropertiesSkipsNonPublicProperties()
    {
        $sut = new class extends Entity {
            protected int $aProtected;
            private int   $aPrivate;
        };

        $this->assertProperties($sut, []);
    }

    function testPropertiesSkipsStaticProperties()
    {
        $sut = new class extends Entity {
            public static int $aStatic;
        };

        $this->assertProperties($sut, []);
    }

    function testPropertiesSkipsReadOnlyProperties()
    {
        $sut = new class extends Entity {
            public readonly int $aReadOnly;
        };

        $this->assertProperties($sut, []);
    }

    function testPropertiesSkipsUntypedProperties()
    {
        $sut = new class extends Entity {
            public $anUntyped;
        };

        $this->assertProperties($sut, []);
    }

    function testPropertiesSkipsUnionTypeProperties()
    {
        $sut = new class extends Entity {
            public int|string $aUnion;
        };

        $this->assertProperties($sut, []);
    }

    function testPropertiesSkipsIntersectionTypeProperties()
    {
        $sut = new class extends Entity {
            public \Iterator&\Countable $anIntersection;
        };

        $this->assertProperties($sut, []);
    }

    function testPropertiesSkipsUnsupportedTypes()
    {
        $sut = new class extends Entity {
            public array              $anArray;
            public \stdClass          $anObject;
            public iterable           $anIterable;
            public \DateTimeImmutable $aDateTimeImmutable;
        };

        $this->assertProperties($sut, []);
    }

    function testPropertiesSkipsPureEnumProperties()
    {
        $sut = new class extends Entity {
            public TPureEnum $aPureEnum;
        };

        $this->assertProperties($sut, []);
    }

    function testPropertiesSkipsEmptyBackedEnumProperties()
    {
        $sut = new class extends Entity {
            public TEmptyIntegerEnum $anEmptyIntegerEnum;
            public TEmptyStringEnum  $anEmptyStringEnum;
        };

        $this->assertProperties($sut, []);
    }

    function testPropertiesHandlesNullableSupportedProperties()
    {
        $sut = new class extends Entity {
            public ?bool         $aNullableBool;
            public ?int          $aNullableInt;
            public ?float        $aNullableFloat;
            public ?string       $aNullableString;
            public ?\DateTime    $aNullableDateTime;
            public ?TIntegerEnum $aNullableIntegerEnum;
            public ?TStringEnum  $aNullableStringEnum;
        };

        $this->assertProperties($sut, [
            'aNullableBool'        => ['type' => 'bool', 'nullable' => true],
            'aNullableInt'         => ['type' => 'int', 'nullable' => true],
            'aNullableFloat'       => ['type' => 'float', 'nullable' => true],
            'aNullableString'      => ['type' => 'string', 'nullable' => true],
            'aNullableDateTime'    => ['type' => 'DateTime', 'nullable' => true],
            'aNullableIntegerEnum' => ['type' => TIntegerEnum::class, 'nullable' => true],
            'aNullableStringEnum'  => ['type' => TStringEnum::class, 'nullable' => true],
        ]);
        $this->assertNull($sut->aNullableBool);
        $this->assertNull($sut->aNullableInt);
        $this->assertNull($sut->aNullableFloat);
        $this->assertNull($sut->aNullableString);
        $this->assertNull($sut->aNullableDateTime);
        $this->assertNull($sut->aNullableIntegerEnum);
        $this->assertNull($sut->aNullableStringEnum);
    }

    function testPropertiesHandlesSupportedProperties()
    {
        $sut = new class extends Entity {
            public bool         $aBool;
            public int          $anInt;
            public float        $aFloat;
            public string       $aString;
            public \DateTime    $aDateTime;
            public TIntegerEnum $anIntegerEnum;
            public TStringEnum  $aStringEnum;
        };

        $this->assertProperties($sut, [
            'aBool'         => ['type' => 'bool', 'nullable' => false],
            'anInt'         => ['type' => 'int', 'nullable' => false],
            'aFloat'        => ['type' => 'float', 'nullable' => false],
            'aString'       => ['type' => 'string', 'nullable' => false],
            'aDateTime'     => ['type' => 'DateTime', 'nullable' => false],
            'anIntegerEnum' => ['type' => TIntegerEnum::class, 'nullable' => false],
            'aStringEnum'   => ['type' => TStringEnum::class, 'nullable' => false],
        ]);
        $this->assertFalse($sut->aBool);
        $this->assertSame(0, $sut->anInt);
        $this->assertSame(0.0, $sut->aFloat);
        $this->assertSame('', $sut->aString);
        $this->assertInstanceOf(\DateTime::class, $sut->aDateTime);
        $this->assertEqualsWithDelta(\time(), $sut->aDateTime->getTimestamp(), 1);
        $this->assertSame(TIntegerEnum::Zero, $sut->anIntegerEnum);
        $this->assertSame(TStringEnum::Zero, $sut->aStringEnum);
    }

    function testPropertiesDoesNotOverwriteAlreadyInitializedProperties()
    {
        $sut = new class extends Entity {
            public bool         $aBool = true;
            public int          $anInt = 1;
            public float        $aFloat = 1.1;
            public string       $aString = 'keep-me';
            public \DateTime    $aDateTime;
            public TIntegerEnum $anIntegerEnum = TIntegerEnum::One;
            public TStringEnum  $aStringEnum = TStringEnum::Two;

            public function __construct() {
                $this->aDateTime = new \DateTime('2020-01-01 10:00:00');
            }
        };

        $this->assertProperties($sut, [
            'aBool'         => ['type' => 'bool', 'nullable' => false],
            'anInt'         => ['type' => 'int', 'nullable' => false],
            'aFloat'        => ['type' => 'float', 'nullable' => false],
            'aString'       => ['type' => 'string', 'nullable' => false],
            'aDateTime'     => ['type' => 'DateTime', 'nullable' => false],
            'anIntegerEnum' => ['type' => TIntegerEnum::class, 'nullable' => false],
            'aStringEnum'   => ['type' => TStringEnum::class, 'nullable' => false],
        ]);
        $this->assertTrue($sut->aBool);
        $this->assertSame(1, $sut->anInt);
        $this->assertSame(1.1, $sut->aFloat);
        $this->assertSame('keep-me', $sut->aString);
        $this->assertSame('2020-01-01 10:00:00', $sut->aDateTime->format('Y-m-d H:i:s'));
        $this->assertSame(TIntegerEnum::One, $sut->anIntegerEnum);
        $this->assertSame(TStringEnum::Two, $sut->aStringEnum);
    }

    #endregion properties

    #region isSupportedPropertyType --------------------------------------------

    #[TestWith(['bool'])]
    #[TestWith(['int'])]
    #[TestWith(['float'])]
    #[TestWith(['string'])]
    #[TestWith(['DateTime'])]
    #[TestWith([TIntegerEnum::class])]
    #[TestWith([TStringEnum::class])]
    function testIsSupportedPropertyTypeReturnsTrue(string $type)
    {
        $actual = ah::CallStaticMethod(
            Entity::class,
            'isSupportedPropertyType',
            [$type]
        );

        $this->assertTrue($actual);
    }

    #[TestWith(['array'])]
    #[TestWith(['object'])]
    #[TestWith(['iterable'])]
    #[TestWith([\DateTimeImmutable::class])]
    #[TestWith(['NonExistentClass'])]
    #[TestWith([TPureEnum::class])]
    #[TestWith([TEmptyIntegerEnum::class])]
    #[TestWith([TEmptyStringEnum::class])]
    function testIsSupportedPropertyTypeReturnsFalse(string $type)
    {
        $actual = ah::CallStaticMethod(
            Entity::class,
            'isSupportedPropertyType',
            [$type]
        );

        $this->assertFalse($actual);
    }

    #endregion isSupportedPropertyType

    #region defaultValueForSupportedPropertyType -------------------------------

    #[TestWith([false, 'bool'])]
    #[TestWith([0, 'int'])]
    #[TestWith([0.0, 'float'])]
    #[TestWith(['', 'string'])]
    #[TestWith([null, 'DateTime'])] // null is a placeholder; verified specifically
    #[TestWith([TIntegerEnum::Zero, TIntegerEnum::class])]
    #[TestWith([TStringEnum::Zero, TStringEnum::class])]
    function testDefaultValueForSupportedPropertyType(mixed $expected, string $type)
    {
        $actual = ah::CallStaticMethod(
            Entity::class,
            'defaultValueForSupportedPropertyType',
            [$type]
        );

        if ($type === 'DateTime') {
            $this->assertInstanceOf(\DateTime::class, $actual);
            $this->assertEqualsWithDelta(\time(), $actual->getTimestamp(), 1);
        } else {
            $this->assertSame($expected, $actual);
        }
    }

    #endregion defaultValueForSupportedPropertyType

    #region scalarize ----------------------------------------------------------

    #[TestWith([true, true])]
    #[TestWith([false, false])]
    #[TestWith([12345, 12345])]
    #[TestWith([123.45, 123.45])]
    #[TestWith(['a-string', 'a-string'])]
    function testScalarize(mixed $expected, mixed $value)
    {
        $actual = ah::CallStaticMethod(
            Entity::class,
            'scalarize',
            [$value]
        );

        $this->assertSame($expected, $actual);
    }

    function testScalarizeWithDateTime()
    {
        $value = new \DateTime();
        $expected = $value->format('Y-m-d H:i:s');

        $actual = ah::CallStaticMethod(
            Entity::class,
            'scalarize',
            [$value]
        );

        $this->assertSame($expected, $actual);
    }

    #[TestWith([0, TIntegerEnum::Zero])]
    #[TestWith([1, TIntegerEnum::One])]
    #[TestWith([2, TIntegerEnum::Two])]
    #[TestWith(['zero', TStringEnum::Zero])]
    #[TestWith(['one', TStringEnum::One])]
    #[TestWith(['two', TStringEnum::Two])]
    function testScalarizeWithBackedEnum(mixed $expected, mixed $value)
    {
        $actual = ah::CallStaticMethod(
            Entity::class,
            'scalarize',
            [$value]
        );

        $this->assertSame($expected, $actual);
    }

    #endregion scalarize

    #region backingTypeForEnum -------------------------------------------------

    #[TestWith(['NonExistentClass'])]
    #[TestWith([\stdClass::class])]
    #[TestWith([TPureEnum::class])]
    function testBackingTypeForEnumFails(string $class)
    {
        $this->expectException(\ReflectionException::class);

        ah::CallStaticMethod(
            Entity::class,
            'backingTypeForEnum',
            [$class]
        );
    }

    #[TestWith(['int', TIntegerEnum::class])]
    #[TestWith(['string', TStringEnum::class])]
    function testBackingTypeForEnumSucceeds(string $expected, string $class)
    {
        $actual = ah::CallStaticMethod(
            Entity::class,
            'backingTypeForEnum',
            [$class]
        );

        $this->assertSame($expected, $actual);
    }

    #endregion backingTypeForEnum
}
