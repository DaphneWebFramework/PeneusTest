<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Management\ListEntityMappingsAction;

use \Harmonia\Core\CFileSystem;
use \Harmonia\Core\CPath;
use \Harmonia\Resource;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Entity;
use \Peneus\Model\ViewEntity;
use \TestToolkit\AccessHelper;

#[CoversClass(ListEntityMappingsAction::class)]
class ListEntityMappingsActionTest extends TestCase
{
    private ?Resource $originalResource = null;
    private ?CFileSystem $originalFileSystem = null;
    private ?Database $originalDatabase = null;

    protected function setUp(): void
    {
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
        $this->originalFileSystem =
            CFileSystem::ReplaceInstance($this->createMock(CFileSystem::class));
        $this->originalDatabase =
            Database::ReplaceInstance(new FakeDatabase());
    }

    protected function tearDown(): void
    {
        Resource::ReplaceInstance($this->originalResource);
        CFileSystem::ReplaceInstance($this->originalFileSystem);
        Database::ReplaceInstance($this->originalDatabase);
    }

    private function systemUnderTest(string ...$mockedMethods): ListEntityMappingsAction
    {
        return $this->getMockBuilder(ListEntityMappingsAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteReturnsEmptyArrayWhenNoModules()
    {
        $sut = $this->systemUnderTest('findModules');

        $sut->expects($this->once())
            ->method('findModules')
            ->willReturn([]);

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertSame(['data' => []], $result);
    }

    function testOnExecuteReturnsEmptyArrayWhenModulesHaveNoEntities()
    {
        $sut = $this->systemUnderTest('findModules', 'findEntities');
        $modulePaths = [new CPath('Module1'), new CPath('Module2')];

        $sut->expects($this->once())
            ->method('findModules')
            ->willReturn($modulePaths);
        $sut->expects($invokedCount = $this->exactly(2))
            ->method('findEntities')
            ->willReturnCallback(function($modulePath) use($invokedCount) {
                switch ($invokedCount->numberOfInvocations()) {
                case 1:
                    $this->assertEquals('Module1', $modulePath);
                    return [];
                case 2:
                    $this->assertEquals('Module2', $modulePath);
                    return [];
                default:
                    $this->fail("Unexpected module path: $modulePath");
                }
            });

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertSame(['data' => []], $result);
    }

    function testOnExecuteSkipsInvalidEntities()
    {
        $sut = $this->systemUnderTest(
            'findModules',
            'findEntities',
            'entityClassFrom',
            'isValidEntity'
        );
        $modulePath = new CPath('Module1');
        $entityPath = new CPath('Module1/Model/Invalid.php');
        $entityClass = '\\Module1\\Model\\Invalid';

        $sut->expects($this->once())
            ->method('findModules')
            ->willReturn([$modulePath]);
        $sut->expects($this->once())
            ->method('findEntities')
            ->with($modulePath)
            ->willReturn([$entityPath]);
        $sut->expects($this->once())
            ->method('entityClassFrom')
            ->with($entityPath)
            ->willReturn($entityClass);
        $sut->expects($this->once())
            ->method('isValidEntity')
            ->with($entityClass)
            ->willReturn(false);

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertSame(['data' => []], $result);
    }

    #[DataProvider('entityMappingDataProvider')]
    function testOnExecuteReturnsEntityMapping(
        string $tableName,
        bool $tableExists,
        bool $isView,
        array $entityMetadata,
        array $tableMetadata,
        ?bool $isSync
    ) {
        $sut = $this->systemUnderTest(
            'findModules',
            'findEntities',
            'entityClassFrom',
            'isValidEntity',
            'tableMetadata'
        );
        $modulePath = new CPath('Module1');
        $entityPath = new CPath('Module1/Model/Foo.php');
        $entity = new class() extends Entity {
            public static string $tableName;
            public static bool $tableExists;
            public static bool $isView;
            public static array $entityMetadata;
            public static function TableName(): string { return self::$tableName; }
            public static function TableExists(): bool { return self::$tableExists; }
            public static function IsView(): bool { return self::$isView; }
            public static function Metadata(): array { return self::$entityMetadata; }
        };
        $entityClass = \get_class($entity);
        $entityClass::$tableName = $tableName;
        $entityClass::$tableExists = $tableExists;
        $entityClass::$isView = $isView;
        $entityClass::$entityMetadata = $entityMetadata;

        $sut->expects($this->once())
            ->method('findModules')
            ->willReturn([$modulePath]);
        $sut->expects($this->once())
            ->method('findEntities')
            ->with($modulePath)
            ->willReturn([$entityPath]);
        $sut->expects($this->once())
            ->method('entityClassFrom')
            ->with($entityPath)
            ->willReturn($entityClass);
        $sut->expects($this->once())
            ->method('isValidEntity')
            ->with($entityClass)
            ->willReturn(true);
        if ($tableExists && !$isView) {
            $sut->expects($this->once())
                ->method('tableMetadata')
                ->with($tableName)
                ->willReturn($tableMetadata);
        } else {
            $sut->expects($this->never())
                ->method('tableMetadata');
        }

        $result = AccessHelper::CallMethod($sut, 'onExecute');
        $this->assertCount(1, $result['data']);
        $this->assertSame([
            'entityClass' => $entityClass,
            'tableName' => $tableName,
            'tableType' => $isView ? 'view' : 'table',
            'tableExists' => $tableExists,
            'isSync' => $isSync
        ], $result['data'][0]);
    }

    #endregion onExecute

    #region findModules --------------------------------------------------------

    function testFindModulesReturnsEmptyArrayWhenScandirFails()
    {
        $sut = $this->systemUnderTest();
        $resource = Resource::Instance();
        $backendPath = $this->createMock(CPath::class);

        $resource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('backend')
            ->willReturn($backendPath);
        $backendPath->expects($this->once())
            ->method('Call')
            ->with('\scandir')
            ->willReturn(false);

        $sut->__construct();
        $result = AccessHelper::CallMethod($sut, 'findModules');
        $this->assertSame([], $result);
    }

    function testFindModulesSkipsDotAndDotDot()
    {
        $sut = $this->systemUnderTest();
        $resource = Resource::Instance();
        $backendPath = $this->createMock(CPath::class);

        $resource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('backend')
            ->willReturn($backendPath);
        $backendPath->expects($this->once())
            ->method('Call')
            ->with('\scandir')
            ->willReturn(['.', '..']);

        $sut->__construct();
        $result = AccessHelper::CallMethod($sut, 'findModules');
        $this->assertSame([], $result);
    }

    function testFindModulesReturnsOnlyDirectoryPaths()
    {
        $sut = $this->systemUnderTest();
        $resource = Resource::Instance();
        $backendPath = $this->createMock(CPath::class);
        $moduleNames = ['Module1', 'Module2'];
        $modulePaths = [
            $this->createMock(CPath::class),
            $this->createMock(CPath::class)
        ];

        $resource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('backend')
            ->willReturn($backendPath);
        $backendPath->expects($this->once())
            ->method('Call')
            ->with('\scandir')
            ->willReturn($moduleNames);
        $backendPath->expects($invokedCount = $this->exactly(2))
            ->method('Extend')
            ->willReturnCallback(function($moduleName)
                use($invokedCount, $moduleNames, $modulePaths)
            {
                switch ($invokedCount->numberOfInvocations()) {
                case 1:
                    $this->assertSame($moduleNames[0], $moduleName);
                    return $modulePaths[0];
                case 2:
                    $this->assertSame($moduleNames[1], $moduleName);
                    return $modulePaths[1];
                default:
                    $this->fail("Unexpected module name: $moduleName");
                }
            });
        $modulePaths[0]->expects($this->once())
            ->method('Call')
            ->with('\is_dir')
            ->willReturn(true);
        $modulePaths[1]->expects($this->once())
            ->method('Call')
            ->with('\is_dir')
            ->willReturn(false);

        $sut->__construct();
        $result = AccessHelper::CallMethod($sut, 'findModules');
        $this->assertSame([$modulePaths[0]], $result);
    }

    #endregion findModules

    #region findEntities -------------------------------------------------------

    function testFindEntitiesReturnsEmptyArrayWhenModelDirIsMissing()
    {
        $sut = $this->systemUnderTest();
        $modulePath = $this->createMock(CPath::class);
        $modelPath = $this->createMock(CPath::class);

        $modulePath->expects($this->once())
            ->method('Extend')
            ->with('Model')
            ->willReturn($modelPath);
        $modelPath->expects($this->once())
            ->method('Call')
            ->with('\is_dir')
            ->willReturn(false);

        $sut->__construct();
        $result = AccessHelper::CallMethod($sut, 'findEntities', [$modulePath]);
        $this->assertSame([], $result);
    }

    function testFindEntitiesReturnsEmptyArrayWhenNoPhpFilesFound()
    {
        $sut = $this->systemUnderTest();
        $modulePath = $this->createMock(CPath::class);
        $modelPath = $this->createMock(CPath::class);
        $fileSystem = CFileSystem::Instance();

        $modulePath->expects($this->once())
            ->method('Extend')
            ->with('Model')
            ->willReturn($modelPath);
        $modelPath->expects($this->once())
            ->method('Call')
            ->with('\is_dir')
            ->willReturn(true);
        $fileSystem->expects($this->once())
            ->method('FindFiles')
            ->with($modelPath, '*.php', true)
            ->willReturnCallback(function() {
                yield from [];
            });

        $sut->__construct();
        $result = AccessHelper::CallMethod($sut, 'findEntities', [$modulePath]);
        $this->assertSame([], $result);
    }

    function testFindEntitiesReturnsEntityPathsWhenPhpFilesAreFound()
    {
        $sut = $this->systemUnderTest();
        $modulePath = $this->createMock(CPath::class);
        $modelPath = $this->createMock(CPath::class);
        $fileSystem = CFileSystem::Instance();
        $entityPaths = ['Model/Foo.php', 'Model/Sub/Bar.php'];

        $modulePath->expects($this->once())
            ->method('Extend')
            ->with('Model')
            ->willReturn($modelPath);
        $modelPath->expects($this->once())
            ->method('Call')
            ->with('\is_dir')
            ->willReturn(true);
        $fileSystem->expects($this->once())
            ->method('FindFiles')
            ->with($modelPath, '*.php', true)
            ->willReturnCallback(function() use($entityPaths) {
                yield from $entityPaths;
            });

        $sut->__construct();
        $result = AccessHelper::CallMethod($sut, 'findEntities', [$modulePath]);
        $this->assertCount(2, $result);
        $this->assertEquals($entityPaths[0], $result[0]);
        $this->assertEquals($entityPaths[1], $result[1]);
    }

    #endregion findEntities

    #region entityClassFrom ----------------------------------------------------

    function testEntityClassFromThrowsWhenPathIsOutsideBackend()
    {
        $sut = $this->systemUnderTest();
        $resource = Resource::Instance();
        $backendPath = $this->createStub(CPath::class);
        $entityPath = $this->createMock(CPath::class);

        $resource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('backend')
            ->willReturn($backendPath);
        $entityPath->expects($this->once())
            ->method('StartsWith')
            ->with($backendPath)
            ->willReturn(false);

        $sut->__construct();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Entity path must be within the backend directory.');
        AccessHelper::CallMethod($sut, 'entityClassFrom', [$entityPath]);
    }

    function testEntityClassFromReturnsFullyQualifiedClassName()
    {
        $sut = $this->systemUnderTest();
        $resource = Resource::Instance();
        $backendPath = $this->createMock(CPath::class);
        $entityPath = $this->createMock(CPath::class);
        $backendPathLength = 7;
        $relativePath = $this->createMock(CPath::class);

        $resource->expects($this->once())
            ->method('AppSubdirectoryPath')
            ->with('backend')
            ->willReturn($backendPath);
        $entityPath->expects($this->once())
            ->method('StartsWith')
            ->with($backendPath)
            ->willReturn(true);
        $backendPath->expects($this->once())
            ->method('Length')
            ->willReturn($backendPathLength);
        $entityPath->expects($this->once())
            ->method('Middle')
            ->with($backendPathLength)
            ->willReturn($relativePath);
        $relativePath->expects($this->once())
            ->method('Call')
            ->with('\pathinfo')
            ->willReturn([
                'dirname' => '/Module/Foo',
                'filename' => 'Bar'
            ]);

        $sut->__construct();
        $result = AccessHelper::CallMethod($sut, 'entityClassFrom', [$entityPath]);
        $this->assertSame('\\Module\\Foo\\Bar', $result);
    }

    #endregion entityClassFrom

    #region isValidEntity ------------------------------------------------------

    function testIsValidEntityReturnsFalseWhenNotSubclassOfEntity()
    {
        $sut = $this->systemUnderTest();

        $result = AccessHelper::CallMethod($sut, 'isValidEntity', [\stdClass::class]);
        $this->assertFalse($result);
    }

    function testIsValidEntityReturnsFalseWhenClassIsAbstract()
    {
        $sut = $this->systemUnderTest();

        $result = AccessHelper::CallMethod($sut, 'isValidEntity', [ViewEntity::class]);
        $this->assertFalse($result);
    }

    function testIsValidEntityReturnsTrueForConcreteEntitySubclass()
    {
        $sut = $this->systemUnderTest();
        $entity = new class() extends Entity {};
        $entityClass = \get_class($entity);

        $result = AccessHelper::CallMethod($sut, 'isValidEntity', [$entityClass]);
        $this->assertTrue($result);
    }

    #endregion isValidEntity

    #region tableMetadata ------------------------------------------------------

    function testTableMetadataThrowsWhenQueryFails()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SHOW COLUMNS FROM `account`',
            result: null,
            times: 1
        );

        $sut->__construct();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to retrieve columns for: account');
        AccessHelper::CallMethod($sut, 'tableMetadata', ['account']);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testTableMetadataReturnsNormalizedColumnData()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SHOW COLUMNS FROM `account`',
            result: [
                [
                    'Field' => 'id',
                    'Type' => 'int(11)',
                    'Null' => 'NO',
                    'Key' => 'PRI',
                    'Default' => null,
                    'Extra' => 'auto_increment'
                ],
                [
                    'Field' => 'email',
                    'Type' => 'text',
                    'Null' => 'NO',
                    'Key' => '',
                    'Default' => null,
                    'Extra' => ''
                ],
                [
                    'Field' => 'timeLastLogin',
                    'Type' => 'datetime',
                    'Null' => 'YES',
                    'Key' => '',
                    'Default' => null,
                    'Extra' => ''
                ]
            ],
            times: 1
        );

