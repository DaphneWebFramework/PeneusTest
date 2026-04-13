<?php declare(strict_types=1);
namespace suite\Api\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Traits\NotPendingEnsurer;

use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \TestToolkit\AccessHelper as ah;

class _NotPendingEnsurer {
    use NotPendingEnsurer;
}

#[CoversClass(_NotPendingEnsurer::class)]
class NotPendingEnsurerTest extends TestCase
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

    #region ensureNotPending ---------------------------------------------------

    function testEnsureNotPendingSucceedsIfEmailIsNotPending()
    {
        $sut = new _NotPendingEnsurer();
        $email = 'john@example.com';
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `pendingaccount`'
               . ' WHERE email = :email',
            bindings: ['email' => $email],
            result: [[0]],
            times: 1
        );

        $this->expectNotToPerformAssertions();

        ah::CallMethod($sut, 'ensureNotPending', [$email]);

        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testEnsureNotPendingThrowsIfEmailIsAlreadyPending()
    {
        $sut = new _NotPendingEnsurer();
        $email = 'john@example.com';
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `pendingaccount`'
               . ' WHERE email = :email',
            bindings: ['email' => $email],
            result: [[1]],
            times: 1
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("This account is already awaiting activation.");
        $this->expectExceptionCode(StatusCode::Conflict->value);

        ah::CallMethod($sut, 'ensureNotPending', [$email]);

        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion ensureNotPending
}
