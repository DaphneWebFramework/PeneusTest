<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\PageSystem\MetaCollection;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \TestToolkit\AccessHelper;

#[CoversClass(MetaCollection::class)]
class MetaCollectionTest extends TestCase
{
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalConfig = Config::ReplaceInstance($this->createMock(Config::class));
    }

    protected function tearDown(): void
    {
        Config::ReplaceInstance($this->originalConfig);
    }

    private function systemUnderTest(string ...$mockedMethods): MetaCollection
    {
        return $this->getMockBuilder(MetaCollection::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region __construct --------------------------------------------------------

    function testConstructorCallsAddDefaults()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->expects($this->once())
            ->method('addDefaults');

        $sut->__construct();
    }

    #endregion __construct

    #region Has ----------------------------------------------------------------

    function testHasReturnsTrueWhenMetaExists()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->__construct();
        $sut->Add('description', 'value');
        $sut->Add('og:title', 'value', 'property');

        $this->assertTrue($sut->Has('description', 'name'));
        $this->assertTrue($sut->Has('og:title', 'property'));
    }

    function testHasReturnsFalseWhenTypeIsMissing()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->__construct();
        $this->assertFalse($sut->Has('og:title', 'property'));
    }

    function testHasReturnsFalseWhenNameIsMissingInType()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->__construct();
        $sut->Add('description', 'value'); // under 'name'
        $this->assertFalse($sut->Has('viewport', 'name'));
        $this->assertFalse($sut->Has('description', 'property'));
    }

    #endregion Has

    #region Add ---------------------------------------------------------------

    function testAddStoresMetaUnderCorrectType()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->__construct();
        $sut->Add('description', 'my description');
        $sut->Add('og:title', 'title', 'property');

        $this->assertSame('my description', $sut->Items()->Get('name')->Get('description'));
        $this->assertSame('title', $sut->Items()->Get('property')->Get('og:title'));
    }

    function testAddReplacesExistingMetaOfSameType()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->__construct();
        $sut->Add('description', 'original');
        $sut->Add('description', 'updated');

        $this->assertSame('updated', $sut->Items()->Get('name')->Get('description'));
    }

    #endregion Add

    #region Remove ------------------------------------------------------------

    function testRemoveIgnoresMissingMeta()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->__construct();
        $sut->Remove('does-not-exist', 'name');

        $this->assertTrue($sut->Items()->IsEmpty());
    }

    function testRemoveDeletesMeta()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->__construct();
        $sut->Add('description', 'my description');
        $sut->Remove('description', 'name');

        $this->assertFalse($sut->Has('description', 'name'));
    }

    function testRemoveDeletesMetaButKeepsOtherTypes()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->__construct();
        $sut->Add('description', 'my description');
        $sut->Add('og:title', 'title', 'property');
        $sut->Remove('description', 'name');

        $this->assertFalse($sut->Has('description', 'name'));
        $this->assertTrue($sut->Has('og:title', 'property'));
    }

    #endregion Remove

    #region RemoveAll ----------------------------------------------------------

    function testRemoveAllDeletesAllMetas()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->__construct();
        $sut->Add('description', 'my description');
        $sut->Add('og:title', 'title', 'property');
        $sut->RemoveAll();

        $this->assertTrue($sut->Items()->IsEmpty());
    }

    #endregion Clear

    #region Items -------------------------------------------------------------

    function testItemsReturnsGroupedStructure()
    {
        $sut = $this->systemUnderTest('addDefaults');

        $sut->__construct();
        $sut->Add('viewport', 'my viewport');
        $sut->Add('og:type', 'website', 'property');

        $items = $sut->Items();
        $this->assertInstanceOf(CArray::class, $items);
        $this->assertTrue($items->Has('name'));
        $this->assertTrue($items->Has('property'));
        $this->assertSame('my viewport', $items->Get('name')->Get('viewport'));
        $this->assertSame('website', $items->Get('property')->Get('og:type'));
    }

    #endregion Items

    #region addDefaults --------------------------------------------------------

    function testAddDefaultsReadsFromConfig()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $items = new CArray();

        $config->expects($this->exactly(3))
            ->method('Option')
            ->willReturnMap([
                ['Description', 'my description'],
                ['Viewport', 'my viewport'],
                ['Locale', 'my locale']
            ]);

        AccessHelper::SetMockProperty(MetaCollection::class, $sut, 'items', $items);
        AccessHelper::CallMethod($sut, 'addDefaults');

        $this->assertSame('my description', $items->Get('name')->Get('description'));
        $this->assertSame('my description', $items->Get('property')->Get('og:description'));
        $this->assertSame('my viewport', $items->Get('name')->Get('viewport'));
        $this->assertSame('my locale', $items->Get('property')->Get('og:locale'));
        $this->assertSame('website', $items->Get('property')->Get('og:type'));
    }

    function testAddDefaultsSkipsUnsetDescription()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $items = new CArray();

        $config->expects($this->exactly(3))
            ->method('Option')
            ->willReturnMap([
                ['Description', null],
                ['Viewport', 'my viewport'],
                ['Locale', 'my locale']
            ]);

        AccessHelper::SetMockProperty(MetaCollection::class, $sut, 'items', $items);
        AccessHelper::CallMethod($sut, 'addDefaults');

        $this->assertFalse($sut->Has('description', 'name'));
        $this->assertFalse($sut->Has('og:description', 'property'));
        $this->assertSame('my viewport', $items->Get('name')->Get('viewport'));
        $this->assertSame('my locale', $items->Get('property')->Get('og:locale'));
        $this->assertSame('website', $items->Get('property')->Get('og:type'));
    }

    function testAddDefaultsSkipsUnsetViewport()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $items = new CArray();

        $config->expects($this->exactly(3))
            ->method('Option')
            ->willReturnMap([
                ['Description', 'my description'],
                ['Viewport', null],
                ['Locale', 'my locale']
            ]);

        AccessHelper::SetMockProperty(MetaCollection::class, $sut, 'items', $items);
        AccessHelper::CallMethod($sut, 'addDefaults');

        $this->assertSame('my description', $items->Get('name')->Get('description'));
        $this->assertFalse($sut->Has('viewport', 'name'));
        $this->assertSame('my locale', $items->Get('property')->Get('og:locale'));
        $this->assertSame('website', $items->Get('property')->Get('og:type'));
    }

    function testAddDefaultsSkipsUnsetLocale()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $items = new CArray();

        $config->expects($this->exactly(3))
            ->method('Option')
            ->willReturnMap([
                ['Description', 'my description'],
                ['Viewport', 'my viewport'],
                ['Locale', null]
            ]);

        AccessHelper::SetMockProperty(MetaCollection::class, $sut, 'items', $items);
        AccessHelper::CallMethod($sut, 'addDefaults');

        $this->assertSame('my description', $items->Get('name')->Get('description'));
        $this->assertSame('my viewport', $items->Get('name')->Get('viewport'));
        $this->assertFalse($sut->Has('og:locale', 'property'));
        $this->assertSame('website', $items->Get('property')->Get('og:type'));
    }

    #endregion addDefaults
}
