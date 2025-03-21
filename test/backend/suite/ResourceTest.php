<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Resource;

use \Harmonia\Core\CPath;
use \TestToolkit\AccessHelper;

#[CoversClass(Resource::class)]
class ResourceTest extends TestCase
{
    private function systemUnderTest(string ...$mockedMethods): Resource
    {
        return $this->getMockBuilder(Resource::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region TemplateFilePath ---------------------------------------------------

    function testTemplateFilePath()
    {
        $sut = $this->systemUnderTest('appSubdirectoryPath');

        $sut->expects($this->once())
            ->method('appSubdirectoryPath')
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
        $sut = $this->systemUnderTest('appSubdirectoryPath');

        $sut->expects($this->once())
            ->method('appSubdirectoryPath')
            ->with('masterpages')
            ->willReturn(new CPath('path/to/app/masterpages'));

        $this->assertEquals(
            'path/to/app/masterpages' . \DIRECTORY_SEPARATOR . 'basic.php',
            $sut->MasterpageFilePath('basic')
        );
    }

    #endregion MasterpageFilePath

    #region appSubdirectoryPath ------------------------------------------------

    function testAppSubdirectoryPath()
    {
        $sut = $this->systemUnderTest('AppPath');

        $sut->expects($this->once())
            ->method('AppPath')
            ->willReturn(new CPath('path/to/app'));

        // Call the constructor to initialize cache in the parent class.
        AccessHelper::CallConstructor($sut);

        $expected = 'path/to/app' . \DIRECTORY_SEPARATOR . 'subdir';
        $this->assertEquals(
            $expected,
            AccessHelper::CallMethod($sut, 'appSubdirectoryPath', ['subdir'])
        );
        // Ensure that the method returns the cached value.
        $this->assertEquals(
            $expected,
            AccessHelper::CallMethod($sut, 'appSubdirectoryPath', ['subdir'])
        );
    }

    #endregion appSubdirectoryPath
}
