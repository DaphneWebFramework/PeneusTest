<?php declare(strict_types=1);
namespace suite\Api\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Traits\PendingAccountFinder;

use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\PendingAccount;
use \TestToolkit\AccessHelper as ah;

class _PendingAccountFinder {
    use PendingAccountFinder;
}

#[CoversClass(_PendingAccountFinder::class)]
class PendingAccountFinderTest extends TestCase
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

    #region findPendingAccount -------------------------------------------------

    function testFindPendingAccountThrowsIfNotFound()
    {
        $sut = new _PendingAccountFinder();
        $activationCode = 'code1234';
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `pendingaccount`'
               . ' WHERE activationCode = :activationCode'
               . ' LIMIT 1',
            bindings: ['activationCode' => $activationCode],
            result: null,
            times: 1
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "No account is awaiting activation for the given code.");
        $this->expectExceptionCode(StatusCode::NotFound->value);

        ah::CallMethod($sut, 'findPendingAccount', [$activationCode]);

        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindPendingAccountReturnsEntityIfFound()
    {
        $sut = new _PendingAccountFinder();
        $activationCode = 'code1234';
        $fakeDatabase = Database::Instance();
        $data = [
            'id'             => 10,
            'email'          => 'john@example.com',
            'passwordHash'   => 'hash1234',
            'displayName'    => 'John',
            'activationCode' => $activationCode,
            'timeRegistered' => '2026-04-13 12:00:00'
        ];
        $expected = new PendingAccount($data);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `pendingaccount`'
               . ' WHERE activationCode = :activationCode'
               . ' LIMIT 1',
            bindings: ['activationCode' => $activationCode],
            result: [$data],
            times: 1
        );

        $actual = ah::CallMethod($sut, 'findPendingAccount', [$activationCode]);

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findPendingAccount
}
