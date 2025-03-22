<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Resource;

use \Harmonia\Core\CPath;
use \Harmonia\Core\CUrl;
use \TestToolkit\AccessHelper;

#[CoversClass(Resource::class)]
class ResourceTest extends TestCase
{
    private ?\Harmonia\Resource $originalHarmoniaResource = null;

    protected function setUp(): void
    {
        $this->originalHarmoniaResource =
            \Harmonia\Resource::ReplaceInstance($this->createMock(\Harmonia\Resource::class));
    }

    protected function tearDown(): void
    {
        \Harmonia\Resource::ReplaceInstance($this->originalHarmoniaResource);
    }

    private function systemUnderTest(string ...$mockedMethods): Resource
    {
        $sut = $this->getMockBuilder(Resource::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
        return AccessHelper::CallConstructor($sut);
    }

    #region __call -------------------------------------------------------------

    function testCallDelegatesToBaseWhenMethodExists()
    {
        $sut = $this->systemUnderTest();
        $harmoniaResource = \Harmonia\Resource::Instance();

        $harmoniaResource->expects($this->once())
            ->method('AppUrl')
            ->willReturn(new CUrl('https://example.com/app/'));

        $this->assertEquals('https://example.com/app/', $sut->AppUrl());
    }

    function testCallThrowsWhenBaseMethodDoesNotExist()
    {
        $sut = $this->systemUnderTest();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method `Nonexistent` does not exist');
        $sut->Nonexistent();
    }

    #endregion __call

    #region TemplateFilePath ---------------------------------------------------

    function testTemplateFilePath()
    {
        $sut = $this->systemUnderTest();
        $harmoniaResource = \Harmonia\Resource::Instance();

        $harmoniaResource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('templates')
            ->willReturn(new CPath('path/to/app/templates'));

        $this->assertEquals(
            'path/to/app/templates' . \DIRECTORY_SEPARATOR . 'page.html',
            $sut->TemplateFilePath('page')
        );
    }

    #endregion TemplateFilePath

    #region MasterpageFilePath -------------------------------------------------

    function testMasterpageFilePath()
    {
        $sut = $this->systemUnderTest();
        $harmoniaResource = \Harmonia\Resource::Instance();

        $harmoniaResource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('masterpages')
            ->willReturn(new CPath('path/to/app/masterpages'));

        $this->assertEquals(
            'path/to/app/masterpages' . \DIRECTORY_SEPARATOR . 'basic.php',
            $sut->MasterpageFilePath('basic')
        );
    }

    #endregion MasterpageFilePath

}
