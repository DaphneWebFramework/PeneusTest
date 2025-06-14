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
use \Peneus\Translation;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

class _TransactionalEmailSender { use TransactionalEmailSender; }

#[CoversClass(_TransactionalEmailSender::class)]
class TransactionalEmailSenderTest extends TestCase
{
    private ?Config $originalConfig = null;
    private ?Resource $originalResource = null;
    private ?Logger $originalLogger = null;
    private ?Translation $originalTranslation = null;

    protected function setUp(): void
    {
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
        $this->originalLogger =
            Logger::ReplaceInstance($this->createMock(Logger::class));
        $this->originalTranslation =
            Translation::ReplaceInstance($this->createMock(Translation::class));
    }

    protected function tearDown(): void
    {
        Config::ReplaceInstance($this->originalConfig);
        Resource::ReplaceInstance($this->originalResource);
        Logger::ReplaceInstance($this->originalLogger);
        Translation::ReplaceInstance($this->originalTranslation);
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
        $translation = Translation::Instance();
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
                <h1>{{MastheadText}}</h1>
                <h2>{{GreetingText}}</h2>
                <p>{{IntroText}}</p>
                <a href="{{ActionUrl}}">{{ButtonText}}</a>
                <p>{{SecurityNoticeText}}</p>
                <footer>
                <p>{{ContactUsText}} <a href="mailto:{{SupportEmail}}">{{SupportEmail}}</a></p>
                <p>{{CopyrightText}}</p>
                </footer>
                </body>
                </html>
            HTML);
        $file->expects($this->once())
            ->method('Close');
        $config->expects($this->exactly(3))
            ->method('OptionOrDefault')
            ->willReturnMap([
                ['Language'     , 'en' , 'en'],
                ['AppName'      , ''   , 'Example'],
                ['SupportEmail' , ''   , 'support@example.com']
            ]);
        $translation->expects($this->any())
            ->method('Get')
            ->willReturnCallback(function(string $key, mixed ...$args): string {
                return match ([$key, ...$args]) {
                    ['email_activate_account_masthead'] =>
                        'Welcome!',
                    ['email_common_greeting', 'John Doe'] =>
                        'Hi John Doe,',
                    ['email_activate_account_intro'] =>
                        "You're almost there! Just click the button below to activate your account.",
                    ['email_activate_account_button_text'] =>
                        'Activate My Account',
                    ['email_activate_account_security_notice', 'Example'] =>
                        'You received this email because your email address was used to register on Example.',
                    ['email_common_contact_us'] =>
                        'Need help? Contact us at',
                    ['email_common_copyright', '2020', 'Example'] =>
                        '© 2020 Example. All rights reserved.',
                    default =>
                        $this->fail("Unexpected translation key: {$key}")
                };
            });
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
            ->with('Welcome!')
            ->willReturnSelf();
        $mailer->expects($this->once())
            ->method('SetBody')
            ->with(<<<HTML
                <!DOCTYPE html>
                <html lang="en">
                <head><title>Welcome!</title></head>
                <h1>Welcome!</h1>
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
            'sendTransactionalEmail', [
                'john@example.com',
                'John Doe',
                'url/to/page/code123', [
                    'masthead' => 'email_activate_account_masthead',
                    'intro' => 'email_activate_account_intro',
                    'buttonText' => 'email_activate_account_button_text',
                    'securityNotice' => 'email_activate_account_security_notice'
                ]
            ]
        ));
    }
}
