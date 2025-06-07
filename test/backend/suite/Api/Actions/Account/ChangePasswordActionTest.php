<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Account\ChangePasswordAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\SecurityService;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper;

#[CoversClass(ChangePasswordAction::class)]
class ChangePasswordActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?AccountService $originalAccountService = null;
    private ?SecurityService $originalSecurityService = null;
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->config());
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        AccountService::ReplaceInstance($this->originalAccountService);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        Config::ReplaceInstance($this->originalConfig);
    }

    private function config()
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(): ChangePasswordAction
    {
        return new ChangePasswordAction();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfCurrentPasswordIsMissing()
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
                'newPassword' => 'pass1234'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Required field 'currentPassword' is missing.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfCurrentPasswordTooShort()
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
                'currentPassword' => '1234567',
                'newPassword' => 'pass1234'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'currentPassword' must have a minimum length of 8 characters.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfCurrentPasswordTooLong()
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
                'currentPassword' => str_repeat('a', 73),
                'newPassword' => 'pass1234'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'currentPassword' must have a maximum length of 72 characters.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfNewPasswordIsMissing()
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
                'currentPassword' => 'pass1234'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Required field 'newPassword' is missing.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfNewPasswordTooShort()
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
                'currentPassword' => 'pass1234',
                'newPassword' => '1234567'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'newPassword' must have a minimum length of 8 characters.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfNewPasswordTooLong()
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
                'currentPassword' => 'pass1234',
                'newPassword' => str_repeat('a', 73)
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'newPassword' must have a maximum length of 72 characters.");
        AccessHelper::CallMethod($sut, 'onExecute');
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
        AccessHelper::CallMethod($sut, 'onExecute');
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
        AccessHelper::CallMethod($sut, 'onExecute');
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
        AccessHelper::CallMethod($sut, 'onExecute');
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

        $this->assertNull(AccessHelper::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute
}
