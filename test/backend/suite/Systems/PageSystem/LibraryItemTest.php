<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Systems\PageSystem\LibraryItem;

use \TestToolkit\DataHelper;

#[CoversClass(LibraryItem::class)]
class LibraryItemTest extends TestCase
{
    #region Name ---------------------------------------------------------------

    function testNamePropertyIsStoredCorrectly()
    {
        $sut = new LibraryItem('foo', null, null, null, false);
        $this->assertSame('foo', $sut->Name());
    }

    #endregion Name

    #region Css ----------------------------------------------------------------

    function testCssAcceptsNull()
    {
        $sut = new LibraryItem('foo', null, null, null, false);
        $this->assertSame([], $sut->Css());
    }

    function testCssAcceptsString()
    {
        $sut = new LibraryItem('foo', 'foo/bar', null, null, false);
        $this->assertSame(['foo/bar'], $sut->Css());
    }

    function testCssAcceptsArray()
    {
        $paths = ['foo/bar', 'foo/baz'];
        $sut = new LibraryItem('foo', $paths, null, null, false);
        $this->assertSame($paths, $sut->Css());
    }

    #endregion Css

    #region Js -----------------------------------------------------------------

    function testJsAcceptsNull()
    {
        $sut = new LibraryItem('foo', null, null, null, false);
        $this->assertSame([], $sut->Js());
    }

    function testJsAcceptsString()
    {
        $sut = new LibraryItem('foo', null, 'foo/bar', null, false);
        $this->assertSame(['foo/bar'], $sut->Js());
    }

    function testJsAcceptsArray()
    {
        $paths = ['foo/bar', 'foo/baz'];
        $sut = new LibraryItem('foo', null, $paths, null, false);
        $this->assertSame($paths, $sut->Js());
    }

    #endregion Js

    #region Extras -------------------------------------------------------------

    function testExtrasAcceptsNull()
    {
        $sut = new LibraryItem('foo', null, null, null, false);
        $this->assertSame([], $sut->Extras());
    }

    function testExtrasAcceptsString()
    {
        $sut = new LibraryItem('foo', null, null, 'foo/bar.map', false);
        $this->assertSame(['foo/bar.map'], $sut->Extras());
    }

    function testExtrasAcceptsArray()
    {
        $paths = ['foo/a.txt', 'foo/b.png'];
        $sut = new LibraryItem('foo', null, null, $paths, false);
        $this->assertSame($paths, $sut->Extras());
    }

    #endregion Extras

    #region IsDefault ----------------------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testIsDefaultFlag($isDefault)
    {
        $sut = new LibraryItem('foo', null, null, null, $isDefault);
        $this->assertSame($isDefault, $sut->IsDefault());
    }

    #endregion IsDefault
}
