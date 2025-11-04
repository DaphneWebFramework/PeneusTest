<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Model\Role;

#[CoversClass(Role::class)]
class RoleTest extends TestCase
{
    #region Parse --------------------------------------------------------------

    function testParseReturnsNoneIfValueIsNull()
    {
        $this->assertSame(Role::None, Role::Parse(null));
    }

    function testParseReturnsNoneIfValueIsNotAnEnumValue()
    {
        $this->assertSame(Role::None, Role::Parse(-1));
        $this->assertSame(Role::None, Role::Parse(99));
    }

    function testParseReturnsNoneIfValueIsZero()
    {
        $this->assertSame(Role::None, Role::Parse(0));
    }

    function testParseReturnsEditorIfValueIsTen()
    {
        $this->assertSame(Role::Editor, Role::Parse(10));
    }

    function testParseReturnsAdminIfValueIsTwenty()
    {
        $this->assertSame(Role::Admin, Role::Parse(20));
    }

    #endregion Parse

    #region AtLeast ------------------------------------------------------------

    function testAtLeastReturnsTrueIfSelfIsNoneAndMinimumIsNone()
    {
        $this->assertTrue(Role::None->AtLeast(Role::None));
    }

    function testAtLeastReturnsFalseIfSelfIsNoneAndMinimumIsEditor()
    {
        $this->assertFalse(Role::None->AtLeast(Role::Editor));
    }

    function testAtLeastReturnsFalseIfSelfIsNoneAndMinimumIsAdmin()
    {
        $this->assertFalse(Role::None->AtLeast(Role::Admin));
    }

    function testAtLeastReturnsTrueIfSelfIsEditorAndMinimumIsNone()
    {
        $this->assertTrue(Role::Editor->AtLeast(Role::None));
    }

    function testAtLeastReturnsTrueIfSelfIsEditorAndMinimumIsEditor()
    {
        $this->assertTrue(Role::Editor->AtLeast(Role::Editor));
    }

    function testAtLeastReturnsFalseIfSelfIsEditorAndMinimumIsAdmin()
    {
        $this->assertFalse(Role::Editor->AtLeast(Role::Admin));
    }

    function testAtLeastReturnsTrueIfSelfIsAdminAndMinimumIsNone()
    {
        $this->assertTrue(Role::Admin->AtLeast(Role::None));
    }

    function testAtLeastReturnsTrueIfSelfIsAdminAndMinimumIsEditor()
    {
        $this->assertTrue(Role::Admin->AtLeast(Role::Editor));
    }

    function testAtLeastReturnsTrueIfSelfIsAdminAndMinimumIsAdmin()
    {
        $this->assertTrue(Role::Admin->AtLeast(Role::Admin));
    }

    #endregion AtLeast
}
