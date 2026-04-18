<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Model\Role;

#[CoversClass(Role::class)]
class RoleTest extends TestCase
{
    #region AtLeast ------------------------------------------------------------

    #[DataProvider('atLeastDataProvider')]
    function testAtLeast(bool $expected, Role $current, Role $minimum)
    {
        $this->assertSame($expected, $current->AtLeast($minimum));
    }

    #endregion AtLeast

    #region Data Providers -----------------------------------------------------

    static function atLeastDataProvider()
    {
        return [
            'None vs None'     => [true,  Role::None,   Role::None],
            'None vs Editor'   => [false, Role::None,   Role::Editor],
            'None vs Admin'    => [false, Role::None,   Role::Admin],
            'Editor vs None'   => [true,  Role::Editor, Role::None],
            'Editor vs Editor' => [true,  Role::Editor, Role::Editor],
            'Editor vs Admin'  => [false, Role::Editor, Role::Admin],
            'Admin vs None'    => [true,  Role::Admin,  Role::None],
            'Admin vs Editor'  => [true,  Role::Admin,  Role::Editor],
            'Admin vs Admin'   => [true,  Role::Admin,  Role::Admin],
        ];
    }

    #endregion Data Providers
}
