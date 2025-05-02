<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\MailerSystem\FakeMailerImpl;

#[CoversClass(FakeMailerImpl::class)]
class FakeMailerImplTest extends TestCase
{
    function testSendAlwaysReturnsTrue()
    {
        $sut = new FakeMailerImpl();
        $this->assertTrue($sut
            ->SetAddress('someone@example.com')
            ->SetSubject('Hello')
            ->SetBody('World')
            ->Send()
        );
    }
}
