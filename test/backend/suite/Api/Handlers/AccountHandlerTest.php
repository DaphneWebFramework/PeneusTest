<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Handlers\AccountHandler;

use \Peneus\Api\Actions\LoginAction;
use \Peneus\Api\Actions\LogoutAction;
use \Peneus\Api\Guards\FormTokenGuard;
use \Peneus\Api\Guards\SessionGuard;
use \TestToolkit\AccessHelper;

#[CoversClass(AccountHandler::class)]
class AccountHandlerTest extends TestCase
{
    #region createAction -------------------------------------------------------

    function testCreateActionWithLogin()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['login']);
        $this->assertInstanceOf(LoginAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(FormTokenGuard::class, $guards[0]);
    }

    function testCreateActionWithLogout()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['logout']);
        $this->assertInstanceOf(LogoutAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(SessionGuard::class, $guards[0]);
    }

    public function testCreateActionWithUnknownAction()
    {
        $handler = new AccountHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['unknown']);
        $this->assertNull($action);
    }

    #endregion createAction
}
