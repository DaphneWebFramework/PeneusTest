<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\MailerSystem\Mailer;

use \Harmonia\Config;
use \Harmonia\Logger;
use \Harmonia\Server;
use \Peneus\Systems\MailerSystem\FakeMailerImpl;
use \Peneus\Systems\MailerSystem\IMailerImpl;
use \Peneus\Systems\MailerSystem\MailerConfig;
use \Peneus\Systems\MailerSystem\MailerImpl;
use \TestToolkit\AccessHelper;

#[CoversClass(Mailer::class)]
class MailerTest extends TestCase
{
    private ?Config $originalConfig = null;
    private ?Server $originalServer = null;
    private ?Logger $originalLogger = null;

    protected function setUp(): void
    {
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalServer =
            Server::ReplaceInstance($this->createMock(Server::class));
        $this->originalLogger =
            Logger::ReplaceInstance($this->createMock(Logger::class));
    }

    protected function tearDown(): void
    {
        Config::ReplaceInstance($this->originalConfig);
        Server::ReplaceInstance($this->originalServer);
        Logger::ReplaceInstance($this->originalLogger);
    }

    private function systemUnderTest(string ...$mockedMethods): Mailer
    {
        return $this->getMockBuilder(Mailer::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region __construct --------------------------------------------------------

    function testConstructorInstantiatesFakeMailerImplWhenDebugModeIsEnabled()
    {
        $config = Config::Instance();

        $config->expects($this->once())
            ->method('Option')
            ->with('IsDebug')
            ->willReturn(true);

        $sut = new Mailer();
        $impl = AccessHelper::GetProperty($sut, 'impl');
        $this->assertInstanceOf(FakeMailerImpl::class, $impl);
    }

    function testConstructorInstantiatesMailerImplWhenDebugModeIsDisabled()
    {
        $config = Config::Instance();
        $server = Server::Instance();

        $config->expects($this->once())
            ->method('Option')
            ->with('IsDebug')
            ->willReturn(false);
        $server->expects($this->once())
            ->method('IsSecure')
            ->willReturn(true);
        $config->method('OptionOrDefault')
            ->willReturnCallback(function(string $key, $default) {
                return match ($key) {
                    'MailerHost' => 'smtp.example.com',
                    'MailerPort' => 587,
                    'MailerEncryption' => 'tls',
                    'MailerUsername' => 'user@example.com',
                    'MailerPassword' => 'pass1234',
                    'AppName' => 'MyApp',
                    'LogLevel' => 0,
                    default => null,
                };
            });

        $sut = new Mailer();
        $impl = AccessHelper::GetProperty($sut, 'impl');
        $this->assertInstanceOf(MailerImpl::class, $impl);
    }

    #endregion __construct

    #region SetAddress ---------------------------------------------------------

    function testSetAddress()
    {
        $sut = $this->systemUnderTest();
        $impl = $this->createMock(IMailerImpl::class);

        $impl->expects($this->once())
            ->method('SetAddress')
            ->with('john@example.com')
            ->willReturn($impl);

        AccessHelper::SetProperty($sut, 'impl', $impl);
        $this->assertSame($sut, $sut->SetAddress('john@example.com'));
    }

    #endregion SetAddress

    #region SetSubject ---------------------------------------------------------

    function testSetSubject()
    {
        $sut = $this->systemUnderTest();
        $impl = $this->createMock(IMailerImpl::class);

        $impl->expects($this->once())
            ->method('SetSubject')
            ->with('Test Subject')
            ->willReturn($impl);

        AccessHelper::SetProperty($sut, 'impl', $impl);
        $this->assertSame($sut, $sut->SetSubject('Test Subject'));
    }

    #endregion SetSubject

    #region SetBody ------------------------------------------------------------

    function testSetBody()
    {
        $sut = $this->systemUnderTest();
        $impl = $this->createMock(IMailerImpl::class);

        $impl->expects($this->once())
            ->method('SetBody')
            ->with('<p>Hello</p>')
            ->willReturn($impl);

        AccessHelper::SetProperty($sut, 'impl', $impl);
        $this->assertSame($sut, $sut->SetBody('<p>Hello</p>'));
    }

    #endregion SetBody

    #region Send ---------------------------------------------------------------

    function testSend()
    {
        $sut = $this->systemUnderTest();
        $impl = $this->createMock(IMailerImpl::class);

        $impl->expects($this->once())
            ->method('Send')
            ->willReturn(true);

        AccessHelper::SetProperty($sut, 'impl', $impl);
        $this->assertTrue($sut->Send());
    }

    #endregion Send
}
