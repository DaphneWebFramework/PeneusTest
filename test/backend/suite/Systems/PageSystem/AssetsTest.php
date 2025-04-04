<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\PageSystem\Assets;

#[CoversClass(Assets::class)]
class AssetsTest extends TestCase
{
    #region Css ----------------------------------------------------------------

    function testCssAcceptsNull()
    {
        $sut = new Assets(null, null, null);
        $this->assertSame([], $sut->Css());
    }

    function testCssAcceptsString()
    {
        $sut = new Assets('foo/bar', null, null);
        $this->assertSame(['foo/bar'], $sut->Css());
    }

    function testCssAcceptsArray()
    {
        $paths = ['foo/bar', 'foo/baz'];
        $sut = new Assets($paths, null, null);
        $this->assertSame($paths, $sut->Css());
    }

    #endregion Css

    #region Js -----------------------------------------------------------------

    function testJsAcceptsNull()
    {
        $sut = new Assets(null, null, null);
        $this->assertSame([], $sut->Js());
    }

    function testJsAcceptsString()
    {
        $sut = new Assets(null, 'foo/bar', null);
        $this->assertSame(['foo/bar'], $sut->Js());
    }

    function testJsAcceptsArray()
    {
        $paths = ['foo/bar', 'foo/baz'];
        $sut = new Assets(null, $paths, null);
        $this->assertSame($paths, $sut->Js());
    }

    #endregion Js

    #region Extras -------------------------------------------------------------

    function testExtrasAcceptsNull()
    {
        $sut = new Assets(null, null, null);
        $this->assertSame([], $sut->Extras());
    }

    function testExtrasAcceptsString()
    {
        $sut = new Assets(null, null, 'foo/bar.map');
        $this->assertSame(['foo/bar.map'], $sut->Extras());
    }

    function testExtrasAcceptsArray()
    {
        $paths = ['foo/a.txt', 'foo/b.png'];
        $sut = new Assets(null, null, $paths);
        $this->assertSame($paths, $sut->Extras());
    }

    #endregion Extras
}
