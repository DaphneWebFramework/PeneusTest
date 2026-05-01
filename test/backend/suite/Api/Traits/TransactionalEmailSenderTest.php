<?php declare(strict_types=1);
namespace suite\Api\Traits;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Api\Traits\TransactionalEmailSender;

use \Harmonia\Config;
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Core\CUrl;
use \Peneus\Resource;
use \Peneus\Systems\MailerSystem\Mailer;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

class _TransactionalEmailSenderWithMembers {
    use TransactionalEmailSender;
    private readonly Resource $resource;
    private readonly Config $config;
    public function __construct() {
        $this->config = Config::Instance();
        $this->resource = Resource::Instance();
    }
}
class _TransactionalEmailSenderWithoutMembers {
    use TransactionalEmailSender;
}

#[CoversClass(_TransactionalEmailSenderWithMembers::class)]
#[CoversClass(_TransactionalEmailSenderWithoutMembers::class)]
class TransactionalEmailSenderTest extends TestCase
{
    private ?Config $originalConfig = null;
    private ?Resource $originalResource = null;

    protected function setUp(): void
    {
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
    }

    protected function tearDown(): void
    {
        Config::ReplaceInstance($this->originalConfig);
        Resource::ReplaceInstance($this->originalResource);
    }

    private function systemUnderTest(
        bool $hasMembers = true,
        string ...$mockedMethods
    ): object
    {
        $className = $hasMembers
            ? _TransactionalEmailSenderWithMembers::class
            : _TransactionalEmailSenderWithoutMembers::class;
        return $this->getMockBuilder($className)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region sendTransactionalEmail ---------------------------------------------

    private function contextForSendTransactionalEmail(
        bool $hasMembers = true,
        bool $templateReadSucceeds = true,
        bool $emailSendSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            $hasMembers,
            'readTransactionalEmailTemplate',
            'composeTransactionalEmail',
            'makeMailer'
        );
        $ctx->emailAddress = 'john@example.com';
        $ctx->displayName = 'John';
        $ctx->actionUrl = $this->createStub(CUrl::class);
        $ctx->substitutions = [
            'heroText'       => 'HERO_TEXT',
            'introText'      => 'INTRO_TEXT',
            'buttonText'     => 'BUTTON_TEXT',
            'disclaimerText' => 'DISCLAIMER_TEXT'
        ];
        $template = 'TEMPLATE';
        $composed = 'COMPOSED';
        $mailer = $this->createMock(Mailer::class);

        $ctx->sut->expects($ctx->chain())
            ->method('readTransactionalEmailTemplate')
            ->willReturn($templateReadSucceeds ? $template : null);
        $ctx->sut->expects($ctx->chainIf($templateReadSucceeds))
            ->method('composeTransactionalEmail')
            ->with($template, $ctx->displayName, $ctx->actionUrl, $ctx->substitutions)
            ->willReturn($composed);
        $ctx->sut->expects($ctx->chain())
            ->method('makeMailer')
            ->willReturn($mailer);
        $mailer->expects($ctx->chain())
            ->method('SetAddress')
            ->with($ctx->emailAddress)
            ->willReturnSelf();
        $mailer->expects($ctx->chain())
            ->method('SetSubject')
            ->with($ctx->substitutions['heroText'])
            ->willReturnSelf();
        $mailer->expects($ctx->chain())
            ->method('SetBody')
            ->with($composed)
            ->willReturnSelf();
        $mailer->expects($ctx->chain())
            ->method('Send')
            ->willReturn($emailSendSucceeds);

        return $ctx;
    }

    function testSendTransactionalEmailFailsIfTemplateReadFails()
    {
        $ctx = $this->contextForSendTransactionalEmail(templateReadSucceeds: false);
        $actual = ah::CallMethod($ctx->sut, 'sendTransactionalEmail', [
            $ctx->emailAddress,
            $ctx->displayName,
            $ctx->actionUrl,
            $ctx->substitutions
        ]);
        $this->assertFalse($actual);
    }

    function testSendTransactionalEmailFailsIfEmailSendFails()
    {
        $ctx = $this->contextForSendTransactionalEmail(emailSendSucceeds: false);
        $actual = ah::CallMethod($ctx->sut, 'sendTransactionalEmail', [
            $ctx->emailAddress,
            $ctx->displayName,
            $ctx->actionUrl,
            $ctx->substitutions
        ]);
        $this->assertFalse($actual);
    }

