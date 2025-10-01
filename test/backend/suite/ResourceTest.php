<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Resource;

use \Harmonia\Core\CFileSystem;
use \Harmonia\Core\CPath;
use \Harmonia\Core\CString;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\StatusCode;
use \Harmonia\Resource as _BaseResource;
use \Harmonia\Server;
use \TestToolkit\AccessHelper;

#[CoversClass(Resource::class)]
class ResourceTest extends TestCase
{
    private ?_BaseResource $originalBaseResource = null;
    private ?CFileSystem $originalFileSystem = null;
    private ?Server $originalServer = null;

    protected function setUp(): void
    {
        $this->originalBaseResource =
            _BaseResource::ReplaceInstance($this->createMock(_BaseResource::class));
        $this->originalFileSystem =
            CFileSystem::ReplaceInstance($this->createMock(CFileSystem::class));
        $this->originalServer =
            Server::ReplaceInstance($this->createMock(Server::class));
    }

    protected function tearDown(): void
    {
        _BaseResource::ReplaceInstance($this->originalBaseResource);
        CFileSystem::ReplaceInstance($this->originalFileSystem);
        Server::ReplaceInstance($this->originalServer);
    }

    private function systemUnderTest(string ...$mockedMethods): Resource
    {
        $mock = $this->getMockBuilder(Resource::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
        return AccessHelper::CallConstructor($mock);
    }

    #region __call -------------------------------------------------------------

    function testCallDelegatesToBaseWhenMethodExists()
    {
        $sut = $this->systemUnderTest();
        $baseResource = _BaseResource::Instance();

        $baseResource->expects($this->once())
            ->method('AppUrl')
            ->willReturn(new CUrl('https://example.com/app/'));

        $this->assertEquals('https://example.com/app/', $sut->AppUrl());
    }

    function testCallThrowsWhenBaseMethodDoesNotExist()
    {
        $sut = $this->systemUnderTest();

        $this->expectException(\Error::class);
        $sut->Nonexistent();
    }

    #endregion __call

    #region TemplateFilePath ---------------------------------------------------

    function testTemplateFilePath()
    {
        $sut = $this->systemUnderTest();
        $baseResource = _BaseResource::Instance();

        $baseResource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('templates')
            ->willReturn(new CPath('path/to/templates'));

        $this->assertEquals(
            'path/to/templates' . \DIRECTORY_SEPARATOR . 'page.html',
            $sut->TemplateFilePath('page')
        );
    }

    #endregion TemplateFilePath

    #region MasterpageFilePath -------------------------------------------------

    function testMasterpageFilePath()
    {
        $sut = $this->systemUnderTest();
        $baseResource = _BaseResource::Instance();

        $baseResource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('masterpages')
            ->willReturn(new CPath('path/to/masterpages'));

        $this->assertEquals(
            'path/to/masterpages' . \DIRECTORY_SEPARATOR . 'basic.php',
            $sut->MasterpageFilePath('basic')
        );
    }

    #endregion MasterpageFilePath

    #region FrontendManifestFilePath -------------------------------------------

    function testFrontendManifestFilePath()
    {
        $sut = $this->systemUnderTest();
        $baseResource = _BaseResource::Instance();

        $baseResource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('frontend')
            ->willReturn(new CPath('path/to/frontend'));

        $this->assertEquals(
            'path/to/frontend' . \DIRECTORY_SEPARATOR . 'manifest.json',
            $sut->FrontendManifestFilePath()
        );
    }

    #endregion FrontendManifestFilePath

    #region FrontendLibraryFileUrl ---------------------------------------------

    function testFrontendLibraryFileUrlAppendsCacheBuster()
    {
        $sut = $this->systemUnderTest();
        $baseResource = _BaseResource::Instance();
        $fileSystem = CFileSystem::Instance();

        $baseResource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('frontend')
            ->willReturn(new CPath('path/to/frontend'));
        $baseResource->expects($this->once())
            ->method('AppSubdirectoryUrl')
            ->with('frontend')
            ->willReturn(new CUrl('https://example.com/frontend'));
        $fileSystem->expects($this->once())
            ->method('ModificationTime')
            ->with(new CPath('path/to/frontend'
                           . \DIRECTORY_SEPARATOR
                           . 'bootstrap-5.3.3/css/bootstrap.css'))
            ->willReturn(1711234567);

        $this->assertEquals(
            'https://example.com/frontend/bootstrap-5.3.3/css/bootstrap.css?1711234567',
            $sut->FrontendLibraryFileUrl('bootstrap-5.3.3/css/bootstrap.css')
        );
    }

    function testFrontendLibraryFileUrlWithoutCacheBusterIfFileIsMissing()
    {
        $sut = $this->systemUnderTest();
        $baseResource = _BaseResource::Instance();
        $fileSystem = CFileSystem::Instance();

        $baseResource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('frontend')
            ->willReturn(new CPath('path/to/frontend'));
        $baseResource->expects($this->once())
            ->method('AppSubdirectoryUrl')
            ->with('frontend')
            ->willReturn(new CUrl('https://example.com/frontend'));
        $fileSystem->expects($this->once())
            ->method('ModificationTime')
            ->with(new CPath('path/to/frontend'
                           . \DIRECTORY_SEPARATOR
                           . 'bootstrap/css/bootstrap.min.css'))
            ->willReturn(0); // simulates missing or unreadable file

        $this->assertEquals(
            'https://example.com/frontend/bootstrap/css/bootstrap.min.css',
            $sut->FrontendLibraryFileUrl('bootstrap/css/bootstrap.min.css')
        );
    }

    #endregion FrontendLibraryFileUrl

    #region PagePath -----------------------------------------------------------

    function testPagePath()
    {
        $sut = $this->systemUnderTest();
        $baseResource = _BaseResource::Instance();

        $baseResource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('pages')
            ->willReturn(new CPath('path/to/pages'));

        $this->assertEquals(
            'path/to/pages' . \DIRECTORY_SEPARATOR . 'mypage',
            $sut->PagePath('mypage')
        );
    }

    #endregion PagePath

    #region PageUrl ------------------------------------------------------------

    function testPageUrl()
    {
        $sut = $this->systemUnderTest();
        $baseResource = _BaseResource::Instance();

        $baseResource->expects($this->once())
            ->method('AppSubdirectoryUrl')
            ->with('pages')
            ->willReturn(new CUrl('https://example.com/pages'));

        $this->assertEquals(
            'https://example.com/pages/mypage/',
            $sut->PageUrl('mypage')
        );
    }

    #endregion PageUrl

    #region LoginPageUrl -------------------------------------------------------

    function testLoginPageUrlAppendsRedirectParameter()
    {
        $sut = $this->systemUnderTest('PageUrl');
        $server = Server::Instance();

        $sut->expects($this->once())
            ->method('PageUrl')
            ->with('login')
            ->willReturn(new CUrl('https://example.com/pages/login/'));
        $server->expects($this->once())
            ->method('RequestUri')
            ->willReturn(new CString('/pages/home/'));

        $this->assertEquals(
            'https://example.com/pages/login/?redirect=%2Fpages%2Fhome%2F',
            $sut->LoginPageUrl()
        );
    }

    function testLoginPageUrlDoesNotAppendRedirectParameter()
    {
        $sut = $this->systemUnderTest('PageUrl');
        $server = Server::Instance();

        $sut->expects($this->once())
            ->method('PageUrl')
            ->with('login')
            ->willReturn(new CUrl('https://example.com/pages/login/'));
        $server->expects($this->once())
            ->method('RequestUri')
            ->willReturn(null);

        $this->assertEquals(
            'https://example.com/pages/login/',
            $sut->LoginPageUrl()
        );
    }

    function testLoginPageUrlWithCustomRedirectPageId()
    {
        $sut = $this->systemUnderTest('PageUrl');
        $server = Server::Instance();

        $sut->expects($this->exactly(2))
            ->method('PageUrl')
            ->willReturnCallback(function(string $pageId) {
                return match ($pageId) {
                    'login' =>
                        new CUrl('https://example.com/pages/login/'),
                    'home' =>
                        new CUrl('https://example.com/pages/home/'),
                    default =>
                        $this->fail("Unexpected page ID: $pageId")
                };
            });
        $server->expects($this->never())
            ->method('RequestUri');

        $this->assertEquals(
            'https://example.com/pages/login/?redirect=%2Fpages%2Fhome%2F',
            $sut->LoginPageUrl('home')
        );
    }

    #endregion LoginPageUrl

    #region ErrorPageUrl ------------------------------------------------------

    function testErrorPageUrl()
    {
        $sut = $this->systemUnderTest('PageUrl');

        $sut->expects($this->once())
            ->method('PageUrl')
            ->with('error')
            ->willReturn(new CUrl('https://example.com/pages/error/'));

        $this->assertEquals(
            'https://example.com/pages/error/404',
            $sut->ErrorPageUrl(StatusCode::NotFound)
        );
    }

    #endregion ErrorPageUrl

    #region PageFilePath -------------------------------------------------------

    function testPageFilePath()
    {
        $sut = $this->systemUnderTest('PagePath');
        $pageId = 'mypage';
        $relativePath = 'script.js';
        $pagePath = 'path/to/pages' . \DIRECTORY_SEPARATOR . $pageId;

        $sut->expects($this->once())
            ->method('PagePath')
            ->with($pageId)
            ->willReturn(new CPath($pagePath));

        $this->assertEquals(
            $pagePath . \DIRECTORY_SEPARATOR . $relativePath,
            $sut->PageFilePath($pageId, $relativePath)
        );
    }

    #endregion PageFilePath

    #region PageFileUrl --------------------------------------------------------

    function testPageFileUrlAppendsCacheBuster()
    {
        $sut = $this->systemUnderTest('PageUrl', 'PageFilePath');
        $baseResource = _BaseResource::Instance();
        $fileSystem = CFileSystem::Instance();
        $pageId = 'mypage';
        $relativePath = 'style.css';
        $pageFilePath = "path/to/pages"
            . \DIRECTORY_SEPARATOR
            . $pageId
            . \DIRECTORY_SEPARATOR
            . $relativePath;
        $pageUrl = "https://example.com/pages/{$pageId}/";

        $sut->expects($this->once())
            ->method('PageUrl')
            ->with($pageId)
            ->willReturn(new CUrl($pageUrl));
        $sut->expects($this->once())
            ->method('PageFilePath')
            ->with($pageId, $relativePath)
            ->willReturn(new CPath($pageFilePath));
        $fileSystem->expects($this->once())
            ->method('ModificationTime')
            ->with(new CPath($pageFilePath))
            ->willReturn(1712345678);

        $this->assertEquals(
            "{$pageUrl}{$relativePath}?1712345678",
            $sut->PageFileUrl($pageId, $relativePath)
        );
    }

    function testPageFileUrlWithoutCacheBusterIfFileIsMissing()
    {
        $sut = $this->systemUnderTest('PageUrl', 'PageFilePath');
        $baseResource = _BaseResource::Instance();
        $fileSystem = CFileSystem::Instance();
        $pageId = 'mypage';
        $relativePath = 'style.css';
        $pageFilePath = "path/to/pages"
            . \DIRECTORY_SEPARATOR
            . $pageId
            . \DIRECTORY_SEPARATOR
            . $relativePath;
        $pageUrl = "https://example.com/pages/{$pageId}/";

        $sut->expects($this->once())
            ->method('PageUrl')
            ->with($pageId)
            ->willReturn(new CUrl($pageUrl));
        $sut->expects($this->once())
            ->method('PageFilePath')
            ->with($pageId, $relativePath)
            ->willReturn(new CPath($pageFilePath));
        $fileSystem->expects($this->once())
            ->method('ModificationTime')
            ->with(new CPath($pageFilePath))
            ->willReturn(0); // simulates missing or unreadable file

        $this->assertEquals(
            "{$pageUrl}{$relativePath}",
            $sut->PageFileUrl($pageId, $relativePath)
        );
    }

    #endregion PageFileUrl
}
