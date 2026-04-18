<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Guards\SessionGuard;

use \Peneus\Model\AccountView;
use \Peneus\Model\Role;
use \Peneus\Services\AccountService;

#[CoversClass(SessionGuard::class)]
class SessionGuardTest extends TestCase
{
    private ?AccountService $originalAccountService = null;

    protected function setUp(): void
    {
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
    }

    protected function tearDown(): void
    {
        AccountService::ReplaceInstance($this->originalAccountService);
    }

    #region Verify -------------------------------------------------------------

    function testVerifyWhenSessionAccountDoesNotExist()
    {
        $sut = new SessionGuard();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn(null);

        $this->assertFalse($sut->Verify());
    }

    #[DataProvider('verifyDataProvider')]
    public function testVerify(
        bool $expected,
        Role $accountRole,
        Role $minimumRole
    ) {
        $sut = new SessionGuard($minimumRole);
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);
        $accountView->role = $accountRole;

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn($accountView);

        $this->assertSame($expected, $sut->Verify());
    }

    #endregion Verify

    #region Data Providers -----------------------------------------------------

    static function verifyDataProvider()
    {
        return [
            'None vs None'     => [true,  Role::None,   Role::None],
            'None vs Editor'   => [false, Role::None,   Role::Editor],
            'None vs Admin'    => [false, Role::None,   Role::Admin],
            'Editor vs None'   => [true,  Role::Editor, Role::None],
            'Editor vs Editor' => [true,  Role::Editor, Role::Editor],
            'Editor vs Admin'  => [false, Role::Editor, Role::Admin],
            'Admin vs None'    => [true,  Role::Admin,  Role::None],
            'Admin vs Editor'  => [true,  Role::Admin,  Role::Editor],
            'Admin vs Admin'   => [true,  Role::Admin,  Role::Admin],
        ];
    }

    #endregion Data Providers
}
