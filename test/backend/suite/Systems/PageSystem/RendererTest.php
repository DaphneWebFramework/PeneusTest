<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Systems\PageSystem\Renderer;

use \Harmonia\Config;
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Core\CSequentialArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Logger;
use \Peneus\Resource;
use \Peneus\Systems\PageSystem\LibraryItem;
use \Peneus\Systems\PageSystem\Page;
use \TestToolkit\AccessHelper;

#[CoversClass(Renderer::class)]
class RendererTest extends TestCase
{
    private ?Resource $originalResource = null;
    private ?Config $originalConfig = null;
    private ?Logger $originalLogger = null;

    protected function setUp(): void
    {
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalLogger =
            Logger::ReplaceInstance($this->createStub(Logger::class));
    }

    protected function tearDown(): void
    {
        Resource::ReplaceInstance($this->originalResource);
        Config::ReplaceInstance($this->originalConfig);
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
                'Name' => 'jquery',
                'Css' => ['jquery-ui-1.12.1.custom/jquery-ui'],
                'Js' => [
                    'jquery-3.5.1/jquery',
                    'jquery-ui-1.12.1.custom/jquery-ui'
                ],
                'Extras' => [],
                'IsDefault' => true
            ]),
            $this->createConfiguredMock(LibraryItem::class, [
                'Name' => 'bootstrap',
                'Css' => ['bootstrap-4.6.2/css/bootstrap'],
                'Js' => ['bootstrap-4.6.2/js/bootstrap.bundle'],
                'Extras' => [
                    'bootstrap-4.6.2/css/bootstrap.min.css.map',
                    'bootstrap-4.6.2/js/bootstrap.bundle.min.js.map'
                ],
                'IsDefault' => true
            ]),
            $this->createConfiguredMock(LibraryItem::class, [
                'Name' => 'bootstrap-icons',
                'Css' => ['bootstrap-icons-1.9.1/bootstrap-icons'],
                'Js' => [],
                'Extras' => ['bootstrap-icons-1.9.1/fonts/*'],
                'IsDefault' => true
            ]),
            $this->createConfiguredMock(LibraryItem::class, [
                'Name' => 'dataTables',
                'Css' => ['dataTables-1.11.3/css/dataTables.bootstrap4'],
                'Js' => [
                    'dataTables-1.11.3/js/jquery.dataTables',
                    'dataTables-1.11.3/js/dataTables.bootstrap4'
                ],
                'Extras' => ['dataTables-1.11.3/i18n/*.json'],
                'IsDefault' => false
            ]),
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
            'libraryJavascriptLinks',
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
                	{{LibraryStylesheetLinks}}
                </head>
                <body>
                	{{Content}}
                	{{LibraryJavascriptLinks}}
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
            ->method('libraryStylesheetLinks')
            ->with($libraries)
            ->willReturn('	<link rel="stylesheet" href="url/to/bootstrap-4.6.2/css/bootstrap.css">');
        $sut->expects($this->once())
            ->method('content')
            ->with($page)
            ->willReturn('	Welcome to MyWebsite!');
        $sut->expects($this->once())
            ->method('libraryJavascriptLinks')
            ->with($libraries)
            ->willReturn('	<script src="url/to/bootstrap-4.6.2/js/bootstrap.bundle.js"></script>');
        $sut->expects($this->once())
            ->method('_echo')
            ->with(<<<HTML
                <!DOCTYPE html>
                <html lang="tr">
                <head>
                	<meta charset="utf-8">
                	<title>Home | MyWebsite</title>
                	<link rel="stylesheet" href="url/to/bootstrap-4.6.2/css/bootstrap.css">
                </head>
                <body>
                	Welcome to MyWebsite!
                	<script src="url/to/bootstrap-4.6.2/js/bootstrap.bundle.js"></script>
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

    #region libraryStylesheetLinks ---------------------------------------------

    function testLibraryStylesheetLinks()
    {
        $sut = $this->systemUnderTest('resolveAssetUrl');
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('IsDebug', false)
            ->willReturn(true);
        $sut->expects($this->any())
            ->method('resolveAssetUrl')
            ->willReturnCallback(function($path, $extension, $isDebug) {
                return "url/to/{$path}.{$extension}";
            });

        $this->assertSame(
            "\t<link rel=\"stylesheet\" href=\"url/to/jquery-ui-1.12.1.custom/jquery-ui.css\">\n"
          . "\t<link rel=\"stylesheet\" href=\"url/to/bootstrap-4.6.2/css/bootstrap.css\">\n"
          . "\t<link rel=\"stylesheet\" href=\"url/to/bootstrap-icons-1.9.1/bootstrap-icons.css\">\n"
          . "\t<link rel=\"stylesheet\" href=\"url/to/dataTables-1.11.3/css/dataTables.bootstrap4.css\">"
          , AccessHelper::CallMethod($sut, 'libraryStylesheetLinks', [$this->libraries()])
        );
    }

    #endregion libraryStylesheetLinks

    #region libraryJavascriptLinks ---------------------------------------------

    function testLibraryJavascriptLinks()
    {
        $sut = $this->systemUnderTest('resolveAssetUrl');
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('IsDebug', false)
            ->willReturn(true);
        $sut->expects($this->any())
            ->method('resolveAssetUrl')
            ->willReturnCallback(function($path, $extension, $isDebug) {
                return "url/to/{$path}.{$extension}";
            });

        $this->assertSame(
            "\t<script src=\"url/to/jquery-3.5.1/jquery.js\"></script>\n"
          . "\t<script src=\"url/to/jquery-ui-1.12.1.custom/jquery-ui.js\"></script>\n"
          . "\t<script src=\"url/to/bootstrap-4.6.2/js/bootstrap.bundle.js\"></script>\n"
          . "\t<script src=\"url/to/dataTables-1.11.3/js/jquery.dataTables.js\"></script>\n"
          . "\t<script src=\"url/to/dataTables-1.11.3/js/dataTables.bootstrap4.js\"></script>"
          , AccessHelper::CallMethod($sut, 'libraryJavascriptLinks', [$this->libraries()])
        );
    }

    #endregion libraryJavascriptLinks

    #region resolveAssetUrl ----------------------------------------------------

    function testResolveAssetUrlWhenPathIsHttpUrl()
    {
        $sut = $this->systemUnderTest();

        $this->assertSame(
            'http://cdn.example.com/assets/style.css',
            AccessHelper::CallMethod($sut, 'resolveAssetUrl', [
                'http://cdn.example.com/assets/style.css',
                'css',
                true
            ])
        );
    }

    function testResolveAssetUrlWhenPathIsHttpsUrl()
    {
        $sut = $this->systemUnderTest();

        $this->assertSame(
            'https://cdn.example.com/assets/style.css',
            AccessHelper::CallMethod($sut, 'resolveAssetUrl', [
                'https://cdn.example.com/assets/style.css',
                'css',
                true
            ])
        );
    }

    #[DataProvider('resolveAssetUrlDataProvider')]
    function testResolveAssetUrlWhenPathIsLocal($expected, $path, $extension, $isDebug)
    {
        $sut = $this->systemUnderTest();
        $resource = Resource::Instance();

        $resource->expects($this->once())
            ->method('FrontendLibraryFileUrl')
            ->with($expected)
            ->willReturn(new CUrl("http://localhost/app/frontend/{$expected}"));

        $this->assertSame(
            "http://localhost/app/frontend/{$expected}",
            AccessHelper::CallMethod($sut, 'resolveAssetUrl', [
                $path,
                $extension,
                $isDebug
            ])
        );
    }

    #endregion resolveAssetUrl

    #region Data Providers -----------------------------------------------------

    static function resolveAssetUrlDataProvider()
    {
        return [
        // No extension
            ['bootstrap/css/bootstrap.css',     'bootstrap/css/bootstrap', 'css', true],
            ['bootstrap/css/bootstrap.min.css', 'bootstrap/css/bootstrap', 'css', false],
            ['jquery/js/jquery.js',             'jquery/js/jquery',        'js',  true],
            ['jquery/js/jquery.min.js',         'jquery/js/jquery',        'js',  false],
        // Already has extension
            ['bootstrap/css/bootstrap.css', 'bootstrap/css/bootstrap.css', 'css', true],
            ['bootstrap/css/bootstrap.css', 'bootstrap/css/bootstrap.css', 'css', false],
            ['jquery/js/jquery.js',         'jquery/js/jquery.js',         'js',  true],
            ['jquery/js/jquery.js',         'jquery/js/jquery.js',         'js',  false],
        // Already has minified extension
            ['bootstrap/css/bootstrap.min.css', 'bootstrap/css/bootstrap.min.css', 'css', true],
            ['bootstrap/css/bootstrap.min.css', 'bootstrap/css/bootstrap.min.css', 'css', false],
            ['jquery/js/jquery.min.js',         'jquery/js/jquery.min.js',         'js',  true],
            ['jquery/js/jquery.min.js',         'jquery/js/jquery.min.js',         'js',  false],
        // Case-insensitive extensions
            ['bootstrap/css/bootstrap.CSS', 'bootstrap/css/bootstrap.CSS', 'css', true],
            ['jquery/js/jquery.JS',         'jquery/js/jquery.JS',         'js',  false],
            ['jquery/js/jquery.Min.Js',     'jquery/js/jquery.Min.Js',     'js',  false],
        // No real extension
            ['lib/selectize.v5.min.css',     'lib/selectize.v5',         'css', false],
            ['bootstrap/css/bootstrap..css', 'bootstrap/css/bootstrap.', 'css', true],
            ['jquery/js/jquery.min..min.js', 'jquery/js/jquery.min.',    'js',  false],
        ];
    }

    #endregion Data Providers
}
