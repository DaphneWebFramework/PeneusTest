<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\ChangeDisplayNameAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as AH;

#[CoversClass(ChangeDisplayNameAction::class)]
class ChangeDisplayNameActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?AccountService $originalAccountService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        AccountService::ReplaceInstance($this->originalAccountService);
    }

    private function systemUnderTest(string ...$mockedMethods): ChangeDisplayNameAction
    {
        return $this->getMockBuilder(ChangeDisplayNameAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    #[DataProvider('invalidModelDataProvider')]
    function testOnExecuteThrowsForInvalidModelData(
        array $data,
        string $exceptionMessage
    ) {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($data);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfUserNotLoggedIn()
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
        $this->expectExceptionMessage(
            'You do not have permission to perform this action.');
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountIsNotFound()
    {
        $sut = $this->systemUnderTest('findAccount');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;

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
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Account not found.");
        $this->expectExceptionCode(StatusCode::NotFound->value);
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountCannotBeSaved()
    {
        $sut = $this->systemUnderTest('findAccount');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
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
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to change display name.");
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('findAccount');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
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
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);

        $this->assertNull(AH::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute

    #region Data Providers -----------------------------------------------------

    static function invalidModelDataProvider()
    {
        return [
            'displayName missing' => [
                'data' => [],
                'exceptionMessage' => "Required field 'displayName' is missing."
            ],
            'displayName invalid' => [
                'data' => [ 'displayName' => '<invalid-display-name>' ],
                'exceptionMessage' => 'Display name is invalid. It must start'
                    . ' with a letter or number and may only contain letters,'
                    . ' numbers, spaces, dots, hyphens, and apostrophes.'
            ],
        ];
    }

    #endregion Data Providers
}
