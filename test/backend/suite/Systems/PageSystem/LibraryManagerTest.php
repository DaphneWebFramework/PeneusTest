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
            $items->Set($libraryName, $this->createLibraryItem($libraryName));
            if ($isDefault) {
                $defaults->PushBack($libraryName);
            }
        }
        $manifest->method('Items')->willReturn($items);
        $manifest->method('Defaults')->willReturn($defaults);
        return $manifest;
    }

    private function createLibraryItem(string $name): LibraryItem
    {
        $item = $this->createMock(LibraryItem::class);
        $item->method('Name')->willReturn($name);
        return $item;
    }

    #region __construct --------------------------------------------------------

    function testConstructIncludesAllDefaultLibraries()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true,
            'bootstrap' => true
        ]);

        $included = $sut->Included();
        $this->assertCount(2, $included);
        $this->assertSame('jquery', $included[0]->Name());
        $this->assertSame('bootstrap', $included[1]->Name());
    }

    #endregion __construct

    #region Add ---------------------------------------------------------------

    function testAddFailsForUnknownLibrary()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown library: typeahead");
        $sut->Add('typeahead');
    }

    function testAddIgnoresAlreadyIncludedDefaultLibrary()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true
        ]);

        $sut->Add('jquery');

        $included = $sut->Included();
        $this->assertCount(1, $included);
        $this->assertSame('jquery', $included[0]->Name());
    }

    function testAddIncludesLibraryNotMarkedAsDefault()
    {
        $sut = $this->systemUnderTest([
            'jquery' => true,
            'bootstrap' => false
        ]);

        $sut->Add('bootstrap');

        $included = $sut->Included();
        $this->assertCount(2, $included);
        $this->assertSame('jquery', $included[0]->Name());
        $this->assertSame('bootstrap', $included[1]->Name());
    }

    #endregion Add

    #region Remove ------------------------------------------------------------

    function testRemoveIgnoresLibraryNotPresent()
    {
        $sut = $this->systemUnderTest(['jquery' => true]);

        $sut->Remove('typeahead'); // no-op
        $this->assertCount(1, $sut->Included());
    }

    function testRemoveExcludesPreviouslyAddedLibrary()
    {
        $sut = $this->systemUnderTest(['dataTables' => false]);

        $sut->Add('dataTables');
        $sut->Remove('dataTables');
        $this->assertCount(0, $sut->Included());
    }

    function testRemoveRemovesDefaultLibrary()
    {
        $sut = $this->systemUnderTest(['jquery' => true]);

        $sut->Remove('jquery');
        $this->assertCount(0, $sut->Included());
    }

    #endregion Remove

    #region Clear -------------------------------------------------------------

    function testClearRemovesAllIncludedLibraries()
    {
        $sut = $this->systemUnderTest(['jquery' => true, 'bootstrap' => true]);

        $sut->Clear();
        $this->assertCount(0, $sut->Included());
    }

    function testClearFollowedByAddIncludesLibrary()
    {
        $sut = $this->systemUnderTest(['jquery' => true, 'selectize' => false]);

        $sut->Clear();
        $sut->Add('selectize');

        $included = $sut->Included();
        $this->assertCount(1, $included);
        $this->assertSame('selectize', $included[0]->Name());
    }

    #endregion Clear

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

        $included = $sut->Included();
        $this->assertCount(2, $included);
        $this->assertSame('b', $included[0]->Name());
        $this->assertSame('d', $included[1]->Name());
    }

    #endregion Included
}
