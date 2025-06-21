<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Systems\PageSystem\Renderer;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Core\CSequentialArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Logger;
use \Peneus\Resource;
use \Peneus\Systems\PageSystem\LibraryItem;
use \Peneus\Systems\PageSystem\Page;
use \Peneus\Systems\PageSystem\PageManifest;
use \TestToolkit\AccessHelper;

#[CoversClass(Renderer::class)]
class RendererTest extends TestCase
{
    private ?Config $originalConfig = null;
    private ?Resource $originalResource = null;
    private ?Logger $originalLogger = null;

    protected function setUp(): void
    {
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
        $this->originalLogger =
            Logger::ReplaceInstance($this->createStub(Logger::class));
    }

    protected function tearDown(): void
    {
        Config::ReplaceInstance($this->originalConfig);
        Resource::ReplaceInstance($this->originalResource);
        Logger::ReplaceInstance($this->originalLogger);
    }

    private function systemUnderTest(string ...$mockedMethods): Renderer
    {
        return $this->getMockBuilder(Renderer::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    private function libraries(): CSequentialArray
    {
        return new CSequentialArray([
            $this->createConfiguredMock(LibraryItem::class, [
                'Css' => ['jquery-ui-1.12.1.custom/jquery-ui'],
                'Js' => [
                    'jquery-3.5.1/jquery',
                    'jquery-ui-1.12.1.custom/jquery-ui'
                ],
                'IsDefault' => true
            ]),
            $this->createConfiguredMock(LibraryItem::class, [
                'Css' => ['bootstrap-4.6.2/css/bootstrap'],
                'Js' => ['bootstrap-4.6.2/js/bootstrap.bundle'],
                'IsDefault' => true
            ]),
            $this->createConfiguredMock(LibraryItem::class, [
                'Css' => ['bootstrap-icons-1.9.1/bootstrap-icons'],
                'Js' => [],
                'IsDefault' => true
            ]),
            $this->createConfiguredMock(LibraryItem::class, [
                'Css' => ['dataTables-1.11.3/css/dataTables.bootstrap4'],
                'Js' => [
                    'dataTables-1.11.3/js/jquery.dataTables',
                    'dataTables-1.11.3/js/dataTables.bootstrap4'
                ],
                'IsDefault' => false
            ]),
        ]);
    }

    private function pageManifest(): PageManifest
    {
        return $this->createConfiguredMock(PageManifest::class, [
            'Css' => [
                'https://cdn.example.com/fonts.css',
                'index',
                'theme'
            ],
            'Js' => [
                'http://cdn.example.com/script.js',
                'Model',
                'View',
                'Controller'
            ]
        ]);
    }

    #region Render -------------------------------------------------------------

    function testRenderWhenOpenFileReturnsNull()
    {
        $sut = $this->systemUnderTest('openFile', '_echo');
        $resource = Resource::Instance();
        $page = $this->createStub(Page::class);

        $resource->expects($this->once())
            ->method('TemplateFilePath')
            ->with('page')
            ->willReturn(new CPath('path/to/templates/page.html'));
        $sut->expects($this->once())
            ->method('openFile')
            ->with(new CPath('path/to/templates/page.html'))
            ->willReturn(null);
        $sut->expects($this->never())
            ->method('_echo');

        $sut->Render($page);
    }

    function testRenderWhenFileReadReturnsNull()
    {
        $sut = $this->systemUnderTest('openFile', '_echo');
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);
        $page = $this->createStub(Page::class);

        $resource->expects($this->once())
            ->method('TemplateFilePath')
            ->with('page')
            ->willReturn(new CPath('path/to/templates/page.html'));
        $sut->expects($this->once())
            ->method('openFile')
            ->with(new CPath('path/to/templates/page.html'))
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(null);
        $file->expects($this->once())
            ->method('Close');
        $sut->expects($this->never())
            ->method('_echo');

        $sut->Render($page);
    }

    function testRenderWhenFileReadReturnsTemplate()
    {
        $sut = $this->systemUnderTest(
            'openFile',
            'content',
            'libraryStylesheetLinks',
            'pageStylesheetLinks',
            'libraryJavascriptLinks',
            'pageJavascriptLinks',
            'renderMetaTags',
            '_echo'
        );
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);
        $config = Config::Instance();
        $page = $this->createMock(Page::class);
        $libraries = $this->createStub(CSequentialArray::class);

        $resource->expects($this->once())
            ->method('TemplateFilePath')
            ->with('page')
            ->willReturn(new CPath('path/to/templates/page.html'));
        $sut->expects($this->once())
            ->method('openFile')
            ->with(new CPath('path/to/templates/page.html'))
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<HTML
                <!DOCTYPE html>
                <html lang="{{Language}}">
                <head>
                	<meta charset="utf-8">
                	<title>{{Title}}</title>
                	{{MetaTags}}
                	{{LibraryStylesheetLinks}}
                	{{PageStylesheetLinks}}
                </head>
                <body>
                	{{Content}}
                	{{LibraryJavascriptLinks}}
                	{{PageJavascriptLinks}}
                </body>
                </html>
            HTML);
        $file->expects($this->once())
            ->method('Close');
        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('Language', '')
            ->willReturn('tr');
        $page->expects($this->once())
            ->method('Title')
            ->willReturn('Home | MyWebsite');
        $page->expects($this->once())
            ->method('IncludedLibraries')
            ->willReturn($libraries);
        $sut->expects($this->once())
            ->method('renderMetaTags')
            ->with($page->MetaItems())
            ->willReturn("\t" . '<meta name="description" content="my description">');
        $sut->expects($this->once())
            ->method('libraryStylesheetLinks')
            ->with($libraries)
            ->willReturn("\t" . '<link rel="stylesheet" href="url/to/bootstrap-4.6.2/css/bootstrap.css">');
        $sut->expects($this->once())
            ->method('pageStylesheetLinks')
            ->with($page)
            ->willReturn("\t" . '<link rel="stylesheet" href="url/to/pages/home/style.css">');
        $sut->expects($this->once())
            ->method('content')
            ->with($page)
            ->willReturn("\t" . 'Welcome to MyWebsite!');
        $sut->expects($this->once())
            ->method('libraryJavascriptLinks')
            ->with($libraries)
            ->willReturn("\t" . '<script src="url/to/bootstrap-4.6.2/js/bootstrap.bundle.js"></script>');
        $sut->expects($this->once())
            ->method('pageJavascriptLinks')
            ->with($page)
            ->willReturn("\t" . '<script src="url/to/pages/home/script.js"></script>');
        $sut->expects($this->once())
            ->method('_echo')
            ->with(<<<HTML
                <!DOCTYPE html>
                <html lang="tr">
                <head>
                	<meta charset="utf-8">
                	<title>Home | MyWebsite</title>
                	<meta name="description" content="my description">
                	<link rel="stylesheet" href="url/to/bootstrap-4.6.2/css/bootstrap.css">
                	<link rel="stylesheet" href="url/to/pages/home/style.css">
                </head>
                <body>
                	Welcome to MyWebsite!
                	<script src="url/to/bootstrap-4.6.2/js/bootstrap.bundle.js"></script>
                	<script src="url/to/pages/home/script.js"></script>
                </body>
                </html>
            HTML);

