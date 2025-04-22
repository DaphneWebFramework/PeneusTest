<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\PageSystem\AccessPolicies\MembersPolicy;

use \Harmonia\Core\CUrl;
use \Harmonia\Http\StatusCode;
use \Peneus\Model\Account;
use \Peneus\Model\Role;
use \Peneus\Resource;
use \Peneus\Services\AccountService;

#[CoversClass(MembersPolicy::class)]
class MembersPolicyTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): MembersPolicy
    {
        return $this->getMockBuilder(MembersPolicy::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region Enforce ------------------------------------------------------------

    function testEnforceRedirectsToLoginPageWhenLoggedInAccountReturnsNull()
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
        // Note: Response::Redirect() typically halts script execution,
        // so subsequent code in Enforce() would not run beyond this point.

        $sut->Enforce();
    }

    function testEnforceWhenRoleOfLoggedInAccountReturnsNullAndMinimumRoleIsNone()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $resource->expects($this->never())
            ->method('LoginPageUrl');
        $accountService->expects($this->once())
            ->method('RoleOfLoggedInAccount')
            ->willReturn(null);
        $resource->expects($this->never())
            ->method('ErrorPageUrl');

        $sut->__construct(Role::None);
        $sut->Enforce();
    }

    function testEnforceWhenRoleOfLoggedInAccountReturnsNoneAndMinimumRoleIsNone()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $resource->expects($this->never())
            ->method('LoginPageUrl');
        $accountService->expects($this->once())
            ->method('RoleOfLoggedInAccount')
            ->willReturn(Role::None);
        $resource->expects($this->never())
            ->method('ErrorPageUrl');

        $sut->__construct(Role::None);
        $sut->Enforce();
    }

    function testEnforceRedirectsToErrorPageWhenRoleOfLoggedInAccountIsLessThanMinimumRole()
    {
        $sut = $this->systemUnderTest('redirect');
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();
        $errorPageUrl = new CUrl('error-page-url');

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $accountService->expects($this->once())
            ->method('RoleOfLoggedInAccount')
            ->willReturn(Role::Editor); // less than minimum role (Admin)
        $resource->expects($this->once())
            ->method('ErrorPageUrl')
            ->with(StatusCode::Unauthorized)
            ->willReturn($errorPageUrl);
        $sut->expects($this->once())
            ->method('redirect')
            ->with($errorPageUrl);

        $sut->__construct(Role::Admin);
        $sut->Enforce();
    }

    function testEnforceWhenRoleOfLoggedInAccountIsEqualToMinimumRole()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $resource->expects($this->never())
            ->method('LoginPageUrl');
        $accountService->expects($this->once())
            ->method('RoleOfLoggedInAccount')
            ->willReturn(Role::Admin); // equal to minimum role
        $resource->expects($this->never())
            ->method('ErrorPageUrl');

        $sut->__construct(Role::Admin);
        $sut->Enforce();
    }

    function testEnforceWhenRoleOfLoggedInAccountIsGreaterThanMinimumRole()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();
        $resource = Resource::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($this->createStub(Account::class));
        $resource->expects($this->never())
            ->method('LoginPageUrl');
        $accountService->expects($this->once())
            ->method('RoleOfLoggedInAccount')
            ->willReturn(Role::Admin); // greater than minimum role (Editor)
        $resource->expects($this->never())
            ->method('ErrorPageUrl');

        $sut->__construct(Role::Editor);
        $sut->Enforce();
    }

    #endregion Enforce
}
