<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Handlers\LanguageHandler;

use \Peneus\Api\Actions\Language\ChangeAction;
use \Peneus\Api\Guards\TokenGuard;
use \Peneus\Services\LanguageService;
use \TestToolkit\AccessHelper;

#[CoversClass(LanguageHandler::class)]
class LanguageHandlerTest extends TestCase
{
    private ?LanguageService $originalLanguageService = null;

    protected function setUp(): void
    {
        $this->originalLanguageService =
            LanguageService::ReplaceInstance($this->createMock(LanguageService::class));
    }

    protected function tearDown(): void
    {
        LanguageService::ReplaceInstance($this->originalLanguageService);
    }

    #region createAction -------------------------------------------------------

    function testCreateActionWithChange()
    {
        $handler = new LanguageHandler;
        $languageService = LanguageService::Instance();
        $tokenGuard = $this->createMock(TokenGuard::class);

        $languageService->expects($this->once())
            ->method('CreateTokenGuard')
            ->willReturn($tokenGuard);

        $action = AccessHelper::CallMethod($handler, 'createAction', ['change']);
        $this->assertInstanceOf(ChangeAction::class, $action);
        $guards = AccessHelper::GetProperty($action, 'guards');
        $this->assertCount(1, $guards);
        $this->assertSame($tokenGuard, $guards[0]);
    }

    function testCreateActionWithUnknownAction()
    {
        $handler = new LanguageHandler;
        $action = AccessHelper::CallMethod($handler, 'createAction', ['unknown']);
        $this->assertNull($action);
    }

    #endregion createAction
}
