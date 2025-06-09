<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Translation;

use \Harmonia\Core\CPath;
use \TestToolkit\AccessHelper;

#[CoversClass(Translation::class)]
class TranslationTest extends TestCase
{
    private function systemUnderTest(string ...$mockedMethods): Translation
    {
        return $this->getMockBuilder(Translation::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region filePaths ----------------------------------------------------------

    function testFilePaths()
    {
        $sut = $this->systemUnderTest();
        $reflectionClass = new \ReflectionClass(Translation::class);
        $directory = \dirname($reflectionClass->getFileName());
        $path = CPath::Join($directory, 'translations.json');

        $paths = AccessHelper::CallMethod($sut, 'filePaths');
        $this->assertEquals([$path], $paths);
    }

    #endregion filePaths
}
