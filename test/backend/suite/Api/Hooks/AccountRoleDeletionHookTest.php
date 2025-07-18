<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Hooks\AccountRoleDeletionHook;

use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;

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

    #region OnDeleteAccount ----------------------------------------------------

    function testOnDeleteAccountSkipsWhenNoRolesExist()
    {
        $sut = new AccountRoleDeletionHook();
        $fakeDatabase = Database::Instance();
        $account = new Account(['id' => 42]);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM accountrole WHERE accountId = :accountId',
            bindings: ['accountId' => 42],
            result: [],
            times: 1
        );

        $this->expectNotToPerformAssertions();
        $sut->OnDeleteAccount($account);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testOnDeleteAccountThrowsIfAnyDeleteFails()
    {
        $sut = new AccountRoleDeletionHook();
        $fakeDatabase = Database::Instance();
        $account = new Account(['id' => 42]);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM accountrole WHERE accountId = :accountId',
            bindings: ['accountId' => 42],
            result: [[
                'id' => 1,
                'accountId' => 42
            ], [
                'id' => 2,
                'accountId' => 42
            ], [
                'id' => 3,
                'accountId' => 42
            ]],
            times: 1
        );
        $fakeDatabase->Expect(
            sql: 'DELETE FROM accountrole WHERE id = :id',
            bindings: ['id' => 1],
            result: [],
            lastAffectedRowCount: 1,
            times: 1
        );
        $fakeDatabase->Expect(
            sql: 'DELETE FROM accountrole WHERE id = :id',
            bindings: ['id' => 2],
            result: null,
            times: 1
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to delete account role.');
        $sut->OnDeleteAccount($account);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testOnDeleteAccountSucceedsIfAllDeletesSucceed()
    {
        $sut = new AccountRoleDeletionHook();
        $fakeDatabase = Database::Instance();
        $account = new Account(['id' => 42]);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM accountrole WHERE accountId = :accountId',
            bindings: ['accountId' => 42],
            result: [[
                'id' => 1,
                'accountId' => 42
            ], [
                'id' => 2,
                'accountId' => 42
            ]],
            times: 1
        );
        $fakeDatabase->Expect(
            sql: 'DELETE FROM accountrole WHERE id = :id',
            bindings: ['id' => 1],
            result: [],
            lastAffectedRowCount: 1,
            times: 1
        );
        $fakeDatabase->Expect(
            sql: 'DELETE FROM accountrole WHERE id = :id',
            bindings: ['id' => 2],
            result: [],
            lastAffectedRowCount: 1,
            times: 1
        );

        $this->expectNotToPerformAssertions();
        $sut->OnDeleteAccount($account);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion OnDeleteAccount
}
