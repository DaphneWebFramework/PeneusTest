<?php declare(strict_types=1);
namespace suite\Api\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Traits\NotRegisteredEnsurer;

use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \TestToolkit\AccessHelper as ah;

class _NotRegisteredEnsurer {
    use NotRegisteredEnsurer;
}

#[CoversClass(_NotRegisteredEnsurer::class)]
class NotRegisteredEnsurerTest extends TestCase
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

    #region ensureNotRegistered ------------------------------------------------

    function testEnsureNotRegisteredSucceedsIfEmailIsAvailable()
    {
        $sut = new _NotRegisteredEnsurer();
        $email = 'john@example.com';
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `account`'
               . ' WHERE email = :email',
            bindings: ['email' => $email],
            result: [[0]],
            times: 1
        );

        $this->expectNotToPerformAssertions();

        ah::CallMethod($sut, 'ensureNotRegistered', [$email]);

        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testEnsureNotRegisteredThrowsIfEmailIsAlreadyRegistered()
    {
        $sut = new _NotRegisteredEnsurer();
        $email = 'john@example.com';
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `account`'
               . ' WHERE email = :email',
            bindings: ['email' => $email],
            result: [[1]],
            times: 1
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("This account is already registered.");
        $this->expectExceptionCode(StatusCode::Conflict->value);

        ah::CallMethod($sut, 'ensureNotRegistered', [$email]);

        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion ensureNotRegistered
}
