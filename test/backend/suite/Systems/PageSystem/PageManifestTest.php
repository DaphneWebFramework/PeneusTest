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

    #region Css, Js, Extras ----------------------------------------------------

    function testCssJsExtrasDelegateToAssets()
    {
        $sut = $this->systemUnderTest();
        $assets = $this->createMock(Assets::class);

        $assets->expects($this->once())
            ->method('Css')
            ->willReturn(['a.css']);
        $assets->expects($this->once())
            ->method('Js')
            ->willReturn(['b.js']);
        $assets->expects($this->once())
            ->method('Extras')
            ->willReturn(['c.map']);

        AccessHelper::SetMockProperty(
            PageManifest::class,
            $sut,
            'assets',
            $assets
        );

        $this->assertSame(['a.css'], $sut->Css());
        $this->assertSame(['b.js'], $sut->Js());
        $this->assertSame(['c.map'], $sut->Extras());
    }

    #endregion Css, Js, Extras

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
        $this->assertSame([], $assets->Extras());
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
        $this->assertSame([], $assets->Extras());
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
        $this->assertSame([], $assets->Extras());
    }

    function testLoadFileReturnsParsedAssetsFromValidJson()
    {
        $sut = $this->systemUnderTest('openFile', 'validateAssetField');
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
                  "js": ["app.js"],
                  "*": ["extra.json"]
                }
            JSON);
        $file->expects($this->once())
            ->method('Close');
        $sut->expects($this->exactly(3))
            ->method('validateAssetField')
            ->willReturnCallback(function(array $data, string $key) {
                return $data[$key] ?? null;
            });

        $assets = AccessHelper::CallMethod($sut, 'loadFile', [$pageId]);
        $this->assertSame(['index.css'], $assets->Css());
        $this->assertSame(['app.js'], $assets->Js());
        $this->assertSame(['extra.json'], $assets->Extras());
    }

    #endregion loadFile

    #region validateAssetField -------------------------------------------------

    function testValidateAssetFieldReturnsNullIfKeyMissing()
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
            '/^Page asset value must be a string( or an array of strings)?\.$/');
        AccessHelper::CallMethod($sut, 'validateAssetValue', [$value]);
    }

    #endregion validateAssetValue

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
