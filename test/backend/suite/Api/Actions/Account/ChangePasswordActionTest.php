<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\ChangePasswordAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\SecurityService;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as AH;

#[CoversClass(ChangePasswordAction::class)]
class ChangePasswordActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?AccountService $originalAccountService = null;
    private ?SecurityService $originalSecurityService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        AccountService::ReplaceInstance($this->originalAccountService);
        SecurityService::ReplaceInstance($this->originalSecurityService);
    }

    private function systemUnderTest(): ChangePasswordAction
    {
        return new ChangePasswordAction();
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
                'currentPassword' => 'pass1234',
                'newPassword' => 'pass5678'
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

    function testOnExecuteThrowsIfPasswordVerificationFails()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $accountService = AccountService::Instance();
        $securityService = SecurityService::Instance();
        $account = $this->createMock(Account::class);
        $account->passwordHash = 'hash1234';

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'currentPassword' => 'wrongpass',
                'newPassword' => 'pass5678'
            ]);
        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('wrongpass', $account->passwordHash)
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Current password is incorrect.');
        $this->expectExceptionCode(StatusCode::Forbidden->value);
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $accountService = AccountService::Instance();
        $securityService = SecurityService::Instance();
        $account = $this->createMock(Account::class);
        $account->passwordHash = 'hash1234';

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'currentPassword' => 'pass1234',
                'newPassword' => 'pass5678'
            ]);
        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('pass1234', $account->passwordHash)
            ->willReturn(true);
        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('pass5678')
            ->willReturn('hash5678');
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Password change failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $accountService = AccountService::Instance();
        $securityService = SecurityService::Instance();
        $account = $this->createMock(Account::class);
        $account->passwordHash = 'hash1234';

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'currentPassword' => 'pass1234',
                'newPassword' => 'pass5678'
            ]);
        $accountService->expects($this->once())
            ->method('LoggedInAccount')
            ->willReturn($account);
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('pass1234', $account->passwordHash)
            ->willReturn(true);
        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('pass5678')
            ->willReturn('hash5678');
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
            'currentPassword missing' => [
                'data' => [],
                'exceptionMessage' => "Required field 'currentPassword' is missing."
            ],
            'currentPassword too short' => [
                'data' => [
                    'currentPassword' => '1234567'
                ],
                'exceptionMessage' => "Field 'currentPassword' must have a minimum length of 8 characters."
            ],
            'currentPassword too long' => [
                'data' => [
                    'currentPassword' => str_repeat('a', 73)
                ],
                'exceptionMessage' => "Field 'currentPassword' must have a maximum length of 72 characters."
            ],
            'newPassword missing' => [
                'data' => [
                    'currentPassword' => 'pass1234'
                ],
                'exceptionMessage' => "Required field 'newPassword' is missing."
            ],
            'newPassword too short' => [
                'data' => [
                    'currentPassword' => 'pass1234',
                    'newPassword' => '1234567'
                ],
                'exceptionMessage' => "Field 'newPassword' must have a minimum length of 8 characters."
            ],
            'newPassword too long' => [
                'data' => [
                    'currentPassword' => 'pass1234',
                    'newPassword' => str_repeat('a', 73)
                ],
                'exceptionMessage' => "Field 'newPassword' must have a maximum length of 72 characters."
            ],
        ];
    }

    #endregion Data Providers
}
