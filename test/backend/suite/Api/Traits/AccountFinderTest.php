<?php declare(strict_types=1);
namespace suite\Api\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Traits\AccountFinder;

use \Harmonia\Http\StatusCode;
use \Peneus\Model\Account;
use \TestToolkit\AccessHelper as ah;

class _AccountFinder {
    use AccountFinder;
}

#[CoversClass(_AccountFinder::class)]
class AccountFinderTest extends TestCase
{
    private function systemUnderTest(string ...$mockedMethods): _AccountFinder
    {
        return $this->getMockBuilder(_AccountFinder::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region findAccount --------------------------------------------------------

    function testFindAccountThrowsIfNotFound()
    {
        $sut = $this->systemUnderTest('tryFindAccountById');
        $id = 42;

        $sut->expects($this->once())
            ->method('tryFindAccountById')
            ->with($id)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Account not found.");
        $this->expectExceptionCode(StatusCode::NotFound->value);

        ah::CallMethod($sut, 'findAccount', [$id]);
    }

    function testFindAccountReturnsEntityIfFound()
    {
        $sut = $this->systemUnderTest('tryFindAccountById');
        $id = 42;
        $expected = new Account([
            'id'            => $id,
            'email'         => 'john@example.com',
            'passwordHash'  => 'hash1234',
            'displayName'   => 'John',
            'timeActivated' => '2024-01-01 00:00:00',
            'timeLastLogin' => null
        ]);

        $sut->expects($this->once())
            ->method('tryFindAccountById')
            ->with($id)
            ->willReturn($expected);

        $actual = ah::CallMethod($sut, 'findAccount', [$id]);

        $this->assertSame($expected, $actual);
    }

    #endregion findAccount
}
