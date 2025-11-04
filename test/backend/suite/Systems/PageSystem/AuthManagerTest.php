<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Systems\PageSystem\AuthManager;

use \Harmonia\Core\CUrl;
use \Harmonia\Http\StatusCode;
use \Peneus\Model\AccountView;
use \Peneus\Model\Role;
use \Peneus\Resource;
use \Peneus\Services\AccountService;

#[CoversClass(AuthManager::class)]
class AuthManagerTest extends TestCase
{
    private ?AccountService $originalAccountService = null;
    private ?Resource $originalResource = null;

    protected function setUp(): void
    {
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
    }

    protected function tearDown(): void
    {
        AccountService::ReplaceInstance($this->originalAccountService);
        Resource::ReplaceInstance($this->originalResource);
    }

    private function systemUnderTest(string ...$mockedMethods): AuthManager
    {
        return $this->getMockBuilder(AuthManager::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region SessionAccount -----------------------------------------------------

    function testSessionAccountReturnsNullWhenNotLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn(null);

        $this->assertNull($sut->SessionAccount());
        $this->assertNull($sut->SessionAccount()); // from cache
    }

    function testSessionAccountReturnsCachedValue()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn($accountView);

        $this->assertSame($accountView, $sut->SessionAccount());
        $this->assertSame($accountView, $sut->SessionAccount()); // from cache
    }

    #endregion SessionAccount

    #region RequireLogin -------------------------------------------------------

    function testRequireLoginRedirectsToLoginPageWhenNotLoggedIn()
    {
        $sut = $this->systemUnderTest('redirect');
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();
        $url = $this->createStub(CUrl::class);

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn(null);
        $resource->expects($this->once())
            ->method('LoginPageUrl')
            ->willReturn($url);
        $sut->expects($this->once())
            ->method('redirect')
            ->with($url);

        $sut->RequireLogin();
    }

    #[DataProvider('requireLoginDataProvider')]
    function testRequireLogin(
        bool $expected,
        ?int $accountRole,
        Role $minimumRole
    ) {
        $sut = $this->systemUnderTest('redirect');
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);
        $accountView->role = $accountRole;
        $resource = Resource::Instance();

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn($accountView);
        if ($expected == true) {
            $resource->expects($this->never())
                ->method('ErrorPageUrl');
            $sut->expects($this->never())
                ->method('redirect');
        } else {
            $url = $this->createStub(CUrl::class);
            $resource->expects($this->once())
                ->method('ErrorPageUrl')
                ->with(StatusCode::Unauthorized)
                ->willReturn($url);
            $sut->expects($this->once())
                ->method('redirect')
                ->with($url);
        }

        $sut->RequireLogin($minimumRole);
    }

    #endregion RequireLogin

    #region Data Providers -----------------------------------------------------

    static function requireLoginDataProvider()
    {
        return [
            'invalid vs None'   => [true,  99, Role::None],
            'invalid vs Editor' => [false, 99, Role::Editor],
            'invalid vs Admin'  => [false, 99, Role::Admin],

            'null vs None'   => [true,  null, Role::None],
            'null vs Editor' => [false, null, Role::Editor],
            'null vs Admin'  => [false, null, Role::Admin],

            'None vs None'   => [true,  0, Role::None],
            'None vs Editor' => [false, 0, Role::Editor],
            'None vs Admin'  => [false, 0, Role::Admin],

            'Editor vs None'   => [true,  10, Role::None],
            'Editor vs Editor' => [true,  10, Role::Editor],
            'Editor vs Admin'  => [false, 10, Role::Admin],

            'Admin vs None'   => [true,  20, Role::None],
            'Admin vs Editor' => [true,  20, Role::Editor],
            'Admin vs Admin'  => [true,  20, Role::Admin],
        ];
    }

    #endregion Data Providers
}
