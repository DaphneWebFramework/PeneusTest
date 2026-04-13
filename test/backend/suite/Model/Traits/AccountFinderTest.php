<?php declare(strict_types=1);
namespace suite\Model\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Model\Traits\AccountFinder;

use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \TestToolkit\AccessHelper as ah;

class _AccountFinder {
    use AccountFinder;
}

#[CoversClass(_AccountFinder::class)]
class AccountFinderTest extends TestCase
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

    #region tryFindAccountById -------------------------------------------------

    function testTryFindAccountByIdReturnsNullIfNotFound()
    {
        $sut = new _AccountFinder();
        $id = 42;
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account`'
               . ' WHERE `id` = :id'
               . ' LIMIT 1',
            bindings: ['id' => $id],
            result: null,
            times: 1
        );

        $actual = ah::CallMethod($sut, 'tryFindAccountById', [$id]);

        $this->assertNull($actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testTryFindAccountByIdReturnsEntityIfFound()
    {
        $sut = new _AccountFinder();
        $id = 42;
        $fakeDatabase = Database::Instance();
        $data = [
            'id'            => $id,
            'email'         => 'john@example.com',
            'passwordHash'  => 'hash1234',
            'displayName'   => 'John',
            'timeActivated' => '2024-01-01 00:00:00',
            'timeLastLogin' => '2025-01-01 00:00:00'
        ];
        $expected = new Account($data);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account`'
               . ' WHERE `id` = :id'
               . ' LIMIT 1',
            bindings: ['id' => $id],
            result: [$data],
            times: 1
        );

        $actual = ah::CallMethod($sut, 'tryFindAccountById', [$id]);

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion tryFindAccountById

    #region tryFindAccountByEmail ----------------------------------------------

    function testTryFindAccountByEmailReturnsNullIfNotFound()
    {
        $sut = new _AccountFinder();
        $email = 'john@example.com';
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account`'
               . ' WHERE email = :email'
               . ' LIMIT 1',
            bindings: ['email' => $email],
            result: null,
            times: 1
        );

        $actual = ah::CallMethod($sut, 'tryFindAccountByEmail', [$email]);

        $this->assertNull($actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testTryFindAccountByEmailReturnsEntityIfFound()
    {
        $sut = new _AccountFinder();
        $email = 'john@example.com';
        $fakeDatabase = Database::Instance();
        $data = [
            'id'            => 42,
            'email'         => $email,
            'passwordHash'  => 'hash1234',
            'displayName'   => 'John',
            'timeActivated' => '2024-01-01 00:00:00',
            'timeLastLogin' => null
        ];
        $expected = new Account($data);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account`'
               . ' WHERE email = :email'
               . ' LIMIT 1',
            bindings: ['email' => $email],
            result: [$data],
            times: 1
        );

        $actual = ah::CallMethod($sut, 'tryFindAccountByEmail', [$email]);

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion tryFindAccountByEmail
}
