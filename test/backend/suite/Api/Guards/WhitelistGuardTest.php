<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Guards\WhitelistGuard;

use \Harmonia\Server;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(WhitelistGuard::class)]
class WhitelistGuardTest extends TestCase
{
    private ?Server $originalServer = null;

    protected function setUp(): void
    {
        $this->originalServer =
            Server::ReplaceInstance($this->createMock(Server::class));
    }

    protected function tearDown(): void
    {
        Server::ReplaceInstance($this->originalServer);
    }

    #region Verify -------------------------------------------------------------

    function testVerifyReturnsFalseIfWhitelistIsEmpty()
    {
        $sut = new WhitelistGuard();
        $server = Server::Instance();

        $server->expects($this->never())
            ->method('ClientAddress');

        $this->assertFalse($sut->Verify());
    }

    #[DataProvider('verifyDataProvider')]
    function testVerify($expected, array $whitelist, string $ip)
    {
        $sut = new WhitelistGuard(...$whitelist);
        $server = Server::Instance();

        $server->expects($this->once())
            ->method('ClientAddress')
            ->willReturn($ip);

        $this->assertSame($expected, $sut->Verify());
    }

    #endregion Verify

    #region inCidr -------------------------------------------------------------

    #[DataProvider('inCidrDataProvider')]
    function testInCidr($expected, $ip, $cidr)
    {
        $sut = new WhitelistGuard();
        $actual = ah::CallMethod($sut, 'inCidr', [$ip, $cidr]);
        $this->assertSame($expected, $actual);
    }

    #endregion inCidr ----------------------------------------------------------

    #region Data Providers -----------------------------------------------------

    static function verifyDataProvider()
    {
        return [
            'single ip no match' =>
                [false, ['127.0.0.1'], '127.0.0.2'],
            'single ip match' =>
                [true,  ['127.0.0.1'], '127.0.0.1'],
            'single cidr no match' =>
                [false, ['192.168.1.0/24'], '192.168.2.42'],
            'single cidr match' =>
                [true,  ['192.168.1.0/24'], '192.168.1.42'],
            'multiple ip match' =>
                [true,  ['127.0.0.1', '127.0.0.2'], '127.0.0.2'],
            'multiple ip no match' =>
                [false, ['127.0.0.1', '127.0.0.2'], '127.0.0.3'],
            'multiple cidr no match' =>
                [false, ['192.168.1.0/24', '10.0.0.0/8'], '172.16.0.1'],
            'multiple cidr match' =>
                [true,  ['192.168.1.0/24', '10.0.0.0/8'], '10.0.0.5'],
            'mixed whitelist no match' =>
                [false, ['127.0.0.1', '192.168.1.0/24'], '10.0.0.5'],
            'mixed whitelist exact match' =>
                [true,  ['127.0.0.1', '192.168.1.0/24'], '127.0.0.1'],
            'mixed whitelist cidr match' =>
                [true,  ['127.0.0.1', '192.168.1.0/24'], '192.168.1.42'],
            'invalid entry no match' =>
                [false, ['not_an_ip'], '127.0.0.1'],
            'ipv6 entry no match' =>
                [false, ['2001:db8::/64'], '2001:db8::1'],
        ];
    }

    static function inCidrDataProvider()
    {
        return [
            [true,  '192.168.1.42', '192.168.1.0/24'],  // inside range
            [false, '192.168.2.42', '192.168.1.0/24'],  // outside range

            [true,  '203.0.113.5',  '203.0.113.5/32'],  // /32 exact match
            [false, '203.0.113.6',  '203.0.113.5/32'],  // /32 non-match
            [true,  '8.8.8.8',      '0.0.0.0/0'],       // /0 matches all

            [true,  '10.0.0.0',     '10.0.0.0/8'],      // first IP in subnet
            [true,  '10.255.255.255','10.0.0.0/8'],     // last IP in subnet

            [false, '2001:db8::1', '2001:db8::/64'],    // IPv6 address and CIDR
            [false, '2001:db8::1', '192.168.1.0/24'],   // IPv6 address with IPv4 CIDR

            [false, 'not_an_ip',    '192.168.1.0/24'],  // invalid IP
            [false, '192.168.1.42', '192.168.1.0'],     // missing mask
            [false, '192.168.1.42', '192.168.1.0/abc'], // non-numeric mask
            [false, '192.168.1.42', 'not_an_ip/24'],    // invalid subnet
            [false, '192.168.1.42', '192.168.1.0/33'],  // mask too large
            [false, '192.168.1.42', '192.168.1.0/-1'],  // mask negative
        ];
    }

    #endregion Data Providers
}
