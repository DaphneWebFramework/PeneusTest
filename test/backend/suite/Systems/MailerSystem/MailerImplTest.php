<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\MailerSystem\MailerImpl;

use \Harmonia\Logger;
use \Peneus\Systems\MailerSystem\MailerConfig;
use \PHPMailer\PHPMailer\PHPMailer;
use \PHPMailer\PHPMailer\SMTP;
use \TestToolkit\AccessHelper;

#[CoversClass(MailerImpl::class)]
class MailerImplTest extends TestCase
{
    private ?Logger $originalLogger = null;

    protected function setUp(): void
    {
        $this->originalLogger =
            Logger::ReplaceInstance($this->createMock(Logger::class));
    }

    protected function tearDown(): void
    {
        Logger::ReplaceInstance($this->originalLogger);
    }

    #region __construct --------------------------------------------------------

    function testConstructorWithRealWorldValues()
    {
        $mailerConfig = new MailerConfig();
        $mailerConfig->isHttps = true;
        $mailerConfig->host = 'smtp.example.com';
        $mailerConfig->port = 587;
        $mailerConfig->encryption = 'tls';
        $mailerConfig->username = 'user@example.com';
        $mailerConfig->password = 'pass1234';
        $mailerConfig->fromAddress = 'sender@example.com';
        $mailerConfig->fromName = 'Example Sender';
        $mailerConfig->logLevel = 0;

        $sut = new MailerImpl($mailerConfig);
        $phpMailer = AccessHelper::GetProperty($sut, 'phpMailer');

        $this->assertInstanceOf(PHPMailer::class, $phpMailer);
        $this->assertTrue(AccessHelper::GetProperty($phpMailer, 'exceptions'));
        $this->assertSame('smtp', $phpMailer->Mailer);
        $this->assertSame(PHPMailer::CHARSET_UTF8, $phpMailer->CharSet);
        $this->assertSame([], $phpMailer->SMTPOptions);
        $this->assertSame('smtp.example.com', $phpMailer->Host);
        $this->assertSame(587, $phpMailer->Port);
        $this->assertSame('tls', $phpMailer->SMTPSecure);
        $this->assertTrue($phpMailer->SMTPAuth);
        $this->assertSame('user@example.com', $phpMailer->Username);
        $this->assertSame('pass1234', $phpMailer->Password);
        $this->assertSame('sender@example.com', $phpMailer->From);
        $this->assertSame('Example Sender', $phpMailer->FromName);
        $this->assertSame(PHPMailer::CONTENT_TYPE_TEXT_HTML, $phpMailer->ContentType);
        $this->assertSame(SMTP::DEBUG_OFF, $phpMailer->SMTPDebug);
    }

    function testConstructorWithNoHttps()
    {
        $mailerConfig = new MailerConfig();
        $mailerConfig->isHttps = false; // non-HTTPS
        $mailerConfig->host = 'smtp.example.com';
        $mailerConfig->port = 587;
        $mailerConfig->encryption = 'tls';
        $mailerConfig->username = 'user@example.com';
        $mailerConfig->password = 'pass1234';
        $mailerConfig->fromAddress = 'sender@example.com';
        $mailerConfig->fromName = 'Example Sender';
        $mailerConfig->logLevel = 0;

        $sut = new MailerImpl($mailerConfig);
        $phpMailer = AccessHelper::GetProperty($sut, 'phpMailer');

        $this->assertArrayHasKey('ssl', $phpMailer->SMTPOptions);
        $this->assertSame([
            'verify_peer' => false,
            'verify_peer_name' => false
        ], $phpMailer->SMTPOptions['ssl']);
    }

    function testConstructorWithNonZeroLogLevel()
    {
        $mailerConfig = new MailerConfig();
        $mailerConfig->isHttps = true;
        $mailerConfig->host = 'smtp.example.com';
        $mailerConfig->port = 587;
        $mailerConfig->encryption = 'tls';
        $mailerConfig->username = 'user@example.com';
        $mailerConfig->password = 'pass1234';
        $mailerConfig->fromAddress = 'sender@example.com';
        $mailerConfig->fromName = 'Example Sender';
        $mailerConfig->logLevel = 1; // non-zero log level

        $sut = new MailerImpl($mailerConfig);
        $phpMailer = AccessHelper::GetProperty($sut, 'phpMailer');

        $this->assertSame(SMTP::DEBUG_SERVER, $phpMailer->SMTPDebug);
        $this->assertIsArray($phpMailer->Debugoutput);
        $this->assertSame($sut, $phpMailer->Debugoutput[0]);
        $this->assertSame('LoggerCallback', $phpMailer->Debugoutput[1]);
    }

    #endregion __construct

    #region SetAddress ---------------------------------------------------------

