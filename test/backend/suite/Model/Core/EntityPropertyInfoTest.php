<?php declare(strict_types=1);
namespace suite\Model\Core;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Model\Core\EntityPropertyInfo;

use \Peneus\Model\Core\EntityPropertyType;
use \TestToolkit\AccessHelper as ah;

enum TPureEnum { case Zero; case One; case Two; }
enum TEmptyIntegerEnum: int {}
enum TEmptyStringEnum: string {}
enum TIntegerEnum: int { case Zero = 0; case One = 1; case Two = 2; }
enum TStringEnum: string { case Zero = 'zero'; case One = 'one'; case Two = 'two'; }

#[CoversClass(EntityPropertyInfo::class)]
class EntityPropertyInfoTest extends TestCase
{
    #region From ---------------------------------------------------------------

    #[TestWith([\ReflectionUnionType::class])]
    #[TestWith([\ReflectionIntersectionType::class])]
    function testFromFailsIfReflectionTypeIsNotNamed(string $reflectionTypeClass)
    {
        $reflectionType = $this->createMock($reflectionTypeClass);
        $sut = EntityPropertyInfo::From($reflectionType);
        $this->assertNull($sut);
    }

    #[TestWith(['array'])]
    #[TestWith(['object'])]
    #[TestWith(['iterable'])]
    #[TestWith([\DateTimeImmutable::class])]
    #[TestWith([TPureEnum::class])]
    #[TestWith([TEmptyIntegerEnum::class])]
    #[TestWith([TEmptyStringEnum::class])]
    function testFromFails(string $class)
    {
        $reflectionType = $this->createMock(\ReflectionNamedType::class);
        $reflectionType->expects($this->once())
            ->method('getName')
            ->willReturn($class);
        $sut = EntityPropertyInfo::From($reflectionType);
        $this->assertNull($sut);
    }

    #[TestWith([EntityPropertyType::Boolean,     'bool'             ])]
    #[TestWith([EntityPropertyType::Integer,     'int'              ])]
    #[TestWith([EntityPropertyType::Float,       'float'            ])]
    #[TestWith([EntityPropertyType::String,      'string'           ])]
    #[TestWith([EntityPropertyType::DateTime,    'DateTime'         ])]
    #[TestWith([EntityPropertyType::Enumeration, TIntegerEnum::class])]
    #[TestWith([EntityPropertyType::Enumeration, TStringEnum::class ])]
    function testFromSucceeds(EntityPropertyType $type, string $class)
    {
        $reflectionType = $this->createMock(\ReflectionNamedType::class);
        $reflectionType->expects($this->once())
            ->method('getName')
            ->willReturn($class);
        $reflectionType->expects($this->once())
            ->method('allowsNull')
            ->willReturn(false);
        $sut = EntityPropertyInfo::From($reflectionType);
        $this->assertInstanceOf(EntityPropertyInfo::class, $sut);
        $this->assertSame($type, ah::GetProperty($sut, 'type'));
        $this->assertSame($class, ah::GetProperty($sut, 'class'));
        $this->assertSame(false, ah::GetProperty($sut, 'isNullable'));
    }

    #endregion From

    #region Type ---------------------------------------------------------------

    #[TestWith([EntityPropertyType::Boolean   ])]
    #[TestWith([EntityPropertyType::Integer   ])]
    #[TestWith([EntityPropertyType::Float     ])]
    #[TestWith([EntityPropertyType::String    ])]
    #[TestWith([EntityPropertyType::DateTime  ])]
    #[TestWith([EntityPropertyType::Enumeration])]
    function testType(EntityPropertyType $expected)
    {
        $sut = ah::CallConstructor(EntityPropertyInfo::class, [
            $expected,
            '',    // unused
            false  // unused
        ]);
        $actual = $sut->Type();
        $this->assertSame($expected, $actual);
    }

    #endregion Type

    #region Class --------------------------------------------------------------

