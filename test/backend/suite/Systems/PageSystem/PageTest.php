<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\PageSystem\Page;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CSequentialArray;
use \Peneus\Systems\PageSystem\LibraryManager;
use \Peneus\Systems\PageSystem\MetaCollection;
use \Peneus\Systems\PageSystem\PageManifest;
use \Peneus\Systems\PageSystem\Renderer;
use \TestToolkit\AccessHelper;

#[CoversClass(Page::class)]
class PageTest extends TestCase
{
    private ?Renderer $renderer = null;
    private ?LibraryManager $libraryManager = null;
    private ?PageManifest $pageManifest = null;
    private ?MetaCollection $metaCollection = null;
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->renderer = $this->createMock(Renderer::class);
        $this->libraryManager = $this->createMock(LibraryManager::class);
        $this->pageManifest = $this->createMock(PageManifest::class);
        $this->metaCollection = $this->createMock(MetaCollection::class);
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
    }

    protected function tearDown(): void
    {
        $this->renderer = null;
        $this->libraryManager = null;
        $this->pageManifest = null;
        $this->metaCollection = null;
        Config::ReplaceInstance($this->originalConfig);
    }

    private function systemUnderTest(string ...$mockedMethods): Page
    {
        return $this->getMockBuilder(Page::class)
            ->setConstructorArgs([
                __DIR__,
                $this->renderer,
                $this->libraryManager,
                $this->pageManifest,
                $this->metaCollection
            ])
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region SetTitle -----------------------------------------------------------

    function testSetTitle()
    {
        $sut = $this->systemUnderTest();

        // Test default value
        $this->assertSame(
            '',
            AccessHelper::GetMockProperty(Page::class, $sut, 'title')
        );

        $this->assertSame($sut, $sut->SetTitle('Home'));
        $this->assertSame(
            'Home',
            AccessHelper::GetMockProperty(Page::class, $sut, 'title')
        );
    }

    #endregion SetTitle

    #region SetTitleTemplate ---------------------------------------------------

    function testSetTitleTemplate()
    {
        $sut = $this->systemUnderTest();

        // Test default value
        $this->assertSame(
            '{{Title}} | {{AppName}}',
            AccessHelper::GetMockProperty(Page::class, $sut, 'titleTemplate')
        );

        $this->assertSame($sut, $sut->SetTitleTemplate('{{AppName}}: {{Title}}'));
        $this->assertSame(
            '{{AppName}}: {{Title}}',
            AccessHelper::GetMockProperty(Page::class, $sut, 'titleTemplate')
        );
    }

    #endregion SetTitleTemplate

    #region SetMasterpage ------------------------------------------------------

    function testSetMasterpage()
    {
        $sut = $this->systemUnderTest();

        // Test default value
        $this->assertSame(
            '',
            AccessHelper::GetMockProperty(Page::class, $sut, 'masterpage')
        );

        $this->assertSame($sut, $sut->SetMasterpage('basic'));
        $this->assertSame(
            'basic',
            AccessHelper::GetMockProperty(Page::class, $sut, 'masterpage')
        );
    }

    #endregion SetMasterpage

    #region Id -----------------------------------------------------------------

    function testId()
    {
        $sut = $this->systemUnderTest();

        // Note that `$sut` was constructed with `__DIR__` in `systemUnderTest`.
        $this->assertSame(\basename(__DIR__), $sut->Id());
    }

    #endregion Id

    #region Title --------------------------------------------------------------

    function testFormatTitleWhenConfigAppNameAndTitleAreNotSet()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('');

        $this->assertSame('', $sut->Title());
    }

    function testFormatTitleWhenConfigAppNameIsNotSetAndTitleIsSet()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('');

        $sut->SetTitle('Home');

        $this->assertSame('Home', $sut->Title());
    }

    function testFormatTitleWhenConfigAppNameIsSetAndTitleIsNotSet()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('MyWebsite');

        $this->assertSame('MyWebsite', $sut->Title());
    }

    function testFormatTitleWhenConfigAppNameAndTitleAreSet()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('MyWebsite');

        $sut->SetTitle('Home');

        $this->assertSame('Home | MyWebsite', $sut->Title());
    }

    function testFormatTitleWithEmptyTitleTemplate()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('MyWebsite');

        $sut->SetTitle('Home')
            ->SetTitleTemplate('');

        $this->assertSame('', $sut->Title());
    }

    function testFormatTitleWithNoPlaceholdersInTitleTemplate()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('MyWebsite');

        $sut->SetTitle('Home')
            ->SetTitleTemplate('Welcome to my website!');

        $this->assertSame('Welcome to my website!', $sut->Title());
    }

    function testFormatTitleWithUnknownPlaceholderForAppNameInTitleTemplate()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('MyWebsite');

        $sut->SetTitle('Home')
            ->SetTitleTemplate('{{UnknownKey}}: {{Title}}');

        $this->assertSame('{{UnknownKey}}: Home', $sut->Title());
    }

    function testFormatTitleWithUnknownPlaceholderForTitleInTitleTemplate()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('MyWebsite');

        $sut->SetTitle('Home')
            ->SetTitleTemplate('{{AppName}}: {{UnknownKey}}');

        $this->assertSame('MyWebsite: {{UnknownKey}}', $sut->Title());
    }

    function testFormatTitleWithUnknownPlaceholdersInTitleTemplate()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('MyWebsite');

        $sut->SetTitle('Home')
            ->SetTitleTemplate('{{UnknownKey1}}: {{UnknownKey2}}');

        $this->assertSame('{{UnknownKey1}}: {{UnknownKey2}}', $sut->Title());
    }

    function testFormatTitleCorrectlySubstitutesPlaceholdersInTitleTemplate()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('MyWebsite');

        $sut->SetTitle('Home')
            ->SetTitleTemplate('{{AppName}}: {{Title}}');

        $this->assertSame('MyWebsite: Home', $sut->Title());
    }

    #endregion Title

    #region Masterpage ---------------------------------------------------------

    function testMasterpage()
    {
        $sut = $this->systemUnderTest();

        AccessHelper::SetMockProperty(
            Page::class,
            $sut,
            'masterpage',
            'basic'
        );

        $this->assertSame('basic', $sut->Masterpage());
    }

    #endregion Masterpage

    #region Content ------------------------------------------------------------

    function testContent()
    {
        $sut = $this->systemUnderTest();

        AccessHelper::SetMockProperty(
            Page::class,
            $sut,
            'content',
            'Welcome to MyWebsite!'
        );

        $this->assertSame('Welcome to MyWebsite!', $sut->Content());
    }

    #endregion Content

    #region IncludedLibraries --------------------------------------------------

    function testIncludedLibraries()
    {
        $sut = $this->systemUnderTest();
        $included = $this->createStub(CSequentialArray::class);

        $this->libraryManager->expects($this->once())
            ->method('Included')
            ->willReturn($included);

        $this->assertSame($included, $sut->IncludedLibraries());
    }

    #endregion IncludedLibraries

    #region Manifest -----------------------------------------------------------

    function testManifest()
    {
        $sut = $this->systemUnderTest();

        $this->assertSame($this->pageManifest, $sut->Manifest());
    }

    #endregion Manifest

    #region MetaItems ----------------------------------------------------------

    function testMetaItemsDoesNotInjectOgTitleIfAlreadyPresent()
    {
        $sut = $this->systemUnderTest();
        $metas = $this->createStub(CArray::class);

        $this->metaCollection->expects($this->once())
            ->method('Has')
            ->with('og:title', 'property')
            ->willReturn(true);
        $this->metaCollection->expects($this->never())
            ->method('Add');
        $this->metaCollection->expects($this->once())
            ->method('Items')
            ->willReturn($metas);

        $this->assertSame($metas, $sut->MetaItems());
    }

    function testMetaItemsInjectsOgTitleIfMissing()
    {
        $sut = $this->systemUnderTest('Title');
        $metas = $this->createStub(CArray::class);

        $sut->expects($this->once())
            ->method('Title')
            ->willReturn('My Title');
        $this->metaCollection->expects($this->once())
            ->method('Has')
            ->with('og:title', 'property')
            ->willReturn(false);
        $this->metaCollection->expects($this->once())
            ->method('Add')
            ->with('og:title','My Title', 'property');
        $this->metaCollection->expects($this->once())
            ->method('Items')
            ->willReturn($metas);

        $this->assertSame($metas, $sut->MetaItems());
    }

    #endregion MetaItems

    #region Begin --------------------------------------------------------------

    function testBegin()
    {
        $sut = $this->systemUnderTest('_ob_start');

        $sut->expects($this->once())
            ->method('_ob_start');

        $sut->Begin();

        $this->assertSame('', $sut->Content());
    }

    #endregion Begin

    #region End ----------------------------------------------------------------

    function testEnd()
    {
        $sut = $this->systemUnderTest('_ob_get_clean');

        $sut->expects($this->once())
            ->method('_ob_get_clean')
            ->willReturn('Welcome to MyWebsite!');
        $this->renderer->expects($this->once())
            ->method('Render')
            ->with($sut);

        $sut->End();

        $this->assertSame('Welcome to MyWebsite!', $sut->Content());
    }

    #endregion End

    #region AddLibrary ---------------------------------------------------------

    function testAddLibrary()
    {
        $sut = $this->systemUnderTest();

        $this->libraryManager->expects($this->once())
            ->method('Add')
            ->with('jquery');

        $this->assertSame($sut, $sut->AddLibrary('jquery'));
    }

    #endregion AddLibrary

    #region RemoveLibrary ------------------------------------------------------

    function testRemoveLibrary()
    {
        $sut = $this->systemUnderTest();

        $this->libraryManager->expects($this->once())
            ->method('Remove')
            ->with('jquery');

        $this->assertSame($sut, $sut->RemoveLibrary('jquery'));
    }

    #endregion RemoveLibrary

    #region RemoveAllLibraries -------------------------------------------------

    function testRemoveAllLibraries()
    {
        $sut = $this->systemUnderTest();

        $this->libraryManager->expects($this->once())
            ->method('RemoveAll');

        $this->assertSame($sut, $sut->RemoveAllLibraries());
    }

    #endregion RemoveAllLibraries

    #region AddMeta ------------------------------------------------------------

    function testAddMeta()
    {
        $sut = $this->systemUnderTest();

        $this->metaCollection->expects($this->once())
            ->method('Add')
            ->with('description', 'my description', 'name');

        $this->assertSame($sut, $sut->AddMeta('description', 'my description'));
    }

    #endregion AddMeta

    #region RemoveMeta ---------------------------------------------------------

    function testRemoveMeta()
    {
        $sut = $this->systemUnderTest();

        $this->metaCollection->expects($this->once())
            ->method('Remove')
            ->with('description', 'name');

        $this->assertSame($sut, $sut->RemoveMeta('description', 'name'));
    }

    #endregion RemoveMeta

    #region RemoveAllMetas -----------------------------------------------------

    function testRemoveAllMetas()
    {
        $sut = $this->systemUnderTest();

        $this->metaCollection->expects($this->once())
            ->method('RemoveAll');

        $this->assertSame($sut, $sut->RemoveAllMetas());
    }

    #endregion RemoveAllMetas
}
