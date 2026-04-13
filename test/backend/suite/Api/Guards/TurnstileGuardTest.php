<?php declare(strict_types=1);
namespace suite\Api\Guards;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Guards\TurnstileGuard;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Client;
use \Harmonia\Http\Request;
use \Harmonia\Server;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(TurnstileGuard::class)]
class TurnstileGuardTest extends TestCase
{
    private ?Client $client = null;
    private ?Request $originalRequest = null;
    private ?Config $originalConfig = null;
    private ?Server $originalServer = null;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalServer =
            Server::ReplaceInstance($this->createMock(Server::class));
    }

    protected function tearDown(): void
    {
        $this->client = null;
        Request::ReplaceInstance($this->originalRequest);
        Config::ReplaceInstance($this->originalConfig);
        Server::ReplaceInstance($this->originalServer);
    }

    private function systemUnderTest(string ...$mockedMethods): TurnstileGuard
    {
        return $this->getMockBuilder(TurnstileGuard::class)
            ->setConstructorArgs([$this->client])
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region Verify -------------------------------------------------------------

    function testVerifyFailsIfPayloadValidationFails()
    {
        $sut = $this->systemUnderTest('validatePayload', 'verifyToken');

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn(null);
        $sut->expects($this->never())
            ->method('verifyToken');

        $this->assertFalse($sut->Verify());
    }

    function testVerifyFailsIfTokenVerificationFails()
    {
        $sut = $this->systemUnderTest('validatePayload', 'verifyToken');
        $payload = (object)['token' => 'token123'];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('verifyToken')
            ->with($payload->token)
            ->willReturn(false);

        $this->assertFalse($sut->Verify());
    }

    function testVerifySucceeds()
    {
        $sut = $this->systemUnderTest('validatePayload', 'verifyToken');
        $payload = (object)['token' => 'token123'];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('verifyToken')
            ->with($payload->token)
            ->willReturn(true);

        $this->assertTrue($sut->Verify());
    }

    #endregion Verify

    #region validatePayload ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
    function testValidatePayloadFails(array $payload)
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $actual = ah::CallMethod($sut, 'validatePayload');

        $this->assertNull($actual);
    }

    function testValidatePayloadSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $payload = ['cf-turnstile-response' => 'token123'];
        $expected = (object)['token' => 'token123'];

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $actual = ah::CallMethod($sut, 'validatePayload');

        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region verifyToken --------------------------------------------------------

    function testVerifyTokenFailsIfClientSendFails()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $server = Server::Instance();
        $token = 'token123';
        $secretKey = 'secret-key';
        $clientAddress = '127.0.0.1';

        $config->expects($this->once())
            ->method('Option')
            ->with('Cloudflare.Turnstile.SecretKey')
            ->willReturn($secretKey);
        $server->expects($this->once())
            ->method('ClientAddress')
            ->willReturn($clientAddress);
        $this->client->expects($this->once())
            ->method('Post')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Url')
            ->with('https://challenges.cloudflare.com/turnstile/v0/siteverify')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Body')
            ->with(\http_build_query([
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $clientAddress
            ]))
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Send')
            ->willReturn(false);

        $this->assertFalse(ah::CallMethod($sut, 'verifyToken', [$token]));
    }

    function testVerifyTokenFailsIfStatusIsNot200()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $server = Server::Instance();
        $token = 'token123';
        $secretKey = 'secret-key';
        $clientAddress = '127.0.0.1';

        $config->expects($this->once())
            ->method('Option')
            ->with('Cloudflare.Turnstile.SecretKey')
            ->willReturn($secretKey);
        $server->expects($this->once())
            ->method('ClientAddress')
            ->willReturn($clientAddress);
        $this->client->expects($this->once())
            ->method('Post')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Url')
            ->with('https://challenges.cloudflare.com/turnstile/v0/siteverify')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Body')
            ->with(\http_build_query([
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $clientAddress
            ]))
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Send')
            ->willReturn(true);
        $this->client->expects($this->once())
            ->method('StatusCode')
            ->willReturn(403);

        $this->assertFalse(ah::CallMethod($sut, 'verifyToken', [$token]));
    }

    function testVerifyTokenFailsIfBodyCannotBeDecoded()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $server = Server::Instance();
        $token = 'token123';
        $secretKey = 'secret-key';
        $clientAddress = '127.0.0.1';
        $bodyPayload = \http_build_query([
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $clientAddress
        ]);

        $config->expects($this->once())
            ->method('Option')
            ->with('Cloudflare.Turnstile.SecretKey')
            ->willReturn($secretKey);
        $server->expects($this->once())
            ->method('ClientAddress')
            ->willReturn($clientAddress);
        $this->client->expects($this->once())
            ->method('Post')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Url')
            ->with('https://challenges.cloudflare.com/turnstile/v0/siteverify')
            ->willReturnSelf();
        $this->client->expects($this->exactly(2))
            ->method('Body')
            ->willReturnMap([
                [$bodyPayload, $this->client],
                [null, 'invalid-json']
            ]);
        $this->client->expects($this->once())
            ->method('Send')
            ->willReturn(true);
        $this->client->expects($this->once())
            ->method('StatusCode')
            ->willReturn(200);

        $this->assertFalse(ah::CallMethod($sut, 'verifyToken', [$token]));
    }

    function testVerifyTokenFailsIfSuccessKeyIsMissingInBody()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $server = Server::Instance();
        $token = 'token123';
        $secretKey = 'secret-key';
        $clientAddress = '127.0.0.1';
        $bodyPayload = \http_build_query([
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $clientAddress
        ]);

        $config->expects($this->once())
            ->method('Option')
            ->with('Cloudflare.Turnstile.SecretKey')
            ->willReturn($secretKey);
        $server->expects($this->once())
            ->method('ClientAddress')
            ->willReturn($clientAddress);
        $this->client->expects($this->once())
            ->method('Post')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Url')
            ->with('https://challenges.cloudflare.com/turnstile/v0/siteverify')
            ->willReturnSelf();
        $this->client->expects($this->exactly(2))
            ->method('Body')
            ->willReturnMap([
                [$bodyPayload, $this->client],
                [null, '{}']
            ]);
        $this->client->expects($this->once())
            ->method('Send')
            ->willReturn(true);
        $this->client->expects($this->once())
            ->method('StatusCode')
            ->willReturn(200);

        $this->assertFalse(ah::CallMethod($sut, 'verifyToken', [$token]));
    }

    function testVerifyTokenFailsIfSuccessIsFalseInBody()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $server = Server::Instance();
        $token = 'token123';
        $secretKey = 'secret-key';
        $clientAddress = '127.0.0.1';
        $bodyPayload = \http_build_query([
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $clientAddress
        ]);

        $config->expects($this->once())
            ->method('Option')
            ->with('Cloudflare.Turnstile.SecretKey')
            ->willReturn($secretKey);
        $server->expects($this->once())
            ->method('ClientAddress')
            ->willReturn($clientAddress);
        $this->client->expects($this->once())
            ->method('Post')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Url')
            ->with('https://challenges.cloudflare.com/turnstile/v0/siteverify')
            ->willReturnSelf();
        $this->client->expects($this->exactly(2))
            ->method('Body')
            ->willReturnMap([
                [$bodyPayload, $this->client],
                [null, '{"success": false}']
            ]);
        $this->client->expects($this->once())
            ->method('Send')
            ->willReturn(true);
        $this->client->expects($this->once())
            ->method('StatusCode')
            ->willReturn(200);

        $this->assertFalse(ah::CallMethod($sut, 'verifyToken', [$token]));
    }

    function testVerifyTokenSucceeds()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();
        $server = Server::Instance();
        $token = 'token123';
        $secretKey = 'secret-key';
        $clientAddress = '127.0.0.1';
        $bodyPayload = \http_build_query([
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $clientAddress
        ]);

        $config->expects($this->once())
            ->method('Option')
            ->with('Cloudflare.Turnstile.SecretKey')
            ->willReturn($secretKey);
        $server->expects($this->once())
            ->method('ClientAddress')
            ->willReturn($clientAddress);
        $this->client->expects($this->once())
            ->method('Post')
            ->willReturnSelf();
        $this->client->expects($this->once())
            ->method('Url')
            ->with('https://challenges.cloudflare.com/turnstile/v0/siteverify')
            ->willReturnSelf();
        $this->client->expects($this->exactly(2))
            ->method('Body')
            ->willReturnMap([
                [$bodyPayload, $this->client],
                [null, '{"success": true}']
            ]);
        $this->client->expects($this->once())
            ->method('Send')
            ->willReturn(true);
        $this->client->expects($this->once())
            ->method('StatusCode')
            ->willReturn(200);

        $this->assertTrue(ah::CallMethod($sut, 'verifyToken', [$token]));
    }

    #endregion verifyToken

    #region Data Providers -----------------------------------------------------

    /**
     * @return array<string, array{
     *   payload: array<string, mixed>
     * }>
     */
    static function invalidPayloadProvider()
    {
        return [
            'token missing' => [
                'payload' => []
            ],
            'token not a string' => [
                'payload' => [
                    'cf-turnstile-response' => 42
                ]
            ],
            'token empty' => [
                'payload' => [
                    'cf-turnstile-response' => ''
                ]
            ],
            'token too long' => [
                'payload' => [
                    'cf-turnstile-response' => str_repeat('a', 2049)
                ]
            ],
        ];
    }

    #endregion Data Providers
}
