<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Handlers\AccountHandler;

use \Peneus\Api\Actions\Account\ActivateAction;
use \Peneus\Api\Actions\Account\ChangeDisplayNameAction;
use \Peneus\Api\Actions\Account\ChangePasswordAction;
use \Peneus\Api\Actions\Account\DeleteAction;
use \Peneus\Api\Actions\Account\LogInAction;
use \Peneus\Api\Actions\Account\LogOutAction;
use \Peneus\Api\Actions\Account\RegisterAction;
use \Peneus\Api\Actions\Account\ResetPasswordAction;
use \Peneus\Api\Actions\Account\SendPasswordResetAction;
use \Peneus\Api\Actions\Account\SignInWithGoogleAction;
use \Peneus\Api\Guards\FormTokenGuard;
use \Peneus\Api\Guards\HeaderTokenGuard;
use \Peneus\Api\Guards\SessionGuard;
use \Peneus\Api\Guards\TurnstileGuard;
use \TestToolkit\AccessHelper;

#[CoversClass(AccountHandler::class)]
class AccountHandlerTest extends TestCase
{
    #region createAction -------------------------------------------------------

    function testCreateActionWithSignInWithGoogle()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['sign-in-with-google']);
        $this->assertInstanceOf(SignInWithGoogleAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(HeaderTokenGuard::class, $guards[0]);
    }

    function testCreateActionWithRegister()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['register']);
        $this->assertInstanceOf(RegisterAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(2, $guards);
        $this->assertInstanceOf(FormTokenGuard::class, $guards[0]);
        $this->assertInstanceOf(TurnstileGuard::class, $guards[1]);
    }

    function testCreateActionWithActivate()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['activate']);
        $this->assertInstanceOf(ActivateAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(FormTokenGuard::class, $guards[0]);
    }

    function testCreateActionWithLogIn()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['log-in']);
        $this->assertInstanceOf(LogInAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(2, $guards);
        $this->assertInstanceOf(FormTokenGuard::class, $guards[0]);
        $this->assertInstanceOf(TurnstileGuard::class, $guards[1]);
    }

    function testCreateActionWithLogOut()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['log-out']);
        $this->assertInstanceOf(LogOutAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(SessionGuard::class, $guards[0]);
    }

    function testCreateActionWithSendPasswordReset()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['send-password-reset']);
        $this->assertInstanceOf(SendPasswordResetAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(2, $guards);
        $this->assertInstanceOf(FormTokenGuard::class, $guards[0]);
        $this->assertInstanceOf(TurnstileGuard::class, $guards[1]);
    }

    function testCreateActionWithResetPassword()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['reset-password']);
        $this->assertInstanceOf(ResetPasswordAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(FormTokenGuard::class, $guards[0]);
    }

    function testCreateActionWithChangeDisplayName()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['change-display-name']);
        $this->assertInstanceOf(ChangeDisplayNameAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(SessionGuard::class, $guards[0]);
    }

    function testCreateActionWithChangePassword()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['change-password']);
        $this->assertInstanceOf(ChangePasswordAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(SessionGuard::class, $guards[0]);
    }

    function testCreateActionWithDelete()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['delete']);
        $this->assertInstanceOf(DeleteAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(SessionGuard::class, $guards[0]);
    }

    function testCreateActionWithUnknownAction()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['unknown']);
        $this->assertNull($action);
    }

    #endregion createAction
}
