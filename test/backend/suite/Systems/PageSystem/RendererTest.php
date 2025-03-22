<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\PageSystem\Renderer;

use \Harmonia\Config;
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Logger;
use \Peneus\Resource;
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
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
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
        $sut = $this->systemUnderTest('openFile', 'masterContents', '_echo');
        $resource = Resource::Instance();
        $file = $this->createMock(CFile::class);
        $config = Config::Instance();
        $page = $this->createMock(Page::class);

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
                </head>
                <body>
                	{{Contents}}
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
        $sut->expects($this->once())
            ->method('masterContents')
            ->with($page)
            ->willReturn('	Welcome to MyWebsite!');
        $sut->expects($this->once())
            ->method('_echo')
            ->with(<<<HTML
                <!DOCTYPE html>
                <html lang="tr">
                <head>
                	<meta charset="utf-8">
                	<title>Home | MyWebsite</title>
                </head>
                <body>
                	Welcome to MyWebsite!
                </body>
                </html>
            HTML);

        $sut->Render($page);
    }

    #endregion Render

    #region masterContents -----------------------------------------------------

    function testMasterContentsWhenMasterpageNameIsEmpty()
    {
        $sut = $this->systemUnderTest('_ob_start', '_ob_get_clean', '_echo');
        $page = $this->createMock(Page::class);

        $sut->expects($this->once())
            ->method('_ob_start');
        $page->expects($this->once())
            ->method('Masterpage')
            ->willReturn('');
        $page->expects($this->once())
            ->method('Contents')
            ->willReturn('Welcome to MyWebsite!');
        $sut->expects($this->once())
            ->method('_echo')
            ->with('Welcome to MyWebsite!');
        $sut->expects($this->once())
            ->method('_ob_get_clean')
            ->willReturn('Welcome to MyWebsite!');

        $this->assertSame(
            'Welcome to MyWebsite!',
            AccessHelper::CallMethod($sut, 'masterContents', [$page])
        );
    }

    function testMasterContentsWhenMasterpageDoesNotExist()
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
            AccessHelper::CallMethod($sut, 'masterContents', [$page])
        );
    }

    function testMasterContentsWhenMasterpageExists()
    {
        $sut = $this->systemUnderTest();
        $page = $this->createMock(Page::class);
        $resource = Resource::Instance();
        $masterpagePath = new CPath(__DIR__ . '/test.masterpage.php');

        \file_put_contents((string)$masterpagePath, <<<PHP
            <h1>This is a test masterpage.</h1>
            <?=\$this->Contents()?>
            <footer>&copy; 2025</footer>
        PHP);

        $page->expects($this->once())
            ->method('Masterpage')
            ->willReturn('test.masterpage');
        $resource->expects($this->once())
            ->method('MasterpageFilePath')
            ->with('test.masterpage')
            ->willReturn($masterpagePath);
        $page->expects($this->once())
            ->method('Contents')
            ->willReturn("Hello, World!\n");

        $result = AccessHelper::CallMethod($sut, 'masterContents', [$page]);

        $this->assertSame(<<<HTML
            <h1>This is a test masterpage.</h1>
            Hello, World!
            <footer>&copy; 2025</footer>
        HTML, $result);

        \unlink((string)$masterpagePath);
    }

    #endregion masterContents
}