        $sut->Render($page);
    }

    #endregion Render

    #region content ------------------------------------------------------------

    function testContentWhenMasterpageNameIsEmpty()
    {
        $sut = $this->systemUnderTest('_ob_start', '_ob_get_clean', '_echo');
        $page = $this->createMock(Page::class);

        $sut->expects($this->once())
            ->method('_ob_start');
        $page->expects($this->once())
            ->method('Masterpage')
            ->willReturn('');
        $page->expects($this->once())
            ->method('Content')
            ->willReturn('Welcome to MyWebsite!');
        $sut->expects($this->once())
            ->method('_echo')
            ->with('Welcome to MyWebsite!');
        $sut->expects($this->once())
            ->method('_ob_get_clean')
            ->willReturn('Welcome to MyWebsite!');

        $this->assertSame(
            'Welcome to MyWebsite!',
            AccessHelper::CallMethod($sut, 'content', [$page])
        );
    }

    function testContentWhenMasterpageDoesNotExist()
    {
        $sut = $this->systemUnderTest('_ob_start', '_ob_get_clean', '_echo');
        $page = $this->createMock(Page::class);
        $resource = Resource::Instance();
        $masterpagePath = $this->createMock(CPath::class);

        $sut->expects($this->once())
            ->method('_ob_start');
        $page->expects($this->once())
            ->method('Masterpage')
            ->willReturn('basic');
        $resource->expects($this->once())
            ->method('MasterpageFilePath')
            ->with('basic')
            ->willReturn($masterpagePath);
        $masterpagePath->expects($this->once())
            ->method('IsFile')
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('_echo');
        $sut->expects($this->once())
            ->method('_ob_get_clean')
            ->willReturn('');

        $this->assertSame(
            '',
            AccessHelper::CallMethod($sut, 'content', [$page])
        );
    }

    function testContentWhenMasterpageExists()
    {
        $sut = $this->systemUnderTest();
        $page = $this->createMock(Page::class);
        $resource = Resource::Instance();
        $masterpagePath = new CPath(__DIR__ . '/test-masterpage.php');

        \file_put_contents((string)$masterpagePath, <<<PHP
            <h1>This is a test masterpage.</h1>
            <?=\$this->Content()?>
            <footer>&copy; 2025</footer>
        PHP);

        $page->expects($this->once())
            ->method('Masterpage')
            ->willReturn('test-masterpage');
        $resource->expects($this->once())
            ->method('MasterpageFilePath')
            ->with('test-masterpage')
            ->willReturn($masterpagePath);
        $page->expects($this->once())
            ->method('Content')
            ->willReturn("Hello, World!\n");

        $result = AccessHelper::CallMethod($sut, 'content', [$page]);

        $this->assertSame(<<<HTML
            <h1>This is a test masterpage.</h1>
            Hello, World!
            <footer>&copy; 2025</footer>
        HTML, $result);

        \unlink((string)$masterpagePath);
    }

    #endregion content

    #region renderMetaTags -----------------------------------------------------

    function testRenderMetaTags()
    {
        $sut = $this->systemUnderTest();
        $metaItems = new CArray([
            'name' => new CArray([
                'description' => 'my description',
                'viewport' => 'my viewport'
            ]),
            'property' => new CArray([
                'og:locale' => 'my locale',
                'og:type' => 'website'
            ])
        ]);

        $this->assertSame(
            "\t" . '<meta name="description" content="my description">' . "\n"
          . "\t" . '<meta name="viewport" content="my viewport">' . "\n"
          . "\t" . '<meta property="og:locale" content="my locale">' . "\n"
          . "\t" . '<meta property="og:type" content="website">'
          , AccessHelper::CallMethod($sut, 'renderMetaTags', [$metaItems])
        );
    }

    #endregion renderMetaTags


    #region libraryStylesheetLinks ---------------------------------------------

    function testLibraryStylesheetLinks()
    {
        $sut = $this->systemUnderTest('resolveLibraryAssetUrl');

        $sut->expects($this->any())
            ->method('resolveLibraryAssetUrl')
            ->willReturnCallback(function($path, $extension) {
                return "url/to/{$path}.{$extension}";
            });

        $this->assertSame(
            "\t" . '<link rel="stylesheet" href="url/to/jquery-ui-1.12.1.custom/jquery-ui.css">' . "\n"
          . "\t" . '<link rel="stylesheet" href="url/to/bootstrap-4.6.2/css/bootstrap.css">' . "\n"
          . "\t" . '<link rel="stylesheet" href="url/to/bootstrap-icons-1.9.1/bootstrap-icons.css">' . "\n"
          . "\t" . '<link rel="stylesheet" href="url/to/dataTables-1.11.3/css/dataTables.bootstrap4.css">'
          , AccessHelper::CallMethod($sut, 'libraryStylesheetLinks', [$this->libraries()])
        );
    }

    #endregion libraryStylesheetLinks

    #region libraryJavascriptLinks ---------------------------------------------

    function testLibraryJavascriptLinks()
    {
        $sut = $this->systemUnderTest('resolveLibraryAssetUrl');

        $sut->expects($this->any())
            ->method('resolveLibraryAssetUrl')
            ->willReturnCallback(function($path, $extension) {
                return "url/to/{$path}.{$extension}";
            });

        $this->assertSame(
            "\t" . '<script src="url/to/jquery-3.5.1/jquery.js"></script>' . "\n"
          . "\t" . '<script src="url/to/jquery-ui-1.12.1.custom/jquery-ui.js"></script>' . "\n"
          . "\t" . '<script src="url/to/bootstrap-4.6.2/js/bootstrap.bundle.js"></script>' . "\n"
          . "\t" . '<script src="url/to/dataTables-1.11.3/js/jquery.dataTables.js"></script>' . "\n"
          . "\t" . '<script src="url/to/dataTables-1.11.3/js/dataTables.bootstrap4.js"></script>'
          , AccessHelper::CallMethod($sut, 'libraryJavascriptLinks', [$this->libraries()])
        );
    }

    #endregion libraryJavascriptLinks

    #region pageStylesheetLinks ------------------------------------------------

    function testPageStylesheetLinksInDebugMode()
    {
        $sut = $this->systemUnderTest('resolvePageAssetUrl');
        $page = $this->createMock(Page::class);
        $config = Config::Instance();

        $page->expects($this->once())
            ->method('Id')
            ->willReturn('home');
        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('IsDebug', false)
            ->willReturn(true);
        $page->expects($this->once())
            ->method('Manifest')
            ->willReturn($this->pageManifest());
        $sut->expects($this->any())
            ->method('resolvePageAssetUrl')
            ->willReturnCallback(function($pageId, $path, $extension) {
                if (\str_starts_with($path, 'http')) {
                    return $path;
                }
                return "url/to/pages/{$pageId}/{$path}.{$extension}";
            });

        $this->assertSame(
            "\t" . '<link rel="stylesheet" href="https://cdn.example.com/fonts.css">' . "\n"
          . "\t" . '<link rel="stylesheet" href="url/to/pages/home/index.css">' . "\n"
          . "\t" . '<link rel="stylesheet" href="url/to/pages/home/theme.css">'
          , AccessHelper::CallMethod($sut, 'pageStylesheetLinks', [$page])
        );
    }

    function testPageStylesheetLinksInProductionMode()
    {
        $sut = $this->systemUnderTest('isRemoteAsset', 'pageMinifiedAssetExists');
        $config = Config::Instance();
        $resource = Resource::Instance();
        $page = $this->createMock(Page::class);
        $pageId = 'home';

        $page->expects($this->once())
            ->method('Id')
            ->willReturn($pageId);
        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('IsDebug', false)
            ->willReturn(false);
        $page->expects($this->once())
            ->method('Manifest')
            ->willReturn($this->pageManifest());
        $sut->expects($this->any())
            ->method('isRemoteAsset')
            ->willReturnCallback(function($path) {
                return \str_starts_with($path, 'http');
            });
        $sut->expects($this->once())
            ->method('pageMinifiedAssetExists')
            ->with($pageId, 'css')
            ->willReturn(true);
        $resource->expects($this->once())
            ->method('PageFileUrl')
            ->with($pageId, 'page.min.css')
            ->willReturn(new CUrl('http://localhost/app/pages/home/page.min.css'));

        $this->assertSame(
            "\t" . '<link rel="stylesheet" href="https://cdn.example.com/fonts.css">' . "\n"
          . "\t" . '<link rel="stylesheet" href="http://localhost/app/pages/home/page.min.css">'
          , AccessHelper::CallMethod($sut, 'pageStylesheetLinks', [$page])
        );
    }

    #endregion pageStylesheetLinks

    #region pageJavascriptLinks ------------------------------------------------

    function testPageJavascriptLinksInDebugMode()
    {
        $sut = $this->systemUnderTest('resolvePageAssetUrl');
        $page = $this->createMock(Page::class);
        $config = Config::Instance();

        $page->expects($this->once())
            ->method('Id')
            ->willReturn('home');
        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('IsDebug', false)
            ->willReturn(true);
        $page->expects($this->once())
            ->method('Manifest')
            ->willReturn($this->pageManifest());
        $sut->expects($this->any())
            ->method('resolvePageAssetUrl')
            ->willReturnCallback(function($pageId, $path, $extension) {
                if (\str_starts_with($path, 'http')) {
                    return $path;
                }
                return "url/to/pages/{$pageId}/{$path}.{$extension}";
            });

        $this->assertSame(
            "\t" . '<script src="http://cdn.example.com/script.js"></script>' . "\n"
          . "\t" . '<script src="url/to/pages/home/Model.js"></script>' . "\n"
          . "\t" . '<script src="url/to/pages/home/View.js"></script>' . "\n"
          . "\t" . '<script src="url/to/pages/home/Controller.js"></script>'
          , AccessHelper::CallMethod($sut, 'pageJavascriptLinks', [$page])
        );
    }

    function testPageJavascriptLinksInProductionMode()
    {
        $sut = $this->systemUnderTest('isRemoteAsset', 'pageMinifiedAssetExists');
        $config = Config::Instance();
        $resource = Resource::Instance();
        $page = $this->createMock(Page::class);
        $pageId = 'home';

        $page->expects($this->once())
            ->method('Id')
            ->willReturn($pageId);
        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('IsDebug', false)
            ->willReturn(false);
        $page->expects($this->once())
            ->method('Manifest')
            ->willReturn($this->pageManifest());
        $sut->expects($this->any())
            ->method('isRemoteAsset')
            ->willReturnCallback(function($path) {
                return \str_starts_with($path, 'http');
            });
        $sut->expects($this->once())
            ->method('pageMinifiedAssetExists')
            ->with($pageId, 'js')
            ->willReturn(true);
        $resource->expects($this->once())
            ->method('PageFileUrl')
            ->with($pageId, 'page.min.js')
            ->willReturn(new CUrl('http://localhost/app/pages/home/page.min.js'));

        $this->assertSame(
            "\t" . '<script src="http://cdn.example.com/script.js"></script>' . "\n"
          . "\t" . '<script src="http://localhost/app/pages/home/page.min.js"></script>'
          , AccessHelper::CallMethod($sut, 'pageJavascriptLinks', [$page])
        );
    }

    #endregion pageJavascriptLinks

    #region resolveLibraryAssetUrl ---------------------------------------------

    function testResolveLibraryAssetUrlWhenPathIsRemote()
    {
        $sut = $this->systemUnderTest('isRemoteAsset', 'lowercaseExtension');
        $config = Config::Instance();
        $resource = Resource::Instance();
        $path = 'https://cdn.example.com/style.css';

        $sut->expects($this->once())
            ->method('isRemoteAsset')
            ->with($path)
            ->willReturn(true);
        $sut->expects($this->never())
            ->method('lowercaseExtension');
        $config->expects($this->never())
            ->method('OptionOrDefault');
        $resource->expects($this->never())
            ->method('FrontendLibraryFileUrl');

        $this->assertSame(
            $path,
            AccessHelper::CallMethod($sut, 'resolveLibraryAssetUrl', [
                $path,
                'css'
            ])
        );
    }

    #[DataProvider('resolveLibraryAssetUrlWhenPathIsLocalDataProvider')]
    function testResolveLibraryAssetUrlWhenPathIsLocal(
        string $expected,
        string $path,
        string $extension,
        string $lowercaseExtension,
        bool $isDebug
    ) {
        $sut = $this->systemUnderTest('isRemoteAsset', 'lowercaseExtension');
        $config = Config::Instance();
        $resource = Resource::Instance();
        $resolvedUrl = "http://localhost/app/frontend/{$expected}";

        $sut->expects($this->once())
            ->method('isRemoteAsset')
            ->with($path)
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('lowercaseExtension')
            ->with($path)
            ->willReturn($lowercaseExtension);
        if ($extension !== $lowercaseExtension) {
            $config->expects($this->once())
                ->method('OptionOrDefault')
                ->with('IsDebug', false)
                ->willReturn($isDebug);
        } else {
            $config->expects($this->never())
                ->method('OptionOrDefault');
        }
        $resource->expects($this->once())
            ->method('FrontendLibraryFileUrl')
            ->with($expected)
            ->willReturn(new CUrl($resolvedUrl));

        $this->assertSame(
            $resolvedUrl,
            AccessHelper::CallMethod(
                $sut,
                'resolveLibraryAssetUrl',
                [$path, $extension]
            )
        );
    }

    #endregion resolveLibraryAssetUrl

    #region resolvePageAssetUrl ------------------------------------------------

    function testResolvePageAssetUrlWhenPathIsRemote()
    {
        $sut = $this->systemUnderTest('isRemoteAsset', 'lowercaseExtension');
        $resource = Resource::Instance();
        $path = 'https://cdn.example.com/script.js';

        $sut->expects($this->once())
            ->method('isRemoteAsset')
            ->with($path)
            ->willReturn(true);
        $sut->expects($this->never())
            ->method('lowercaseExtension');
        $resource->expects($this->never())
            ->method('PageFileUrl');

        $this->assertSame(
            $path,
            AccessHelper::CallMethod($sut, 'resolvePageAssetUrl', [
                'home',
                $path,
                'js'
            ])
        );
    }

    #[DataProvider('resolvePageAssetUrlWhenPathIsLocalDataProvider')]
    function testResolvePageAssetUrlWhenPathIsLocal(
        string $expected,
        string $path,
        string $extension,
        string $lowercaseExtension
    ) {
        $sut = $this->systemUnderTest('isRemoteAsset', 'lowercaseExtension');
        $resource = Resource::Instance();
        $pageId = 'home';
        $pageAssetUrl = "http://localhost/app/pages/{$pageId}/{$expected}";

        $sut->expects($this->once())
            ->method('isRemoteAsset')
            ->with($path)
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('lowercaseExtension')
            ->with($path)
            ->willReturn($lowercaseExtension);
        $resource->expects($this->once())
            ->method('PageFileUrl')
            ->with($pageId, $expected)
            ->willReturn(new CUrl($pageAssetUrl));

        $this->assertSame(
            $pageAssetUrl,
            AccessHelper::CallMethod(
                $sut,
                'resolvePageAssetUrl',
                [$pageId, $path, $extension]
            )
        );
    }

    #endregion resolvePageAssetUrl

    #region isRemoteAsset ------------------------------------------------------

    #[DataProvider('isRemoteAssetDataProvider')]
    function testIsRemoteAsset($expected, $path)
    {
        $sut = $this->systemUnderTest();

        $this->assertSame(
            $expected,
            AccessHelper::CallMethod($sut, 'isRemoteAsset', [$path])
        );
    }

    #endregion isRemoteAsset

    #region lowercaseExtension -------------------------------------------------

    #[DataProvider('lowercaseExtensionDataProvider')]
    function testLowercaseExtension($expected, $path)
    {
        $sut = $this->systemUnderTest();

        $this->assertSame(
            $expected,
            AccessHelper::CallMethod($sut, 'lowercaseExtension', [$path])
        );
    }

    #endregion lowercaseExtension

    #region pageMinifiedAssetExists --------------------------------------------

    function testPageMinifiedAssetExistsReturnsTrueIfFileExists()
    {
        $sut = $this->systemUnderTest();
        $resource = Resource::Instance();
        $pageId = 'home';
        $extension = 'css';
        $path = $this->createMock(CPath::class);

        $resource->expects($this->once())
            ->method('PageFilePath')
            ->with($pageId, "page.min.{$extension}")
            ->willReturn($path);
        $path->expects($this->once())
            ->method('IsFile')
            ->willReturn(true);

        $this->assertTrue(AccessHelper::CallMethod(
            $sut,
            'pageMinifiedAssetExists',
            [$pageId, $extension]
        ));
    }

    function testPageMinifiedAssetExistsReturnsFalseIfFileDoesNotExist()
    {
        $sut = $this->systemUnderTest();
        $resource = Resource::Instance();
        $pageId = 'home';
        $extension = 'css';
        $path = $this->createMock(CPath::class);

        $resource->expects($this->once())
            ->method('PageFilePath')
            ->with($pageId, "page.min.{$extension}")
            ->willReturn($path);
        $path->expects($this->once())
            ->method('IsFile')
            ->willReturn(false);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'pageMinifiedAssetExists',
            [$pageId, $extension]
        ));
    }

    #endregion pageMinifiedAssetExists

    #region Data Providers -----------------------------------------------------

    static function resolveLibraryAssetUrlWhenPathIsLocalDataProvider()
    {
        // expected
        // path
        // extension
        // lowercaseExtension
        // isDebug
        return [
            'no extension, debug'
                => ['lib/foo.css', 'lib/foo', 'css', '', true],
            'no extension, production'
                => ['lib/foo.min.css', 'lib/foo', 'css', '', false],
            'correct extension, debug'
                => ['lib/foo.css', 'lib/foo.css', 'css', 'css', true],
            'correct extension, production'
                => ['lib/foo.css', 'lib/foo.css', 'css', 'css', false],
            'wrong extension, debug'
                => ['lib/foo.css', 'lib/foo', 'css', 'js', true],
            'wrong extension, production'
                => ['lib/foo.min.css', 'lib/foo', 'css', 'js', false],
        ];
    }

    static function resolvePageAssetUrlWhenPathIsLocalDataProvider()
    {
        // expected
        // path
        // extension
        // lowercaseExtension
        return [
            'no extension'
                => ['foo.css', 'foo', 'css', ''],
            'correct extension'
                => ['foo.css', 'foo.css', 'css', 'css'],
            'wrong extension'
                => ['foo.js.css', 'foo.js', 'css', 'js'],
        ];
    }

    static function isRemoteAssetDataProvider()
    {
        // expected
        // path
        return [
            [true, 'http://example.com/assets/style.css'],
            [true, 'https://example.com/assets/style.css'],
            [true, 'http://localhost/app/frontend/assets/style.css'],
            [true, 'https://localhost/app/frontend/assets/style.css'],
            [false, 'assets/style.css'],
        ];
    }

    static function lowercaseExtensionDataProvider()
    {
        // expected
        // path
        return [
            ['css', 'assets/style.css'],
            ['css', 'assets/style.CSS'],
            ['css', 'assets/style.min.css'],
            ['css', 'assets/style.min.CSS'],
            ['css', 'assets/style..css'],
            ['css', '.css'],
            ['', 'assets/style.'],
            ['', 'assets/style'],
        ];
    }

    #endregion Data Providers
}
