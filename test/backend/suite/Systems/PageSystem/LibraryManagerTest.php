<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\PageSystem\LibraryManager;

use \Harmonia\Core\CArray;
use \Harmonia\Core\CSequentialArray;
use \Peneus\Systems\PageSystem\LibraryItem;
use \Peneus\Systems\PageSystem\LibraryManifest;

#[CoversClass(LibraryManager::class)]
class LibraryManagerTest extends TestCase
{
    private function systemUnderTest(array $data): LibraryManager
    {
        return new LibraryManager($this->createManifest($data));
    }

    private function createManifest(array $data): LibraryManifest
    {
        $manifest = $this->createMock(LibraryManifest::class);
        $items = new CArray();
        $defaults = new CSequentialArray();
        foreach ($data as $libraryName => $isDefault) {
            $items->Set($libraryName, $this->createStub(LibraryItem::class));
            if ($isDefault) {
                $defaults->PushBack($libraryName);
            }
        }
        $manifest->method('Items')->willReturn($items);
        $manifest->method('Defaults')->willReturn($defaults);
        return $manifest;
    }

    #region __construct --------------------------------------------------------

    function testConstructIncludesAllDefaultLibraries()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true,
            'bootstrap' => true
        ]);

        $this->assertCount(2, $sut->Included());
    }

    #endregion __construct

    #region Add ---------------------------------------------------------------

    function testAddFailsForUnknownLibrary()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown library: typeahead');
        $sut->Add('typeahead');
    }

    function testAddIgnoresAlreadyIncludedDefaultLibrary()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true
        ]);

        $sut->Add('jquery');

        $this->assertCount(1, $sut->Included());
    }

    function testAddIncludesLibraryNotMarkedAsDefault()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true,
            'bootstrap' => false
        ]);

        $sut->Add('bootstrap');

        $this->assertCount(2, $sut->Included());
    }

    #endregion Add

    #region Remove ------------------------------------------------------------

    function testRemoveIgnoresLibraryNotPresent()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true
        ]);

        $sut->Remove('typeahead'); // no-op

        $this->assertCount(1, $sut->Included());
    }

    function testRemoveExcludesPreviouslyAddedLibrary()
    {
        $sut = $this->systemUnderTest([
            'dataTables' => false
        ]);

        $sut->Add('dataTables');
        $sut->Remove('dataTables');

        $this->assertCount(0, $sut->Included());
    }

    function testRemoveRemovesDefaultLibrary()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true
        ]);

        $sut->Remove('jquery');

        $this->assertCount(0, $sut->Included());
    }

    #endregion Remove

    #region RemoveAll ----------------------------------------------------------

    function testRemoveAllIncludedLibraries()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true,
            'bootstrap' => true
        ]);

        $sut->RemoveAll();

        $this->assertCount(0, $sut->Included());
    }

    function testRemoveAllFollowedByAddIncludesLibrary()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true,
            'selectize' => false
        ]);

        $sut->RemoveAll();
        $sut->Add('selectize');

        $this->assertCount(1, $sut->Included());
    }

    #endregion RemoveAll

    #region Included ----------------------------------------------------------

    function testIncludedReturnsLibrariesInManifestOrder()
    {
        $sut = $this->systemUnderTest([
            'a' => false,
            'b' => false,
            'c' => false,
            'd' => false
        ]);

        $sut->Add('d');
        $sut->Add('b');

        $this->assertCount(2, $sut->Included());
    }

    #endregion Included
}
