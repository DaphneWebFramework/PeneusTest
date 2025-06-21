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
        $sut = new Assets(null);
        $this->assertSame([], $sut->Css());
    }

    function testCssAcceptsString()
    {
        $path = 'foo/bar';
        $sut = new Assets($path);
        $this->assertSame([$path], $sut->Css());
    }

    function testCssAcceptsArray()
    {
        $paths = ['foo/bar', 'foo/baz'];
        $sut = new Assets($paths);
        $this->assertSame($paths, $sut->Css());
    }

    #endregion Css

    #region Js -----------------------------------------------------------------

    function testJsAcceptsNull()
    {
        $sut = new Assets(null, null);
        $this->assertSame([], $sut->Js());
    }

    function testJsAcceptsString()
    {
        $path = 'foo/bar';
        $sut = new Assets(null, $path);
        $this->assertSame([$path], $sut->Js());
    }

    function testJsAcceptsArray()
    {
        $paths = ['foo/bar', 'foo/baz'];
        $sut = new Assets(null, $paths);
        $this->assertSame($paths, $sut->Js());
    }

    #endregion Js
}
