<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Handlers\ManagementHandler;

use \Peneus\Api\Actions\Management\ListRecordsAction;
use \Peneus\Api\Guards\SessionGuard;
use \Peneus\Model\Role;
use \TestToolkit\AccessHelper;

#[CoversClass(ManagementHandler::class)]
class ManagementHandlerTest extends TestCase
{
    #region createAction -------------------------------------------------------

    function testCreateActionWithListRecords()
    {
        $handler = new ManagementHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['list-records']);
        $this->assertInstanceOf(ListRecordsAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertInstanceOf(SessionGuard::class, $guards[0]);
        $this->assertSame(Role::Admin, AccessHelper::GetProperty($guards[0], 'minimumRole'));
    }

    public function testCreateActionWithUnknownAction()
    {
        $handler = new ManagementHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['unknown']);
        $this->assertNull($action);
    }

    #endregion createAction
}
