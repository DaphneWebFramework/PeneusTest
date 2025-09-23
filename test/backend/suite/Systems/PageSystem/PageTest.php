<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\PageSystem\Page;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CSequentialArray;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\Security\CsrfToken;
use \Harmonia\Services\SecurityService;
use \Peneus\Api\Guards\FormTokenGuard;
use \Peneus\Model\Account;
use \Peneus\Model\Role;
use \Peneus\Systems\PageSystem\AuthManager;
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
    private ?AuthManager $authManager = null;
    private ?Config $originalConfig = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->renderer = $this->createMock(Renderer::class);
        $this->libraryManager = $this->createMock(LibraryManager::class);
        $this->pageManifest = $this->createMock(PageManifest::class);
        $this->metaCollection = $this->createMock(MetaCollection::class);
        $this->authManager = $this->createMock(AuthManager::class);
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
    }

    protected function tearDown(): void
    {
        $this->renderer = null;
        $this->libraryManager = null;
        $this->pageManifest = null;
        $this->metaCollection = null;
        $this->authManager = null;
        Config::ReplaceInstance($this->originalConfig);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): Page
    {
        return $this->getMockBuilder(Page::class)
            ->setConstructorArgs([
                __DIR__,
                $this->renderer,
                $this->libraryManager,
                $this->pageManifest,
                $this->metaCollection,
                $this->authManager
            ])
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region Id -----------------------------------------------------------------

    function testId()
    {
        $sut = $this->systemUnderTest();

        // Note that `$sut` was constructed with `__DIR__` in `systemUnderTest`.
        $this->assertSame(\basename(__DIR__), $sut->Id());
    }

    #endregion Id

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

    #region SetMeta ------------------------------------------------------------

    function testSetMetaWithDefaultType()
    {
        $sut = $this->systemUnderTest();

        $this->metaCollection->expects($this->once())
            ->method('Set')
            ->with('description', 'my description', 'name');

        $this->assertSame($sut, $sut->SetMeta('description', 'my description'));
    }

    function testSetMetaWithCustomType()
    {
        $sut = $this->systemUnderTest();

        $this->metaCollection->expects($this->once())
            ->method('Set')
            ->with('og:title', 'my title', 'property');

        $this->assertSame($sut, $sut->SetMeta('og:title', 'my title', 'property'));
    }

    function testSetMetaWithStringableContent()
    {
        $sut = $this->systemUnderTest();
        $content = $this->createStub(\Stringable::class);

        $this->metaCollection->expects($this->once())
            ->method('Set')
            ->with('description', $content, 'name');

        $this->assertSame($sut, $sut->SetMeta('description', $content));
    }

    #endregion SetMeta

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
            ->method('Set');
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
            ->method('Set')
            ->with('og:title','My Title', 'property');
        $this->metaCollection->expects($this->once())
            ->method('Items')
            ->willReturn($metas);

        $this->assertSame($metas, $sut->MetaItems());
    }

    #endregion MetaItems

    #region LoggedInAccount ----------------------------------------------------

    function testLoggedInAccountDelegatesToAuthManager()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createStub(Account::class);

        $this->authManager->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);

        $this->assertSame($account, $sut->LoggedInAccount());
    }

    #endregion LoggedInAccount

    #region LoggedInAccountRole ------------------------------------------------

    function testLoggedInAccountRoleDelegatesToAuthManager()
    {
        $sut = $this->systemUnderTest();

        $this->authManager->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(Role::Editor);

        $this->assertSame(Role::Editor, $sut->LoggedInAccountRole());
    }

    #endregion LoggedInAccountRole

    #region RequireLogin -------------------------------------------------------

    function testRequireLoginDelegatesToAuthManager()
    {
        $sut = $this->systemUnderTest();

        $this->authManager->expects($this->once())
            ->method('RequireLogin')
            ->with(Role::Admin);

        $this->assertSame($sut, $sut->RequireLogin(Role::Admin));
    }

    #endregion RequireLogin

    #region SetProperty --------------------------------------------------------

    function testSetProperty()
    {
        $sut = $this->systemUnderTest();
        $properties = AccessHelper::GetMockProperty(Page::class, $sut, 'properties');
        $this->assertSame(null, $properties->GetOrDefault('theme', null));
        $this->assertSame($sut, $sut->SetProperty('theme', 'dark'));
        $this->assertSame('dark', $properties->GetOrDefault('theme', null));
    }

    #endregion SetProperty

    #region Property -----------------------------------------------------------

    function testPropertyReturnsValueOrDefault()
    {
        $sut = $this->systemUnderTest();
        $sut->SetProperty('layout', 'compact');
        $this->assertSame('compact', $sut->Property('layout'));
        $this->assertSame('full', $sut->Property('mode', 'full'));
    }

    #endregion Property

    #region CsrfTokenName ------------------------------------------------------

    function testCsrfTokenName()
    {
        $sut = $this->systemUnderTest();

        $this->assertSame(FormTokenGuard::CSRF_FIELD_NAME, $sut->CsrfTokenName());
    }

    #endregion CsrfTokenName

    #region CsrfTokenValue -----------------------------------------------------

    function testCsrfTokenValue()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $cookieService = CookieService::Instance();
        $csrfToken = $this->createMock(CsrfToken::class);

        $securityService->expects($this->once())
            ->method('GenerateCsrfToken')
            ->willReturn($csrfToken);
        $csrfToken->expects($this->once())
            ->method('CookieValue')
            ->willReturn('cookie-value');
        $cookieService->expects($this->once())
            ->method('SetCsrfCookie')
            ->with('cookie-value');
        $csrfToken->expects($this->once())
            ->method('Token')
            ->willReturn('token-value');

        $this->assertSame('token-value', $sut->CsrfTokenValue());
    }

    #endregion CsrfTokenValue
}