    function testSetAddress()
    {
        $mailerConfig = new MailerConfig();
        $mailerConfig->isHttps = true;
        $mailerConfig->host = 'smtp.example.com';
        $mailerConfig->port = 587;
        $mailerConfig->encryption = 'tls';
        $mailerConfig->username = 'user@example.com';
        $mailerConfig->password = 'pass1234';
        $mailerConfig->fromAddress = 'sender@example.com';
        $mailerConfig->fromName = 'Example Sender';
        $mailerConfig->logLevel = 0;

        $phpMailer = $this->getMockBuilder(PHPMailer::class)
            ->onlyMethods(['clearAddresses', 'addAddress'])
            ->getMock();
        $phpMailer->expects($this->once())
            ->method('clearAddresses');
        $phpMailer->expects($this->once())
            ->method('addAddress')
            ->with('someone@example.com');

        $sut = new MailerImpl($mailerConfig, $phpMailer);
        $this->assertSame($sut, $sut->SetAddress('someone@example.com'));
    }

    #endregion SetAddress

    #region SetSubject ---------------------------------------------------------

    function testSetSubject()
    {
        $mailerConfig = new MailerConfig();
        $mailerConfig->isHttps = true;
        $mailerConfig->host = 'smtp.example.com';
        $mailerConfig->port = 587;
        $mailerConfig->encryption = 'tls';
        $mailerConfig->username = 'user@example.com';
        $mailerConfig->password = 'pass1234';
        $mailerConfig->fromAddress = 'sender@example.com';
        $mailerConfig->fromName = 'Example Sender';
        $mailerConfig->logLevel = 0;

        $phpMailer = $this->createMock(PHPMailer::class);
        $sut = new MailerImpl($mailerConfig, $phpMailer);

        $this->assertSame($sut, $sut->SetSubject('Test Subject'));
        $this->assertSame('Test Subject', $phpMailer->Subject);
    }

    #endregion SetSubject

    #region SetBody ------------------------------------------------------------

    function testSetBody()
    {
        $mailerConfig = new MailerConfig();
        $mailerConfig->isHttps = true;
        $mailerConfig->host = 'smtp.example.com';
        $mailerConfig->port = 587;
        $mailerConfig->encryption = 'tls';
        $mailerConfig->username = 'user@example.com';
        $mailerConfig->password = 'pass1234';
        $mailerConfig->fromAddress = 'sender@example.com';
        $mailerConfig->fromName = 'Example Sender';
        $mailerConfig->logLevel = 0;

        $phpMailer = $this->createMock(PHPMailer::class);
        $sut = new MailerImpl($mailerConfig, $phpMailer);

        $this->assertSame($sut, $sut->SetBody('<p>Hello</p>'));
        $this->assertSame('<p>Hello</p>', $phpMailer->Body);
    }

    #endregion SetBody

    #region Send ---------------------------------------------------------------

    function testSendReturnsTrueOnSuccess()
    {
        $mailerConfig = new MailerConfig();
        $mailerConfig->isHttps = true;
        $mailerConfig->host = 'smtp.example.com';
        $mailerConfig->port = 587;
        $mailerConfig->encryption = 'tls';
        $mailerConfig->username = 'user@example.com';
        $mailerConfig->password = 'pass1234';
        $mailerConfig->fromAddress = 'sender@example.com';
        $mailerConfig->fromName = 'Example Sender';
        $mailerConfig->logLevel = 0;

        $phpMailer = $this->createMock(PHPMailer::class);
        $phpMailer->expects($this->once())
            ->method('send')
            ->willReturn(true);

        $sut = new MailerImpl($mailerConfig, $phpMailer);
        $this->assertTrue($sut->Send());
    }

    function testSendReturnsFalseOnExceptionAndLogsError()
    {
        $mailerConfig = new MailerConfig();
        $mailerConfig->isHttps = true;
        $mailerConfig->host = 'smtp.example.com';
        $mailerConfig->port = 587;
        $mailerConfig->encryption = 'tls';
        $mailerConfig->username = 'user@example.com';
        $mailerConfig->password = 'pass1234';
        $mailerConfig->fromAddress = 'sender@example.com';
        $mailerConfig->fromName = 'Example Sender';
        $mailerConfig->logLevel = 0;

        $phpMailer = $this->createMock(PHPMailer::class);
        $phpMailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('Simulated failure'));

        $logger = Logger::Instance();
        $logger->expects($this->once())
            ->method('Error')
            ->with($this->stringContains('Mailer: Simulated failure'));

        $sut = new MailerImpl($mailerConfig, $phpMailer);
        $this->assertFalse($sut->Send());
    }

    #endregion Send

    #region LoggerCallback -----------------------------------------------------

    function testLoggerCallback()
    {
        $mailerConfig = new MailerConfig();
        $mailerConfig->isHttps = true;
        $mailerConfig->host = 'smtp.example.com';
        $mailerConfig->port = 587;
        $mailerConfig->encryption = 'tls';
        $mailerConfig->username = 'user@example.com';
        $mailerConfig->password = 'pass1234';
        $mailerConfig->fromAddress = 'sender@example.com';
        $mailerConfig->fromName = 'Example Sender';
        $mailerConfig->logLevel = 1;

        $logger = Logger::Instance();
        $logger->expects($this->once())
            ->method('Info')
            ->with('Mailer (level 2): debug message');

        $sut = new MailerImpl($mailerConfig, $this->createStub(PHPMailer::class));
        $sut->LoggerCallback('debug message', 2);
    }

    #endregion LoggerCallback
}
