<?php declare(strict_types=1);
namespace suite\Model\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Model\Traits\PasswordResetFinder;

use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\PasswordReset;
use \TestToolkit\AccessHelper as ah;

class _PasswordResetFinder {
    use PasswordResetFinder;
}

#[CoversClass(_PasswordResetFinder::class)]
class PasswordResetFinderTest extends TestCase
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

    #region tryFindPasswordResetByAccountId ------------------------------------

    function testTryFindPasswordResetByAccountIdReturnsNullIfNotFound()
    {
        $sut = new _PasswordResetFinder();
        $accountId = 42;
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset`'
               . ' WHERE accountId = :accountId'
               . ' LIMIT 1',
            bindings: ['accountId' => $accountId],
            result: null,
            times: 1
        );

        $actual = ah::CallMethod($sut, 'tryFindPasswordResetByAccountId', [$accountId]);

        $this->assertNull($actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testTryFindPasswordResetByAccountIdReturnsEntityIfFound()
    {
        $sut = new _PasswordResetFinder();
        $accountId = 42;
        $fakeDatabase = Database::Instance();
        $data = [
            'id'            => 1,
            'accountId'     => $accountId,
            'resetCode'     => 'code1234',
            'timeRequested' => '2026-04-13 10:00:00'
        ];
        $expected = new PasswordReset($data);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset`'
               . ' WHERE accountId = :accountId'
               . ' LIMIT 1',
            bindings: ['accountId' => $accountId],
            result: [$data],
            times: 1
        );

        $actual = ah::CallMethod($sut, 'tryFindPasswordResetByAccountId', [$accountId]);

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion tryFindPasswordResetByAccountId

    #region tryFindPasswordResetByCode -----------------------------------------

    function testTryFindPasswordResetByCodeReturnsNullIfNotFound()
    {
        $sut = new _PasswordResetFinder();
        $resetCode = 'code1234';
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset`'
               . ' WHERE resetCode = :resetCode'
               . ' LIMIT 1',
            bindings: ['resetCode' => $resetCode],
            result: null,
            times: 1
        );

        $actual = ah::CallMethod($sut, 'tryFindPasswordResetByCode', [$resetCode]);

        $this->assertNull($actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testTryFindPasswordResetByCodeReturnsEntityIfFound()
    {
        $sut = new _PasswordResetFinder();
        $resetCode = 'code1234';
        $fakeDatabase = Database::Instance();
        $data = [
            'id'            => 1,
            'accountId'     => 42,
            'resetCode'     => $resetCode,
            'timeRequested' => '2026-04-13 10:00:00'
        ];
        $expected = new PasswordReset($data);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset`'
               . ' WHERE resetCode = :resetCode'
               . ' LIMIT 1',
            bindings: ['resetCode' => $resetCode],
            result: [$data],
            times: 1
        );

        $actual = ah::CallMethod($sut, 'tryFindPasswordResetByCode', [$resetCode]);

        $this->assertEquals($expected, $actual);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion tryFindPasswordResetByCode
}
