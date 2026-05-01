<?php declare(strict_types=1);
namespace suite\Api\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Api\Traits\NotLoggedInEnsurer;

use \Harmonia\Http\StatusCode;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

class _NotLoggedInEnsurerWithAccountService {
    use NotLoggedInEnsurer;
    private readonly AccountService $accountService;
    public function __construct() {
        $this->accountService = AccountService::Instance();
    }
}
class _NotLoggedInEnsurerWithoutAccountService {
    use NotLoggedInEnsurer;
}

#[CoversClass(_NotLoggedInEnsurerWithAccountService::class)]
#[CoversClass(_NotLoggedInEnsurerWithoutAccountService::class)]
class NotLoggedInEnsurerTest extends TestCase
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

    private function systemUnderTest(bool $hasAccountService): object
    {
        return $hasAccountService
            ? new _NotLoggedInEnsurerWithAccountService()
            : new _NotLoggedInEnsurerWithoutAccountService();
    }

    #region ensureNotLoggedIn --------------------------------------------------

    private function contextForEnsureNotLoggedIn(
        bool $hasAccountService,
        bool $isLoggedIn
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest($hasAccountService);
        $ctx->accountService = AccountService::Instance();
        $ctx->accountView = $this->createStub(AccountView::class);

        $ctx->accountService->expects($ctx->chain())
            ->method('SessionAccount')
            ->willReturn($isLoggedIn ? $ctx->accountView : null);

        return $ctx;
    }

    #[TestWith([true ])]
    #[TestWith([false])]
    function testEnsureNotLoggedInFailsIfUserIsLoggedIn(bool $hasAccountService)
    {
        $ctx = $this->contextForEnsureNotLoggedIn(
            hasAccountService: $hasAccountService,
            isLoggedIn: true
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("You are already logged in.");
        $this->expectExceptionCode(StatusCode::Conflict->value);
        ah::CallMethod($ctx->sut, 'ensureNotLoggedIn');
    }

    #[TestWith([true ])]
    #[TestWith([false])]
    function testEnsureNotLoggedInSucceedsIfUserIsNotLoggedIn(bool $hasAccountService)
    {
        $ctx = $this->contextForEnsureNotLoggedIn(
            hasAccountService: $hasAccountService,
            isLoggedIn: false
        );
        ah::CallMethod($ctx->sut, 'ensureNotLoggedIn');
    }

    #endregion ensureNotLoggedIn
}
