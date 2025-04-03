<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Systems\PageSystem\LibraryManifest;

use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Core\CSequentialArray;
use \Peneus\Resource;
use \Peneus\Systems\PageSystem\LibraryItem;
use \TestToolkit\AccessHelper;

#[CoversClass(LibraryManifest::class)]
class LibraryManifestTest extends TestCase
{
    private ?Resource $originalResource = null;

    protected function setUp(): void
    {
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
    }

    protected function tearDown(): void
    {
        Resource::ReplaceInstance($this->originalResource);
    }

    private function systemUnderTest(string ...$mockedMethods): LibraryManifest
    {
        return $this->getMockBuilder(LibraryManifest::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region __construct --------------------------------------------------------

    function testConstructorThrowsWhenFileCannotBeOpened()
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();

        $resource->expects($this->once())
            ->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Manifest file could not be opened.');
        $sut->__construct();
    }

    function testConstructorThrowsWhenFileCannotBeRead()
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(null);
        $file->expects($this->once())
            ->method('Close');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Manifest file could not be read.');
        $sut->__construct();
    }

    function testConstructorThrowsWhenJsonIsInvalid()
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn('{invalid'); // malformed JSON
        $file->expects($this->once())
            ->method('Close');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Manifest file contains invalid JSON.');
        $sut->__construct();
    }

    function testConstructorThrowsWhenLibraryNameIsNotString()
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<JSON
                [
                  {
                    "css": "foo.css",
                    "js": "foo.js"
                  }
                ]
            JSON);
        $file->expects($this->once())
            ->method('Close');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Library name must be a string.');
        $sut->__construct();
    }

    function testConstructorThrowsWhenLibraryNameIsEmpty()
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<JSON
                {
                  "": {
                    "css": "foo.css",
                    "js": "foo.js"
                  }
                }
            JSON);
        $file->expects($this->once())
            ->method('Close');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Library name cannot be empty.');
        $sut->__construct();
    }

    function testConstructorThrowsWhenLibraryDataIsNotArray()
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<JSON
                {
                  "foo": "this should be an object"
                }
            JSON);
        $file->expects($this->once())
            ->method('Close');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Library data must be an object.');
        $sut->__construct();
    }

    #[DataProvider('invalidAssetValueDataProvider')]
    function testConstructorThrowsOnInvalidCssValues(string $jsonValue)
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<JSON
                {
                  "lib": {
                    "css": $jsonValue
                  }
                }
            JSON);
        $file->expects($this->once())
            ->method('Close');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/^Library asset value must be a string( or an array of strings)?\.$/');
        $sut->__construct();
    }

    function testConstructorLoadsLibraryItemsFromManifest()
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<JSON
                {
                  "jquery": {
                    "default": true,
                    "css": "jquery-ui-1.12.1.custom/jquery-ui",
                    "js": [
                      "jquery-3.5.1/jquery",
                      "jquery-ui-1.12.1.custom/jquery-ui"
                    ]
                  },
                  "selectize": {
                    "css": "selectize-0.13.6/css/selectize.bootstrap4.css",
                    "js": "selectize-0.13.6/js/standalone/selectize"
                  },
                  "audiojs": {
                    "css": "audiojs-1.0.1/audio",
                    "js": "audiojs-1.0.1/audio",
                    "*": "audiojs-1.0.1/player-graphics.gif"
                  }
                }
            JSON);
        $file->expects($this->once())
            ->method('Close');

        $sut->__construct();
        $items = $sut->Items();

        $item = $items->Get('jquery');
        $this->assertInstanceOf(LibraryItem::class, $item);
        $this->assertSame(['jquery-ui-1.12.1.custom/jquery-ui'], $item->Css());
        $this->assertSame([
            'jquery-3.5.1/jquery',
            'jquery-ui-1.12.1.custom/jquery-ui'
        ], $item->Js());
        $this->assertSame([], $item->Extras());
        $this->assertTrue($item->IsDefault());

        $item = $items->Get('selectize');
        $this->assertInstanceOf(LibraryItem::class, $item);
        $this->assertSame(['selectize-0.13.6/css/selectize.bootstrap4.css'], $item->Css());
        $this->assertSame(['selectize-0.13.6/js/standalone/selectize'], $item->Js());
        $this->assertSame([], $item->Extras());
        $this->assertFalse($item->IsDefault());

        $item = $items->Get('audiojs');
        $this->assertInstanceOf(LibraryItem::class, $item);
        $this->assertSame(['audiojs-1.0.1/audio'], $item->Css());
        $this->assertSame(['audiojs-1.0.1/audio'], $item->Js());
        $this->assertSame(['audiojs-1.0.1/player-graphics.gif'], $item->Extras());
        $this->assertFalse($item->IsDefault());
    }

    #endregion __construct

    #region Item ---------------------------------------------------------------

    function testItemReturnsNullWhenLibraryIsNotFound()
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<JSON
                {
                  "foo": {
                    "css": "foo.css"
                  }
                }
            JSON);
        $file->expects($this->once())
            ->method('Close');

        $sut->__construct();

        $this->assertNull($sut->Items()->Get('bar'));
    }

    #endregion Item

    #region Defaults -----------------------------------------------------------

    function testDefaultsReturnsOnlyDefaultLibraries()
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<JSON
                {
                  "a": { "css": "a.css", "default": true },
                  "b": { "css": "b.css" },
                  "c": { "css": "c.css", "default": true },
                  "d": { "css": "d.css", "default": false }
                }
            JSON);
        $file->expects($this->once())
            ->method('Close');

        $sut->__construct();
        $defaults = $sut->Defaults();

        $this->assertInstanceOf(CSequentialArray::class, $defaults);
        $this->assertCount(2, $defaults);
        $this->assertSame(['a', 'c'], $defaults->ToArray());
    }

    #endregion Defaults

    #region Data Providers -----------------------------------------------------

    static function invalidAssetValueDataProvider()
    {
        return [
            'null' => ['null'],
            'boolean true' => ['true'],
            'boolean false' => ['false'],
            'integer' => ['123'],
            'float' => ['3.14'],
            //'object' => ['{"key":"value"}'], // becomes a PHP associative array
            'array with null' => ['[null]'],
            'array with boolean' => ['[true, false]'],
            'array with integer' => ['[42]'],
            'array with float' => ['[3.14]'],
            'array with object' => ['[{"key":"value"}]'],
            'array with array' => ['[["a.css"], ["a.js"]]']
        ];
    }

    #endregion Data Providers
}
