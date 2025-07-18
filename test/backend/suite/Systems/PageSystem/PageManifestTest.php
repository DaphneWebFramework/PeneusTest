<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Systems\PageSystem\PageManifest;

use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Peneus\Resource;
use \Peneus\Systems\PageSystem\Assets;
use \TestToolkit\AccessHelper;

#[CoversClass(PageManifest::class)]
class PageManifestTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): PageManifest
    {
        return $this->getMockBuilder(PageManifest::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region __construct --------------------------------------------------------

    function testConstructorStoresAssetsReturnedByLoadFile()
    {
        $sut = $this->systemUnderTest('loadFile');
        $pageId = 'mypage';
        $assets = $this->createStub(Assets::class);

        $sut->expects($this->once())
            ->method('loadFile')
            ->with($pageId)
            ->willReturn($assets);

        $sut->__construct($pageId);

        $this->assertSame($assets, AccessHelper::GetProperty($sut, 'assets'));
    }

    #endregion __construct

    #region Css, Js ------------------------------------------------------------

    function testCssJsDelegateToAssets()
    {
        $sut = $this->systemUnderTest();
        $assets = $this->createMock(Assets::class);

        $assets->expects($this->once())
            ->method('Css')
            ->willReturn(['a.css']);
        $assets->expects($this->once())
            ->method('Js')
            ->willReturn(['b.js']);

        AccessHelper::SetMockProperty(
            PageManifest::class,
            $sut,
            'assets',
            $assets
        );

        $this->assertSame(['a.css'], $sut->Css());
        $this->assertSame(['b.js'], $sut->Js());
    }

    #endregion Css, Js

    #region loadFile -----------------------------------------------------------

    function testLoadFileReturnsEmptyAssetsWhenFileCannotBeOpened()
    {
        $sut = $this->systemUnderTest('openFile');
        $resource = Resource::Instance();
        $pageId = 'mypage';
        $manifestFilePath = CPath::Join('path/to', $pageId, 'manifest.json');

        $resource->method('PageFilePath')
            ->with($pageId, 'manifest.json')
            ->willReturn($manifestFilePath);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($manifestFilePath)
            ->willReturn(null);

        $assets = AccessHelper::CallMethod($sut, 'loadFile', [$pageId]);
        $this->assertInstanceOf(Assets::class, $assets);
        $this->assertSame([], $assets->Css());
        $this->assertSame([], $assets->Js());
    }

    function testLoadFileReturnsEmptyAssetsWhenFileCannotBeRead()
    {
        $sut = $this->systemUnderTest('openFile');
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);
        $pageId = 'mypage';
        $manifestFilePath = CPath::Join('path/to', $pageId, 'manifest.json');

        $resource->method('PageFilePath')
            ->with($pageId, 'manifest.json')
            ->willReturn($manifestFilePath);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($manifestFilePath)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(null);
        $file->expects($this->once())
            ->method('Close');

        $assets = AccessHelper::CallMethod($sut, 'loadFile', [$pageId]);
        $this->assertSame([], $assets->Css());
        $this->assertSame([], $assets->Js());
    }

    function testLoadFileReturnsEmptyAssetsWhenJsonCannotBeDecoded()
    {
        $sut = $this->systemUnderTest('openFile');
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);
        $pageId = 'mypage';
        $manifestFilePath = CPath::Join('path/to', $pageId, 'manifest.json');

        $resource->method('PageFilePath')
            ->with($pageId, 'manifest.json')
            ->willReturn($manifestFilePath);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($manifestFilePath)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn('{invalid');
        $file->expects($this->once())
            ->method('Close');

        $assets = AccessHelper::CallMethod($sut, 'loadFile', [$pageId]);
        $this->assertSame([], $assets->Css());
        $this->assertSame([], $assets->Js());
    }

    function testLoadFileReturnsParsedAssetsFromValidJson()
    {
        $sut = $this->systemUnderTest('openFile', 'parseAssetBlock');
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);
        $pageId = 'mypage';
        $manifestFilePath = CPath::Join('path/to', $pageId, 'manifest.json');

        $resource->method('PageFilePath')
            ->with($pageId, 'manifest.json')
            ->willReturn($manifestFilePath);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($manifestFilePath)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<JSON
                {
                  "css": ["index.css"],
                  "js": ["app.js"]
                }
            JSON);
        $file->expects($this->once())
            ->method('Close');
        $sut->expects($this->exactly(2))
            ->method('parseAssetBlock')
            ->willReturnCallback(function(array $data, string $key) {
                return $data[$key] ?? null;
            });

        $assets = AccessHelper::CallMethod($sut, 'loadFile', [$pageId]);
        $this->assertSame(['index.css'], $assets->Css());
        $this->assertSame(['app.js'], $assets->Js());
    }

    #endregion loadFile

    #region parseAssetBlock ----------------------------------------------------

    function testParseFieldReturnsNullIfKeyIsMissing()
    {
        $sut = $this->systemUnderTest('parseAssetValue');
        $data = ['js' => ['a.js']];
        $key = 'css';

        $sut->expects($this->never())
            ->method('parseAssetValue');

        $this->assertNull(AccessHelper::CallMethod(
            $sut,
            'parseAssetBlock',
            [$data, $key]
        ));
    }

    function testParseFieldDelegatesToParseValue()
    {
        $sut = $this->systemUnderTest('parseAssetValue');
        $value = ['a.js'];
        $data = ['js' => $value];
        $key = 'js';

        $sut->expects($this->once())
            ->method('parseAssetValue')
            ->with($value)
            ->willReturn($value);

        $this->assertSame($value, AccessHelper::CallMethod(
            $sut,
            'parseAssetBlock',
            [$data, $key]
        ));
    }

    #endregion parseAssetBlock

    #region parseAssetValue ----------------------------------------------------

    function testParseValueReturnsStringWhenInputIsString()
    {
        $sut = $this->systemUnderTest();
        $value = 'foo.css';

        $result = AccessHelper::CallMethod($sut, 'parseAssetValue', [$value]);
        $this->assertSame($value, $result);
    }

    function testParseValueReturnsArrayWhenInputIsArrayOfStrings()
    {
        $sut = $this->systemUnderTest();
        $value = ['a.css', 'b.css'];

        $result = AccessHelper::CallMethod($sut, 'parseAssetValue', [$value]);
        $this->assertSame($value, $result);
    }

    #[DataProvider('invalidAssetValueDataProvider')]
    function testParseValueThrowsOnInvalidInput(string $jsonValue)
    {
        $sut = $this->systemUnderTest();
        $value = \json_decode($jsonValue, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/^Manifest entry must be a string( or an array of strings)?\.$/');
        AccessHelper::CallMethod($sut, 'parseAssetValue', [$value]);
    }

    #endregion parseAssetValue

    #region Data Providers -----------------------------------------------------

    static function invalidAssetValueDataProvider()
    {
        return [
            'null' => ['null'],
            'boolean true' => ['true'],
            'boolean false' => ['false'],
            'integer' => ['123'],
            'float' => ['3.14'],
            //'object' => ['{"key":"value"}'], // associative, not allowed
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
