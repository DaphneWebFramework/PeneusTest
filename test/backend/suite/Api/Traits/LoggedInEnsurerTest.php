<?php declare(strict_types=1);
namespace suite\Api\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Traits\LoggedInEnsurer;

use \Harmonia\Http\StatusCode;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\DataHelper as dh;

class _LoggedInEnsurerWithoutAccountService {
    use LoggedInEnsurer;
}
class _LoggedInEnsurerWithAccountService {
    use LoggedInEnsurer;
    private AccountService $accountService;
}

#[CoversClass(_LoggedInEnsurerWithoutAccountService::class)]
#[CoversClass(_LoggedInEnsurerWithAccountService::class)]
class LoggedInEnsurerTest extends TestCase
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

    private function systemUnderTest(bool $withAccountService): object
    {
        if ($withAccountService) {
            $sut = new _LoggedInEnsurerWithAccountService();
            ah::SetProperty($sut, 'accountService', AccountService::Instance());
        } else {
            $sut = new _LoggedInEnsurerWithoutAccountService();
        }
        return $sut;
    }

    #region ensureLoggedIn -----------------------------------------------------

    #[DataProviderExternal(dh::class, 'BooleanProvider')]
    function testEnsureLoggedInThrowsIfUserIsNotLoggedIn(bool $withAccountService)
    {
        $sut = $this->systemUnderTest($withAccountService);
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "You do not have permission to perform this action.");
        $this->expectExceptionCode(StatusCode::Unauthorized->value);

        ah::CallMethod($sut, 'ensureLoggedIn');
    }

    #[DataProviderExternal(dh::class, 'BooleanProvider')]
    function testEnsureLoggedInSucceedsIfUserIsLoggedIn(bool $withAccountService)
    {
        $sut = $this->systemUnderTest($withAccountService);
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn($accountView);

        $this->assertSame(
            $accountView,
            ah::CallMethod($sut, 'ensureLoggedIn')
        );
    }

    #endregion ensureLoggedIn
}