        $sut->__construct();
        $result = AccessHelper::CallMethod($sut, 'tableMetadata', ['account']);
        $this->assertSame([
            ['name' => 'id', 'type' => 'INT', 'nullable' => false],
            ['name' => 'email', 'type' => 'TEXT', 'nullable' => false],
            ['name' => 'timeLastLogin', 'type' => 'DATETIME', 'nullable' => true]
        ], $result);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion tableMetadata

    #region Data Providers -----------------------------------------------------

    /**
     * @return array<string, array<mixed>>[]
     *   tableName, tableExists, isView, entityMetadata, tableMetadata, isSync
     */
    static function entityMappingDataProvider()
    {
        return [
            'missing table' => [
                'table1',
                false,
                false,
                [ ['name' => 'id', 'type' => 'INT', 'nullable' => false] ],
                [],
                null
            ],
            'view table' => [
                'view1',
                true,
                true,
                [ ['name' => 'id', 'type' => 'INT', 'nullable' => false] ],
                [ ['name' => 'id', 'type' => 'INT', 'nullable' => false] ],
                null
            ],
            'synced table' => [
                'account',
                true,
                false,
                [ ['name' => 'id', 'type' => 'INT', 'nullable' => false] ],
                [ ['name' => 'id', 'type' => 'INT', 'nullable' => false] ],
                true
            ],
            'out-of-sync table' => [
                'account',
                true,
                false,
                [ ['name' => 'id', 'type' => 'INT', 'nullable' => false] ],
                [ ['name' => 'id', 'type' => 'VARCHAR', 'nullable' => false] ],
                false
            ],
        ];
    }

    #endregion Data Providers
}
