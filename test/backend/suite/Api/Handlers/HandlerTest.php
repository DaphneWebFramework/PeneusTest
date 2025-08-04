<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Handlers\Handler;

use \Harmonia\Config;
use \Peneus\Api\Actions\Action;

#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalConfig =
            Config::ReplaceInstance($this->createConfig());
    }

    protected function tearDown(): void
    {
        Config::ReplaceInstance($this->originalConfig);
    }

    private function createConfig(): Config
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(): Handler
    {
        return $this->getMockBuilder(Handler::class)
            ->onlyMethods(['createAction'])
            ->getMock();
    }

    #region HandleAction -------------------------------------------------------

    function testHandleActionWhenActionIsUnknown()
    {
        $sut = $this->systemUnderTest();

        $sut->expects($this->once())
            ->method('createAction')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Action not found: action1');
        $this->expectExceptionCode(404);
        $sut->HandleAction('action1');
    }

    function testHandleActionWithCaseInsensitiveActionName()
    {
        $sut = $this->systemUnderTest();
        $action = $this->createMock(Action::class);

        $sut->expects($this->once())
            ->method('createAction')
            ->with('test')
            ->willReturn($action);
        $action->expects($this->once())
            ->method('Execute')
            ->willReturn(42);

        $this->assertSame(42, $sut->HandleAction('TEST'));
    }

    function testHandleActionWithActionNameWithSurroundingWhitespace()
    {
        $sut = $this->systemUnderTest();
        $action = $this->createMock(Action::class);

        $sut->expects($this->once())
            ->method('createAction')
            ->with('test')
            ->willReturn($action);
        $action->expects($this->once())
            ->method('Execute')
            ->willReturn(42);

        $this->assertSame(42, $sut->HandleAction('  test  '));
    }

    #endregion HandleAction
}
