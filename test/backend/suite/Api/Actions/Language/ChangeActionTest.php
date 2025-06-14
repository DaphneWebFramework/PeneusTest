<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Language\ChangeAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Peneus\Services\LanguageService;
use \TestToolkit\AccessHelper;

#[CoversClass(ChangeAction::class)]
class ChangeActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?LanguageService $originalLanguageService = null;
    private ?Config $originalConfig = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalLanguageService =
            LanguageService::ReplaceInstance($this->createMock(LanguageService::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->config());
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        LanguageService::ReplaceInstance($this->originalLanguageService);
        Config::ReplaceInstance($this->originalConfig);
    }

    private function config(): Config
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(): ChangeAction
    {
        return new ChangeAction();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfLanguageCodeIsMissing()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Required field 'languageCode' is missing.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfLanguageCodeIsUnsupported()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $languageService = LanguageService::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'languageCode' => 'xx'
            ]);
        $languageService->expects($this->once())
            ->method('IsSupported')
            ->with('xx')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'languageCode' failed custom validation.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $languageService = LanguageService::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'languageCode' => 'fr'
            ]);
        $languageService->expects($this->once())
            ->method('IsSupported')
            ->with('fr')
            ->willReturn(true);
        $languageService->expects($this->once())
            ->method('WriteToCookie')
            ->with('fr', false);
        $languageService->expects($this->once())
            ->method('DeleteCsrfCookie');

        $this->assertNull(AccessHelper::CallMethod($sut, 'onExecute'));
    }

    #endregion onExecute
}
