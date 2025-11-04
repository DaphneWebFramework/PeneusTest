<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Account\LogoutAction;

use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(LogoutAction::class)]
class LogoutActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): LogoutAction
    {
        return $this->getMockBuilder(LogoutAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecute()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('DeleteSession');
        $accountService->expects($this->once())
            ->method('DeletePersistentLogin');

        $this->assertNull(ah::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute
}
