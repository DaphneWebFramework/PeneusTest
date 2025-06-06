<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Account\ChangeDisplayNameAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper;

#[CoversClass(ChangeDisplayNameAction::class)]
class ChangeDisplayNameActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?AccountService $originalAccountService = null;
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->config());
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        AccountService::ReplaceInstance($this->originalAccountService);
        Config::ReplaceInstance($this->originalConfig);
    }

    private function config()
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(): ChangeDisplayNameAction
    {
        return new ChangeDisplayNameAction();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfDisplayNameIsMissing()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required field 'displayName' is missing.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDisplayNameIsInvalid()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'displayName' => '<script>'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Display name is invalid. It must start with a letter or number'
          . ' and may only contain letters, numbers, spaces, dots, hyphens,'
          . ' and apostrophes.');
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfNotLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $accountService = AccountService::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'displayName' => 'Alice'
            ]);
        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You do not have permission to perform this action.');
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $accountService = AccountService::Instance();
        $account = $this->createMock(Account::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'displayName' => 'Alice'
            ]);
        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Display name change failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $accountService = AccountService::Instance();
        $account = $this->createMock(Account::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'displayName' => 'Alice'
            ]);
        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertNull($result);
    }

    #endregion onExecute
}
