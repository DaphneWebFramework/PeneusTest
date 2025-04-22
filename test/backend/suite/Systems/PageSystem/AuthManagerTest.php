<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\PageSystem\AuthManager;

use \Harmonia\Core\CUrl;
use \Harmonia\Http\StatusCode;
use \Peneus\Model\Account;
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

    #region LoggedInAccount ----------------------------------------------------

    function testLoggedInAccountReturnsNullWhenNotLoggedIn()
    {
        $sut = new AuthManager();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn(null);

        $this->assertNull($sut->LoggedInAccount());
        $this->assertNull($sut->LoggedInAccount()); // from cache
    }

    function testLoggedInAccountReturnsCachedValue()
    {
        $sut = new AuthManager();
        $accountService = AccountService::Instance();
        $account = $this->createStub(Account::class);

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);

        $this->assertSame($account, $sut->LoggedInAccount());
        $this->assertSame($account, $sut->LoggedInAccount()); // from cache
    }

    #endregion LoggedInAccount

    #region LoggedInAccountRole ------------------------------------------------

    function testLoggedInAccountRoleReturnsCachedOrFallback()
    {
        $sut = new AuthManager();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(null); // Simulate no role stored

        $this->assertSame(Role::None, $sut->LoggedInAccountRole());
        $this->assertSame(Role::None, $sut->LoggedInAccountRole()); // from cache
    }

    function testLoggedInAccountRoleReturnsSetRole()
    {
        $sut = new AuthManager();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(Role::Editor); // Simulate stored role

        $this->assertSame(Role::Editor, $sut->LoggedInAccountRole());
        $this->assertSame(Role::Editor, $sut->LoggedInAccountRole()); // from cache
    }

    #endregion LoggedInAccountRole

    #region RequireLogin -------------------------------------------------------

    function testRequireLoginRedirectsToLoginPageWhenNotLoggedIn()
    {
        $sut = $this->systemUnderTest('redirect');
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();
        $loginPageUrl = new CUrl('login-page-url');

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn(null);
        $resource->expects($this->once())
            ->method('LoginPageUrl')
            ->willReturn($loginPageUrl);
        $sut->expects($this->once())
            ->method('redirect')
            ->with($loginPageUrl);

        $sut->RequireLogin();
    }

    function testRequireLoginPassesWhenRoleIsNullAndMinimumIsNone()
    {
        $sut = new AuthManager();
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $resource->expects($this->never())
            ->method('LoginPageUrl');
        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(null);
        $resource->expects($this->never())
            ->method('ErrorPageUrl');

        $sut->RequireLogin(Role::None);
    }

    function testRequireLoginPassesWhenRoleIsNoneAndMinimumIsNone()
    {
        $sut = new AuthManager();
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(Role::None);
        $resource->expects($this->never())
            ->method('LoginPageUrl');
        $resource->expects($this->never())
            ->method('ErrorPageUrl');

        $sut->RequireLogin(Role::None);
    }

    function testRequireLoginRedirectsToErrorPageWhenRoleIsInsufficient()
    {
        $sut = $this->systemUnderTest('redirect');
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();
        $errorPageUrl = new CUrl('error-page-url');

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(Role::Editor); // less than Admin
        $resource->expects($this->once())
            ->method('ErrorPageUrl')
            ->with(StatusCode::Unauthorized)
            ->willReturn($errorPageUrl);
        $sut->expects($this->once())
            ->method('redirect')
            ->with($errorPageUrl);

        $sut->RequireLogin(Role::Admin);
    }

    function testRequireLoginPassesWhenRoleIsEqualToMinimum()
    {
        $sut = new AuthManager();
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(Role::Admin);
        $resource->expects($this->never())
            ->method('LoginPageUrl');
        $resource->expects($this->never())
            ->method('ErrorPageUrl');

        $sut->RequireLogin(Role::Admin);
    }

    function testRequireLoginPassesWhenRoleIsGreaterThanMinimum()
    {
        $sut = new AuthManager();
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $accountService->expects($this->once())
            ->method('LoggedInAccountRole')
            ->willReturn(Role::Admin); // greater than Editor
        $resource->expects($this->never())
            ->method('LoginPageUrl');
        $resource->expects($this->never())
            ->method('ErrorPageUrl');

        $sut->RequireLogin(Role::Editor);
    }

    #endregion RequireLogin
}
