<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Traits\TransactionalEmailSender;

use \Harmonia\Config;
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Logger;
use \Peneus\Resource;
use \Peneus\Systems\MailerSystem\Mailer;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

class _TransactionalEmailSender { use TransactionalEmailSender; }

#[CoversClass(_TransactionalEmailSender::class)]
class TransactionalEmailSenderTest extends TestCase
{
    private ?Config $originalConfig = null;
    private ?Resource $originalResource = null;
    private ?Logger $originalLogger = null;

    protected function setUp(): void
    {
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
        $this->originalLogger =
            Logger::ReplaceInstance($this->createMock(Logger::class));
    }

    protected function tearDown(): void
    {
        Config::ReplaceInstance($this->originalConfig);
        Resource::ReplaceInstance($this->originalResource);
        Logger::ReplaceInstance($this->originalLogger);
    }

    private function systemUnderTest(string ...$mockedMethods): _TransactionalEmailSender
    {
        return $this->getMockBuilder(_TransactionalEmailSender::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    function testReturnsFalseIfFileOpenFails()
    {
        $sut = $this->systemUnderTest('openFile');
        $resource = Resource::Instance();
        $logger = Logger::Instance();
        $path = new CPath('path/to/template.html');

        $resource->expects($this->once())
            ->method('TemplateFilePath')
            ->with('transactional-email')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn(null);
        $logger->expects($this->once())
            ->method('Error')
            ->with('Email template not found.');

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'sendTransactionalEmail',
            ['', '', '', []]
        ));
    }

    function testReturnsFalseIfTemplateReadFails()
    {
        $sut = $this->systemUnderTest('openFile');
        $resource = Resource::Instance();
        $logger = Logger::Instance();
        $path = new CPath('path/to/template.html');
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('TemplateFilePath')
            ->with('transactional-email')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(null);
        $file->expects($this->once())
            ->method('Close');
        $logger->expects($this->once())
            ->method('Error')
            ->with('Email template could not be read.');

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'sendTransactionalEmail',
            ['', '', '', []]
        ));
    }

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testReturnsExpectedResultIfEmailIsSent($returnValue)
    {
        $sut = $this->systemUnderTest('openFile', 'newMailer', 'currentYear');
        $resource = Resource::Instance();
        $logger = Logger::Instance();
        $path = new CPath('path/to/template.html');
        $file = $this->createMock(CFile::class);
        $config = Config::Instance();
        $mailer = $this->createMock(Mailer::class);

        $resource->expects($this->once())
            ->method('TemplateFilePath')
            ->with('transactional-email')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<HTML
                <!DOCTYPE html>
                <html lang="{{Language}}">
                <head><title>{{Title}}</title></head>
                <h1>{{HeroText}}</h1>
                <h2>Hi {{UserName}},</h2>
                <p>{{IntroText}}</p>
                <a href="{{ActionUrl}}">{{ButtonText}}</a>
                <p>{{DisclaimerText}}</p>
                <footer>
                <p>Need help? Contact us at <a href="mailto:{{SupportEmail}}">{{SupportEmail}}</a></p>
                <p>© {{CurrentYear}} {{AppName}}. All rights reserved.</p>
                </footer>
                </body>
                </html>
            HTML);
        $file->expects($this->once())
            ->method('Close');
        $config->expects($this->exactly(3))
            ->method('OptionOrDefault')
            ->willReturnMap([
                ['AppName'     , ''  , 'Example'],
                ['Language'    , 'en', 'en'],
                ['SupportEmail', ''  , 'support@example.com']
            ]);
        $sut->expects($this->once())
            ->method('currentYear')
            ->willReturn('2020');
        $sut->expects($this->once())
            ->method('newMailer')
            ->willReturn($mailer);
        $mailer->expects($this->once())
            ->method('SetAddress')
            ->with('john@example.com')
            ->willReturnSelf();
        $mailer->expects($this->once())
            ->method('SetSubject')
            ->with('Welcome to Example!')
            ->willReturnSelf();
        $mailer->expects($this->once())
            ->method('SetBody')
            ->with(<<<HTML
                <!DOCTYPE html>
                <html lang="en">
                <head><title>Welcome to Example!</title></head>
                <h1>Welcome to Example!</h1>
                <h2>Hi John Doe,</h2>
                <p>You're almost there! Just click the button below to activate your account.</p>
                <a href="url/to/page/code123">Activate My Account</a>
                <p>You received this email because your email address was used to register on Example.</p>
                <footer>
                <p>Need help? Contact us at <a href="mailto:support@example.com">support@example.com</a></p>
                <p>© 2020 Example. All rights reserved.</p>
                </footer>
                </body>
                </html>
            HTML)
            ->willReturnSelf();
        $mailer->expects($this->once())
            ->method('Send')
            ->willReturn($returnValue);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'sendTransactionalEmail',
            [
                'john@example.com',
                'John Doe',
                'url/to/page/code123',
                [
                    'heroText' =>
                        "Welcome to Example!",
                    'introText' =>
                        "You're almost there! Just click the button below to"
                      . " activate your account.",
                    'buttonText' =>
                        "Activate My Account",
                    'disclaimerText' =>
                        "You received this email because your email address"
                      . " was used to register on Example."
                ]
            ]
        ));
    }
}
