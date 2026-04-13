<?php declare(strict_types=1);
namespace suite\Api\Hooks;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Hooks\AccountRoleDeletionHook;

use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(AccountRoleDeletionHook::class)]
class AccountRoleDeletionHookTest extends TestCase
{
    private ?Database $originalDatabase = null;

    protected function setUp(): void
    {
        $this->originalDatabase =
            Database::ReplaceInstance(new FakeDatabase());
    }

    protected function tearDown(): void
    {
        Database::ReplaceInstance($this->originalDatabase);
    }

    private function systemUnderTest(string ...$mockedMethods): AccountRoleDeletionHook
    {
        return $this->getMockBuilder(AccountRoleDeletionHook::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region OnDeleteAccount ----------------------------------------------------

    function testOnDeleteAccountDoesNothingWhenNoAccountRolesExist()
    {
        $sut = $this->systemUnderTest('findAccountRoles');
        $account = new Account();

        $sut->expects($this->once())
            ->method('findAccountRoles')
            ->with($account)
            ->willReturn([]);

        $sut->OnDeleteAccount($account);
    }

    function testOnDeleteThrowsWhenFirstAccountRoleDeleteFails()
    {
        $sut = $this->systemUnderTest('findAccountRoles');
        $account = new Account();
        $accountRoles = [
            $this->createMock(AccountRole::class),
            $this->createMock(AccountRole::class)
        ];

        $sut->expects($this->once())
            ->method('findAccountRoles')
            ->with($account)
            ->willReturn($accountRoles);
        $accountRoles[0]->expects($this->once())
            ->method('Delete')
            ->willReturn(false);
        $accountRoles[1]->expects($this->never())
            ->method('Delete');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete account role.");

        $sut->OnDeleteAccount($account);
    }

    function testOnDeleteThrowsWhenSecondAccountRoleDeleteFails()
    {
        $sut = $this->systemUnderTest('findAccountRoles');
        $account = new Account();
        $accountRoles = [
            $this->createMock(AccountRole::class),
            $this->createMock(AccountRole::class)
        ];

        $sut->expects($this->once())
            ->method('findAccountRoles')
            ->with($account)
            ->willReturn($accountRoles);
        $accountRoles[0]->expects($this->once())
            ->method('Delete')
            ->willReturn(true);
        $accountRoles[1]->expects($this->once())
            ->method('Delete')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete account role.");

        $sut->OnDeleteAccount($account);
    }

    function testOnDeleteSucceedsWhenAllAccountRolesDeleteSucceed()
    {
        $sut = $this->systemUnderTest('findAccountRoles');
        $account = new Account();
        $accountRoles = [
            $this->createMock(AccountRole::class),
            $this->createMock(AccountRole::class)
        ];

        $sut->expects($this->once())
            ->method('findAccountRoles')
            ->with($account)
            ->willReturn($accountRoles);
        $accountRoles[0]->expects($this->once())
            ->method('Delete')
            ->willReturn(true);
        $accountRoles[1]->expects($this->once())
            ->method('Delete')
            ->willReturn(true);

        $sut->OnDeleteAccount($account);
    }

    #endregion OnDeleteAccount

    #region findAccountRoles ---------------------------------------------------

    function testFindAccountRolesReturnsEmptyArrayIfNoneFound()
    {
        $sut = new AccountRoleDeletionHook();
        $account = new Account(['id' => 100]);
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole`'
               . ' WHERE accountId = :accountId',
            bindings: ['accountId' => 100],
            result: [],
            times: 1
        );

        $actual = ah::CallMethod($sut, 'findAccountRoles', [$account]);

        $this->assertSame([], $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountRolesReturnsArrayOfEntitiesIfFound()
    {
        $sut = new AccountRoleDeletionHook();
        $account = new Account(['id' => 100]);
        $fakeDatabase = Database::Instance();
        $data = [[
            'id' => 10,
            'accountId' => 100,
            'role' => 10
        ], [
            'id' => 11,
            'accountId' => 100,
            'role' => 20
        ]];
        $expected = [
            new AccountRole($data[0]),
            new AccountRole($data[1])
        ];

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `accountrole`'
               . ' WHERE accountId = :accountId',
            bindings: ['accountId' => 100],
            result: $data,
            times: 1
        );

        $actual = ah::CallMethod($sut, 'findAccountRoles', [$account]);

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findAccountRoles
}
