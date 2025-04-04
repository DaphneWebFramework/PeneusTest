<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Systems\PageSystem\LibraryManifest;

use \Harmonia\Core\CArray;
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

    function testConstructorStoresItemsReturnedByLoadFile()
    {
        $sut = $this->systemUnderTest('loadFile');
        $items = $this->createStub(CArray::class);

        $sut->expects($this->once())
            ->method('loadFile')
            ->willReturn($items);

        $sut->__construct();

        $this->assertSame($items, AccessHelper::GetProperty($sut, 'items'));
    }

    #endregion __construct

    #region Items --------------------------------------------------------------

    function testItemsReturnsItems()
    {
        $sut = $this->systemUnderTest();
        $items = $this->createStub(CArray::class);

        AccessHelper::SetMockProperty(
            LibraryManifest::class,
            $sut,
            'items',
            $items
        );

        $this->assertSame($items, $sut->Items());
    }

    #endregion Items

    #region Defaults -----------------------------------------------------------

    function testDefaultsReturnsOnlyDefaultItems()
    {
        $sut = $this->systemUnderTest();
        $items = new CArray([
            'lib1' => $this->createConfiguredMock(LibraryItem::class, [
                'IsDefault' => true
            ]),
            'lib2' => $this->createConfiguredMock(LibraryItem::class, [
                'IsDefault' => false
            ]),
            'lib3' => $this->createConfiguredMock(LibraryItem::class, [
                'IsDefault' => true
            ]),
        ]);

        AccessHelper::SetMockProperty(
            LibraryManifest::class,
            $sut,
            'items',
            $items
        );

        $this->assertEquals(
            new CSequentialArray(['lib1', 'lib3']),
            $sut->Defaults()
        );
    }

    #endregion Defaults

    #region loadFile -----------------------------------------------------------

    function testLoadFileThrowsWhenFileCannotBeOpened()
    {
        $sut = $this->systemUnderTest('openFile');
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();

        $resource->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Manifest file could not be opened.');
        AccessHelper::CallMethod($sut, 'loadFile');
    }

    function testLoadFileThrowsWhenFileCannotBeRead()
    {
        $sut = $this->systemUnderTest('openFile');
        $file = $this->createMock(CFile::class);
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();

        $resource->method('FrontendManifestFilePath')
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
        AccessHelper::CallMethod($sut, 'loadFile');
    }

    function testLoadFileThrowsWhenJsonCannotBeDecoded()
    {
        $sut = $this->systemUnderTest('openFile');
        $file = $this->createMock(CFile::class);
        $path = $this->createStub(CPath::class);
        $resource = Resource::Instance();

        $resource->method('FrontendManifestFilePath')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn('{invalid');
        $file->expects($this->once())
            ->method('Close');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Manifest file contains invalid JSON.');
        AccessHelper::CallMethod($sut, 'loadFile');
    }

    function testLoadFileThrowsWhenLibraryNameIsNotString()
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
        AccessHelper::CallMethod($sut, 'loadFile');
    }

    function testLoadFileThrowsWhenLibraryNameIsEmpty()
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
        AccessHelper::CallMethod($sut, 'loadFile');
    }

    function testLoadFileThrowsWhenLibraryDataIsNotArray()
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
                  "lib1": "I should have been an object"
                }
            JSON);
        $file->expects($this->once())
            ->method('Close');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Library data must be an object.');
        AccessHelper::CallMethod($sut, 'loadFile');
    }

    function testLoadFileReturnsParsedItemsFromValidJson()
    {
        $sut = $this->systemUnderTest(
            'openFile',
            'validateAssetField',
            'validateBooleanField'
        );
        $file = $this->createMock(CFile::class);
        $resource = Resource::Instance();
        $path = $this->createStub(CPath::class);

        $resource->method('FrontendManifestFilePath')
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
                    "css": "jquery-ui-1.12.1.custom/jquery-ui",
                    "js": [
                      "jquery-3.5.1/jquery",
                      "jquery-ui-1.12.1.custom/jquery-ui"
                    ],
                    "default": true
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
        $sut->expects($this->exactly(9))
            ->method('validateAssetField')
            ->willReturnCallback(function(array $data, string $key) {
                return $data[$key] ?? null;
            });
        $sut->expects($this->exactly(3))
            ->method('validateBooleanField')
            ->willReturnCallback(function(array $data, string $key) {
                return $data[$key] ?? false;
            });

        $items = AccessHelper::CallMethod($sut, 'loadFile');

        $this->assertInstanceOf(LibraryItem::class, $items->Get('jquery'));
        $this->assertSame(['jquery-ui-1.12.1.custom/jquery-ui'],
                          $items->Get('jquery')->Css());
        $this->assertSame(['jquery-3.5.1/jquery', 'jquery-ui-1.12.1.custom/jquery-ui'],
                          $items->Get('jquery')->Js());
        $this->assertSame([], $items->Get('jquery')->Extras());
        $this->assertTrue($items->Get('jquery')->IsDefault());

        $this->assertInstanceOf(LibraryItem::class, $items->Get('selectize'));
        $this->assertSame(['selectize-0.13.6/css/selectize.bootstrap4.css'],
                          $items->Get('selectize')->Css());
        $this->assertSame(['selectize-0.13.6/js/standalone/selectize'],
                          $items->Get('selectize')->Js());
        $this->assertSame([], $items->Get('selectize')->Extras());
        $this->assertFalse($items->Get('selectize')->IsDefault());

        $this->assertInstanceOf(LibraryItem::class, $items->Get('audiojs'));
        $this->assertSame(['audiojs-1.0.1/audio'], $items->Get('audiojs')->Css());
        $this->assertSame(['audiojs-1.0.1/audio'], $items->Get('audiojs')->Js());
        $this->assertSame(['audiojs-1.0.1/player-graphics.gif'],
                          $items->Get('audiojs')->Extras());
        $this->assertFalse($items->Get('audiojs')->IsDefault());
    }

    #endregion loadFile

    #region validateAssetField -------------------------------------------------

    function testValidateAssetFieldReturnsNullIfKeyIsMissing()
    {
        $sut = $this->systemUnderTest('validateAssetValue');
        $data = ['js' => ['a.js']];
        $key = 'css';

        $sut->expects($this->never())
            ->method('validateAssetValue');

        $this->assertNull(AccessHelper::CallMethod(
            $sut,
            'validateAssetField',
            [$data, $key]
        ));
    }

    function testValidateAssetFieldDelegatesToValidateAssetValue()
    {
        $sut = $this->systemUnderTest('validateAssetValue');
        $value = ['a.js'];
        $data = ['js' => $value];
        $key = 'js';

        $sut->expects($this->once())
            ->method('validateAssetValue')
            ->with($value)
            ->willReturn($value);

        $this->assertSame($value, AccessHelper::CallMethod(
            $sut,
            'validateAssetField',
            [$data, $key]
        ));
    }

    #endregion validateAssetField

    #region validateAssetValue -------------------------------------------------

    function testValidateAssetValueReturnsStringWhenInputIsString()
    {
        $sut = $this->systemUnderTest();
        $value = 'foo.css';

        $result = AccessHelper::CallMethod($sut, 'validateAssetValue', [$value]);
        $this->assertSame($value, $result);
    }

    function testValidateAssetValueReturnsArrayWhenInputIsArrayOfStrings()
    {
        $sut = $this->systemUnderTest();
        $value = ['a.css', 'b.css'];

        $result = AccessHelper::CallMethod($sut, 'validateAssetValue', [$value]);
        $this->assertSame($value, $result);
    }

    #[DataProvider('invalidAssetValueDataProvider')]
    function testValidateAssetValueThrowsOnInvalidInput(string $jsonValue)
    {
        $sut = $this->systemUnderTest();
        $value = \json_decode($jsonValue, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/^Library asset value must be a string( or an array of strings)?\.$/');
        AccessHelper::CallMethod($sut, 'validateAssetValue', [$value]);
    }

    #endregion validateAssetValue

    #region validateBooleanField -----------------------------------------------

    function testValidateBooleanFieldCastsValidBooleans()
    {
        $sut = $this->systemUnderTest();

        $this->assertTrue(AccessHelper::CallMethod(
            $sut,
            'validateBooleanField',
            [['default' => true], 'default']
        ));
        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'validateBooleanField',
            [['default' => false], 'default']
        ));
        $this->assertTrue(AccessHelper::CallMethod(
            $sut,
            'validateBooleanField',
            [['default' => 1], 'default']
        ));
        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'validateBooleanField',
            [['default' => 0], 'default']
        ));
        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'validateBooleanField',
            [['other' => true], 'default']
        ));
    }

    #endregion validateBooleanField

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
