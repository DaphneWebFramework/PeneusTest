<?php declare(strict_types=1);
namespace suite\Api\Actions\Account;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Account\LogOutAction;

use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

#[CoversClass(LogOutAction::class)]
class LogOutActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): LogOutAction
    {
        return $this->getMockBuilder(LogOutAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    private function contextForOnExecute(
        bool $sessionDeleteSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($ctx->chain())
            ->method('DeleteSession')
            ->willReturnCallback(fn() => $sessionDeleteSucceeds
                ? null
                : throw new \RuntimeException('SESSION_DELETE_FAILED'));

        return $ctx;
    }

    function testOnExecuteFailsIfSessionDeleteFails()
    {
        $ctx = $this->contextForOnExecute(sessionDeleteSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SESSION_DELETE_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $ctx = $this->contextForOnExecute();
        $actual = ah::CallMethod($ctx->sut, 'onExecute');
        $this->assertNull($actual);
    }

    #endregion onExecute
}
