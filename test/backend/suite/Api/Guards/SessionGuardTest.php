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

    function testVerifyWhenLoggedInAccountDoesNotExist()
    {
        $sut = new SessionGuard();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn(null);

        $this->assertFalse($sut->Verify());
    }

    #[DataProvider('verifyDataProvider')]
    public function testVerify(
        bool $expected,
        ?int $accountRole,
        Role $minimumRole
    ) {
        $sut = new SessionGuard($minimumRole);
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);
        $accountView->role = $accountRole;

        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($accountView);

        $this->assertSame($expected, $sut->Verify());
    }

    #endregion Verify

    #region Data Providers -----------------------------------------------------

    static function verifyDataProvider()
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
