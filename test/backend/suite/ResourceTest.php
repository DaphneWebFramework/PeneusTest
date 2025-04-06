<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Resource;

use \Harmonia\Core\CFileSystem;
use \Harmonia\Core\CPath;
use \Harmonia\Core\CUrl;
use \Harmonia\Resource as _BaseResource;
use \TestToolkit\AccessHelper;

#[CoversClass(Resource::class)]
class ResourceTest extends TestCase
{
    private ?_BaseResource $originalBaseResource = null;
    private ?CFileSystem $originalFileSystem = null;

    protected function setUp(): void
    {
        $this->originalBaseResource =
            _BaseResource::ReplaceInstance($this->createMock(_BaseResource::class));
        $this->originalFileSystem =
            CFileSystem::ReplaceInstance($this->createMock(CFileSystem::class));
    }

    protected function tearDown(): void
    {
        _BaseResource::ReplaceInstance($this->originalBaseResource);
    }

    private function systemUnderTest(string ...$mockedMethods): Resource
    {
        $sut = $this->getMockBuilder(Resource::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
        return AccessHelper::CallConstructor($sut);
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

    function testFrontendLibraryFileUrlWithoutCacheBusterIfFileMissing()
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

    #region PageDirectoryPath --------------------------------------------------

    function testPageDirectoryPath()
    {
        $sut = $this->systemUnderTest();
        $baseResource = _BaseResource::Instance();

        $baseResource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('pages')
            ->willReturn(new CPath('path/to/pages'));

        $this->assertEquals(
            'path/to/pages' . \DIRECTORY_SEPARATOR . 'mypage',
            $sut->PageDirectoryPath('mypage')
        );
    }

    #endregion PageDirectoryPath

    #region PageDirectoryUrl ---------------------------------------------------

    function testPageDirectoryUrl()
    {
        $sut = $this->systemUnderTest();
        $baseResource = _BaseResource::Instance();

        $baseResource->expects($this->once())
            ->method('AppSubdirectoryUrl')
            ->with('pages')
            ->willReturn(new CUrl('https://example.com/pages'));

        $this->assertEquals(
            'https://example.com/pages/mypage/',
            $sut->PageDirectoryUrl('mypage')
        );
    }

    #endregion PageDirectoryUrl

    #region PageFilePath -------------------------------------------------------

    function testPageFilePath()
    {
        $sut = $this->systemUnderTest('PageDirectoryPath');
        $pageId = 'mypage';
        $relativePath = 'script.js';
        $pageDirectoryPath = 'path/to/pages' . \DIRECTORY_SEPARATOR . $pageId;

        $sut->expects($this->once())
            ->method('PageDirectoryPath')
            ->with($pageId)
            ->willReturn(new CPath($pageDirectoryPath));

        $this->assertEquals(
            $pageDirectoryPath . \DIRECTORY_SEPARATOR . $relativePath,
            $sut->PageFilePath($pageId, $relativePath)
        );
    }

    #endregion PageFilePath

    #region PageFileUrl --------------------------------------------------------

    function testPageFileUrlAppendsCacheBuster()
    {
        $sut = $this->systemUnderTest('PageDirectoryUrl', 'PageFilePath');
        $baseResource = _BaseResource::Instance();
        $fileSystem = CFileSystem::Instance();
        $pageId = 'mypage';
        $relativePath = 'style.css';
        $pageFilePath = "path/to/pages"
            . \DIRECTORY_SEPARATOR
            . $pageId
            . \DIRECTORY_SEPARATOR
            . $relativePath;
        $pageDirectoryUrl = "https://example.com/pages/{$pageId}/";

        $sut->expects($this->once())
            ->method('PageDirectoryUrl')
            ->with($pageId)
            ->willReturn(new CUrl($pageDirectoryUrl));
        $sut->expects($this->once())
            ->method('PageFilePath')
            ->with($pageId, $relativePath)
            ->willReturn(new CPath($pageFilePath));
        $fileSystem->expects($this->once())
            ->method('ModificationTime')
            ->with(new CPath($pageFilePath))
            ->willReturn(1712345678);

        $this->assertEquals(
            "{$pageDirectoryUrl}{$relativePath}?1712345678",
            $sut->PageFileUrl($pageId, $relativePath)
        );
    }

    function testPageFileUrlWithoutCacheBusterIfFileMissing()
    {
        $sut = $this->systemUnderTest('PageDirectoryUrl', 'PageFilePath');
        $baseResource = _BaseResource::Instance();
        $fileSystem = CFileSystem::Instance();
        $pageId = 'mypage';
        $relativePath = 'style.css';
        $pageFilePath = "path/to/pages"
            . \DIRECTORY_SEPARATOR
            . $pageId
            . \DIRECTORY_SEPARATOR
            . $relativePath;
        $pageDirectoryUrl = "https://example.com/pages/{$pageId}/";

        $sut->expects($this->once())
            ->method('PageDirectoryUrl')
            ->with($pageId)
            ->willReturn(new CUrl($pageDirectoryUrl));
        $sut->expects($this->once())
            ->method('PageFilePath')
            ->with($pageId, $relativePath)
            ->willReturn(new CPath($pageFilePath));
        $fileSystem->expects($this->once())
            ->method('ModificationTime')
            ->with(new CPath($pageFilePath))
            ->willReturn(0); // simulates missing or unreadable file

        $this->assertEquals(
            "{$pageDirectoryUrl}{$relativePath}",
            $sut->PageFileUrl($pageId, $relativePath)
        );
    }

    #endregion PageFileUrl
}