    #[TestWith([true ])]
    #[TestWith([false])]
    function testSendTransactionalEmailSucceeds(bool $hasMembers)
    {
        $ctx = $this->contextForSendTransactionalEmail(hasMembers: $hasMembers);
        $actual = ah::CallMethod($ctx->sut, 'sendTransactionalEmail', [
            $ctx->emailAddress,
            $ctx->displayName,
            $ctx->actionUrl,
            $ctx->substitutions
        ]);
        $this->assertTrue($actual);
    }

    #endregion sendTransactionalEmail

    #region readTransactionalEmailTemplate -------------------------------------

    private function contextForReadTransactionalEmailTemplate(
        bool $hasMembers = true,
        bool $openSucceeds = true,
        bool $readSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest($hasMembers, 'openFile');
        $ctx->template = 'TEMPLATE';
        $resource = Resource::Instance();
        $filePath = $this->createStub(CPath::class);
        $file = $this->createMock(CFile::class);

        $resource->expects($ctx->chain())
            ->method('TemplateFilePath')
            ->with('transactional-email')
            ->willReturn($filePath);
        $ctx->sut->expects($ctx->chain())
            ->method('openFile')
            ->with($filePath)
            ->willReturn($openSucceeds ? $file : null);
        $file->expects($ctx->chainIf($openSucceeds))
            ->method('Read')
            ->willReturn($readSucceeds ? $ctx->template : null);
        $file->expects($ctx->chain())
            ->method('Close');

        return $ctx;
    }

    function testReadTransactionalEmailTemplateFailsIfOpenFails()
    {
        $ctx = $this->contextForReadTransactionalEmailTemplate(openSucceeds: false);
        $actual = ah::CallMethod($ctx->sut, 'readTransactionalEmailTemplate');
        $this->assertNull($actual);
    }

    function testReadTransactionalEmailTemplateFailsIfReadFails()
    {
        $ctx = $this->contextForReadTransactionalEmailTemplate(readSucceeds: false);
        $actual = ah::CallMethod($ctx->sut, 'readTransactionalEmailTemplate');
        $this->assertNull($actual);
    }

    #[TestWith([true ])]
    #[TestWith([false])]
    function testReadTransactionalEmailTemplateSucceeds(bool $hasMembers)
    {
        $ctx = $this->contextForReadTransactionalEmailTemplate(hasMembers: $hasMembers);
        $actual = ah::CallMethod($ctx->sut, 'readTransactionalEmailTemplate');
        $this->assertSame($ctx->template, $actual);
    }

    #endregion readTransactionalEmailTemplate

    #region composeTransactionalEmail ------------------------------------------

    #[TestWith([true ])]
    #[TestWith([false])]
    function testComposeTransactionalEmail(bool $hasMembers)
    {
        $sut = $this->systemUnderTest($hasMembers);
        $template = '{{AppName}}|{{Language}}|{{Title}}|{{HeroText}}|'
                  . '{{UserName}}|{{IntroText}}|{{ActionUrl}}|{{ButtonText}}|'
                  . '{{DisclaimerText}}|{{SupportEmail}}|{{CurrentYear}}';
        $displayName = 'John';
        $actionUrl = new CUrl('https://example.com/activate');
        $substitutions = [
            'heroText'       => 'HERO_TEXT',
            'introText'      => 'INTRO_TEXT',
            'buttonText'     => 'BUTTON_TEXT',
            'disclaimerText' => 'DISCLAIMER_TEXT'
        ];
        $config = Config::Instance();
        $currentYear = \date('Y');
        $expected = 'APP|en|HERO_TEXT|HERO_TEXT|'
                  . 'John|INTRO_TEXT|https://example.com/activate|BUTTON_TEXT|'
                  . "DISCLAIMER_TEXT|support@example.com|{$currentYear}";

        $config->expects($this->exactly(3))
            ->method('Option')
            ->willReturnMap([
                ['AppName',      'APP'],
                ['Language',     'en'],
                ['SupportEmail', 'support@example.com']
            ]);

        $actual = ah::CallMethod($sut, 'composeTransactionalEmail', [
            $template,
            $displayName,
            $actionUrl,
            $substitutions
        ]);

        $this->assertSame($expected, $actual);
    }

    #endregion composeTransactionalEmail

    #region makeMailer ---------------------------------------------------------

    function testMakeMailer()
    {
        $sut = $this->systemUnderTest();
        $config = Config::Instance();

        $config->method('Option')
            ->with('IsDebug')
            ->willReturn(true);

        $actual = ah::CallMethod($sut, 'makeMailer');

        $this->assertInstanceOf(Mailer::class, $actual);
    }

    #endregion makeMailer
}
