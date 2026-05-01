<?php declare(strict_types=1);
namespace suite\Api\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Api\Traits\ActivationHooksTriggerer;

use \Peneus\Api\Hooks\IAccountActivationHook;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

class _ActivationHooksTriggererWithAccountService {
    use ActivationHooksTriggerer;
    private readonly AccountService $accountService;
    public function __construct() {
        $this->accountService = AccountService::Instance();
    }
}
class _ActivationHooksTriggererWithoutAccountService {
    use ActivationHooksTriggerer;
}

#[CoversClass(_ActivationHooksTriggererWithAccountService::class)]
#[CoversClass(_ActivationHooksTriggererWithoutAccountService::class)]
class ActivationHooksTriggererTest extends TestCase
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
            ? new _ActivationHooksTriggererWithAccountService()
            : new _ActivationHooksTriggererWithoutAccountService();
    }

    #region triggerActivationHooks ---------------------------------------------

    private function contextForTriggerActivationHooks(
        bool $hasAccountService,
        bool $firstHookSucceeds = true,
        bool $secondHookSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest($hasAccountService);
        $ctx->account = $this->createStub(Account::class);
        $accountService = AccountService::Instance();
        $hooks = [
            $this->createMock(IAccountActivationHook::class),
            $this->createMock(IAccountActivationHook::class)
        ];

        $accountService->expects($ctx->chain())
            ->method('ActivationHooks')
            ->willReturn($hooks);
        $hooks[0]->expects($ctx->chain())
            ->method('OnActivateAccount')
            ->with($ctx->account)
            ->willReturnCallback(fn() => $firstHookSucceeds
                ? null
                : throw new \RuntimeException('FIRST_HOOK_FAILED'));
        $hooks[1]->expects($ctx->chainIf($firstHookSucceeds))
            ->method('OnActivateAccount')
            ->with($ctx->account)
            ->willReturnCallback(fn() => $secondHookSucceeds
                ? null
                : throw new \RuntimeException('SECOND_HOOK_FAILED'));

        return $ctx;
    }

    #[TestWith([false])]
    #[TestWith([true ])]
    function testTriggerActivationHooksFailsIfFirstHookFails(bool $hasAccountService)
    {
        $ctx = $this->contextForTriggerActivationHooks(
            hasAccountService: $hasAccountService,
            firstHookSucceeds: false
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FIRST_HOOK_FAILED');
        ah::CallMethod($ctx->sut, 'triggerActivationHooks', [$ctx->account]);
    }

    #[TestWith([false])]
    #[TestWith([true ])]
    function testTriggerActivationHooksFailsIfSecondHookFails(bool $hasAccountService)
    {
        $ctx = $this->contextForTriggerActivationHooks(
            hasAccountService: $hasAccountService,
            secondHookSucceeds: false
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SECOND_HOOK_FAILED');
        ah::CallMethod($ctx->sut, 'triggerActivationHooks', [$ctx->account]);
    }

    #[TestWith([false])]
    #[TestWith([true ])]
    function testTriggerActivationHooksSucceeds(bool $hasAccountService)
    {
        $ctx = $this->contextForTriggerActivationHooks(
            hasAccountService: $hasAccountService
        );
        ah::CallMethod($ctx->sut, 'triggerActivationHooks', [$ctx->account]);
    }

    #endregion triggerActivationHooks
}
