<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Systems\PageSystem\LibraryItem;

use \TestToolkit\DataHelper;

#[CoversClass(LibraryItem::class)]
class LibraryItemTest extends TestCase
{
    #region Css ----------------------------------------------------------------

    function testCssAcceptsNull()
    {
        $sut = new LibraryItem(null, null, false);
        $this->assertSame([], $sut->Css());
    }

    function testCssAcceptsString()
    {
        $sut = new LibraryItem('foo/bar', null, false);
        $this->assertSame(['foo/bar'], $sut->Css());
    }

    function testCssAcceptsArray()
    {
        $paths = ['foo/bar', 'foo/baz'];
        $sut = new LibraryItem($paths, null, false);
        $this->assertSame($paths, $sut->Css());
    }

    #endregion Css

    #region Js -----------------------------------------------------------------

    function testJsAcceptsNull()
    {
        $sut = new LibraryItem(null, null, false);
        $this->assertSame([], $sut->Js());
    }

    function testJsAcceptsString()
    {
        $sut = new LibraryItem(null, 'foo/bar', false);
        $this->assertSame(['foo/bar'], $sut->Js());
    }

    function testJsAcceptsArray()
    {
        $paths = ['foo/bar', 'foo/baz'];
        $sut = new LibraryItem(null, $paths, false);
        $this->assertSame($paths, $sut->Js());
    }

    #endregion Js

    #region IsDefault ----------------------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testIsDefaultFlag($isDefault)
    {
        $sut = new LibraryItem(null, null, $isDefault);
        $this->assertSame($isDefault, $sut->IsDefault());
    }

    #endregion IsDefault
}