    #[TestWith(['bool',              EntityPropertyType::Boolean    ])]
    #[TestWith(['int',               EntityPropertyType::Integer    ])]
    #[TestWith(['float',             EntityPropertyType::Float      ])]
    #[TestWith(['string',            EntityPropertyType::String     ])]
    #[TestWith([\DateTime::class,    EntityPropertyType::DateTime   ])]
    #[TestWith([TIntegerEnum::class, EntityPropertyType::Enumeration])]
    #[TestWith([TStringEnum::class,  EntityPropertyType::Enumeration])]
    function testClass(string $expected, EntityPropertyType $type)
    {
        $sut = ah::CallConstructor(EntityPropertyInfo::class, [
            $type,
            $expected,
            false // unused
        ]);
        $actual = $sut->Class();
        $this->assertSame($expected, $actual);
    }

    #endregion Class

    #region IsNullable ---------------------------------------------------------

    #[TestWith([true ])]
    #[TestWith([false])]
    function testIsNullable(bool $expected)
    {
        $sut = ah::CallConstructor(EntityPropertyInfo::class, [
            EntityPropertyType::Boolean, // unused
            '',
            $expected
        ]);
        $actual = $sut->IsNullable();
        $this->assertSame($expected, $actual);
    }

    #endregion IsNullable

    #region DefaultValue -------------------------------------------------------

    #[TestWith([false, EntityPropertyType::Boolean])]
    #[TestWith([0,     EntityPropertyType::Integer])]
    #[TestWith([0.0,   EntityPropertyType::Float  ])]
    #[TestWith(['',    EntityPropertyType::String ])]
    function testDefaultValueForScalar(mixed $expected, EntityPropertyType $type)
    {
        $sut = ah::CallConstructor(EntityPropertyInfo::class, [
            $type,
            '',   // unused
            false // unused
        ]);
        $actual = $sut->DefaultValue();
        $this->assertSame($expected, $actual);
    }

    function testDefaultValueForDateTime()
    {
        $sut = ah::CallConstructor(EntityPropertyInfo::class, [
            EntityPropertyType::DateTime,
            \DateTime::class,
            false // unused
        ]);
        $actual = $sut->DefaultValue();
        $this->assertInstanceOf(\DateTime::class, $actual);
        $this->assertEqualsWithDelta(\time(), $actual->getTimestamp(), 1);
    }

    #[TestWith([TIntegerEnum::Zero, TIntegerEnum::class])]
    #[TestWith([TStringEnum::Zero,  TStringEnum::class ])]
    function testDefaultValueForEnumeration(mixed $expected, string $class)
    {
        $sut = ah::CallConstructor(EntityPropertyInfo::class, [
            EntityPropertyType::Enumeration,
            $class,
            false // unused
        ]);
        $actual = $sut->DefaultValue();
        $this->assertSame($expected, $actual);
    }

    #endregion DefaultValue

    #region EnumBackingType ----------------------------------------------------

    #[TestWith([EntityPropertyType::Boolean ])]
    #[TestWith([EntityPropertyType::Integer ])]
    #[TestWith([EntityPropertyType::Float   ])]
    #[TestWith([EntityPropertyType::String  ])]
    #[TestWith([EntityPropertyType::DateTime])]
    function testEnumBackingTypeFails(EntityPropertyType $type)
    {
        $sut = ah::CallConstructor(EntityPropertyInfo::class, [
            $type,
            '',   // unused
            false // unused
        ]);
        $actual = $sut->EnumBackingType();
        $this->assertNull($actual);
    }

    #[TestWith(['int',    EntityPropertyType::Enumeration, TIntegerEnum::class])]
    #[TestWith(['string', EntityPropertyType::Enumeration, TStringEnum::class ])]
    function testEnumBackingTypeSucceeds(
        string $expected,
        EntityPropertyType $type,
        string $class
    ) {
        $sut = ah::CallConstructor(EntityPropertyInfo::class, [
            $type,
            $class,
            false // unused
        ]);
        $actual = $sut->EnumBackingType();
        $this->assertSame($expected, $actual);
    }

    #endregion EnumBackingType
}
