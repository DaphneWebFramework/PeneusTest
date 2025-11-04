<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\TestWith;

use \Peneus\Services\PersistentLoginManager;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Server;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\PersistentLogin;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(PersistentLoginManager::class)]
class PersistentLoginManagerTest extends TestCase
{
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;
    private ?Request $originalRequest = null;
    private ?Server $originalServer = null;
    private ?Database $originalDatabase = null;

    protected function setUp(): void
    {
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalServer =
            Server::ReplaceInstance($this->createMock(Server::class));
        $this->originalDatabase =
            Database::ReplaceInstance(new FakeDatabase());
    }

    protected function tearDown(): void
    {
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
        Request::ReplaceInstance($this->originalRequest);
        Server::ReplaceInstance($this->originalServer);
        Database::ReplaceInstance($this->originalDatabase);
    }

    private function systemUnderTest(string ...$mockedMethods): PersistentLoginManager
    {
        return $this->getMockBuilder(PersistentLoginManager::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region Create -------------------------------------------------------------

    function testCreateWhenRecordNotFound()
    {
        $sut = $this->systemUnderTest(
            'clientSignature',
            'findByAccountAndSignature',
            'constructEntity',
            'issue'
        );
        $pl = $this->createStub(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $sut->expects($this->once())
            ->method('findByAccountAndSignature')
            ->with(42, 'client-signature')
            ->willReturn(null);
        $sut->expects($this->once())
            ->method('constructEntity')
            ->with(42, 'client-signature')
            ->willReturn($pl);
        $sut->expects($this->once())
            ->method('issue')
            ->with($pl);

        ah::CallMethod($sut, 'Create', [42]);
    }

    function testCreateWhenRecordFound()
    {
        $sut = $this->systemUnderTest(
            'clientSignature',
            'findByAccountAndSignature',
            'constructEntity',
            'issue'
        );
        $pl = $this->createStub(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $sut->expects($this->once())
            ->method('findByAccountAndSignature')
            ->with(42, 'client-signature')
            ->willReturn($pl);
        $sut->expects($this->never())
            ->method('constructEntity');
        $sut->expects($this->once())
            ->method('issue')
            ->with($pl);

        ah::CallMethod($sut, 'Create', [42]);
    }

    function testCreateThrowsIfIssuingFails()
    {
        $sut = $this->systemUnderTest(
            'clientSignature',
            'findByAccountAndSignature',
            'constructEntity',
            'issue'
        );
        $pl = $this->createStub(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $sut->expects($this->once())
            ->method('findByAccountAndSignature')
            ->with(42, 'client-signature')
            ->willReturn($pl);
        $sut->expects($this->never())
            ->method('constructEntity');
        $sut->expects($this->once())
            ->method('issue')
            ->with($pl)
            ->willThrowException(new \RuntimeException());

        $this->expectException(\RuntimeException::class);
        ah::CallMethod($sut, 'Create', [42]);
    }

    #endregion Create

    #region Delete -------------------------------------------------------------

    function testDeleteWhenCookieNotFound()
    {
        $sut = $this->systemUnderTest(
            'cookieName'
        );
        $cookieService = CookieService::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(false);

        ah::CallMethod($sut, 'Delete');
    }

    function testDeleteWhenCookieValueIsInvalid()
    {
        $sut = $this->systemUnderTest(
            'cookieName',
            'parseCookieValue',
            'findByLookupKey'
        );
        $cookieService = CookieService::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $sut->expects($this->once())
            ->method('parseCookieValue')
            ->with('cookie-value')
            ->willReturn([null, null]);
        $sut->expects($this->never())
            ->method('findByLookupKey');

        ah::CallMethod($sut, 'Delete');
    }

    function testDeleteWhenRecordNotFound()
    {
        $sut = $this->systemUnderTest(
            'cookieName',
            'parseCookieValue',
            'findByLookupKey'
        );
        $cookieService = CookieService::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $sut->expects($this->once())
            ->method('parseCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token']);
        $sut->expects($this->once())
            ->method('findByLookupKey')
            ->with('lookup-key')
            ->willReturn(null);

        ah::CallMethod($sut, 'Delete');
    }

    function testDeleteWhenRecordDeleteFails()
    {
        $sut = $this->systemUnderTest(
            'cookieName',
            'parseCookieValue',
            'findByLookupKey'
        );
        $cookieService = CookieService::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $pl = $this->createMock(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $sut->expects($this->once())
            ->method('parseCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token']);
        $sut->expects($this->once())
            ->method('findByLookupKey')
            ->with('lookup-key')
            ->willReturn($pl);
        $pl->expects($this->once())
            ->method('Delete')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete persistent login.");
        ah::CallMethod($sut, 'Delete');
    }

    function testDeleteWhenRecordDeleteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'cookieName',
            'parseCookieValue',
            'findByLookupKey'
        );
        $cookieService = CookieService::Instance();
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $pl = $this->createMock(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $cookieService->expects($this->once())
            ->method('DeleteCookie')
            ->with('cookie-name');
        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $sut->expects($this->once())
            ->method('parseCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token']);
        $sut->expects($this->once())
            ->method('findByLookupKey')
            ->with('lookup-key')
            ->willReturn($pl);
        $pl->expects($this->once())
            ->method('Delete')
            ->willReturn(true);

        ah::CallMethod($sut, 'Delete');
    }

    #endregion Delete

    #region Resolve ------------------------------------------------------------

    function testResolveReturnsNullIfCookieNotFound()
    {
        $sut = $this->systemUnderTest(
            'cookieName'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $request->expects($this->once())
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(false);

        $this->assertNull(ah::CallMethod($sut, 'Resolve'));
    }

    #[TestWith([null, null])]
    #[TestWith(['lookup-key', null])]
    #[TestWith([null, 'token-value'])]
    function testResolveReturnsNullIfCookieValueIsInvalid(
        ?string $lookupKey,
        ?string $token
    ) {
        $sut = $this->systemUnderTest(
            'cookieName',
            'parseCookieValue'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $sut->expects($this->once())
            ->method('parseCookieValue')
            ->with('cookie-value')
            ->willReturn([$lookupKey, $token]);

        $this->assertNull(ah::CallMethod($sut, 'Resolve'));
    }

    function testResolveReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest(
            'cookieName',
            'parseCookieValue',
            'findByLookupKey'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $sut->expects($this->once())
            ->method('parseCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findByLookupKey')
            ->with('lookup-key')
            ->willReturn(null);

        $this->assertNull(ah::CallMethod($sut, 'Resolve'));
    }

    function testResolveReturnsNullIfClientSignatureDoesNotMatch()
    {
        $sut = $this->systemUnderTest(
            'cookieName',
            'parseCookieValue',
            'findByLookupKey',
            'clientSignature'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $pl = $this->createStub(PersistentLogin::class);
        $pl->clientSignature = 'different-client-signature';

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $sut->expects($this->once())
            ->method('parseCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findByLookupKey')
            ->with('lookup-key')
            ->willReturn($pl);
        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');

        $this->assertNull(ah::CallMethod($sut, 'Resolve'));
    }

    function testResolveReturnsNullIfTokenDoesNotMatch()
    {
        $sut = $this->systemUnderTest(
            'cookieName',
            'parseCookieValue',
            'findByLookupKey',
            'clientSignature'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $pl = $this->createStub(PersistentLogin::class);
        $pl->clientSignature = 'client-signature';
        $pl->tokenHash = 'different-token-hash';
        $securityService = SecurityService::Instance();

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $sut->expects($this->once())
            ->method('parseCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findByLookupKey')
            ->with('lookup-key')
            ->willReturn($pl);
        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('token-value', 'different-token-hash')
            ->willReturn(false);

        $this->assertNull(ah::CallMethod($sut, 'Resolve'));
    }

    function testResolveReturnsNullIfRecordIsExpired()
    {
        $sut = $this->systemUnderTest(
            'cookieName',
            'parseCookieValue',
            'findByLookupKey',
            'clientSignature',
            'currentTime'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $pl = $this->createStub(PersistentLogin::class);
        $pl->clientSignature = 'client-signature';
        $pl->tokenHash = 'token-hash';
        $pl->timeExpires = new \DateTime('2024-12-31 23:59:59');
        $securityService = SecurityService::Instance();

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $sut->expects($this->once())
            ->method('parseCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findByLookupKey')
            ->with('lookup-key')
            ->willReturn($pl);
        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('token-value', 'token-hash')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('currentTime')
            ->willReturn(new \DateTime('2025-01-01 00:00:00'));

        $this->assertNull(ah::CallMethod($sut, 'Resolve'));
    }

    function testResolveReturnsAccountIdOnSuccess()
    {
        $sut = $this->systemUnderTest(
            'cookieName',
            'parseCookieValue',
            'findByLookupKey',
            'clientSignature',
            'currentTime'
        );
        $request = Request::Instance();
        $cookies = $this->createMock(CArray::class);
        $pl = $this->createStub(PersistentLogin::class);
        $pl->accountId = 42;
        $pl->clientSignature = 'client-signature';
        $pl->tokenHash = 'token-hash';
        $pl->timeExpires = new \DateTime('2025-01-01 00:00:00');
        $securityService = SecurityService::Instance();

        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $request->expects($this->exactly(2))
            ->method('Cookies')
            ->willReturn($cookies);
        $cookies->expects($this->once())
            ->method('Has')
            ->with('cookie-name')
            ->willReturn(true);
        $cookies->expects($this->once())
            ->method('Get')
            ->with('cookie-name')
            ->willReturn('cookie-value');
        $sut->expects($this->once())
            ->method('parseCookieValue')
            ->with('cookie-value')
            ->willReturn(['lookup-key', 'token-value']);
        $sut->expects($this->once())
            ->method('findByLookupKey')
            ->with('lookup-key')
            ->willReturn($pl);
        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('token-value', 'token-hash')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('currentTime')
            ->willReturn(new \DateTime('2025-01-01 00:00:00'));

        $this->assertSame(42, ah::CallMethod($sut, 'Resolve'));
    }

    #endregion Resolve

    #region Rotate -------------------------------------------------------------

    function testRotateWhenRecordNotFound()
    {
        $sut = $this->systemUnderTest(
            'findByAccountAndSignature',
            'clientSignature',
            'issue'
        );

        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $sut->expects($this->once())
            ->method('findByAccountAndSignature')
            ->with(42, 'client-signature')
            ->willReturn(null);
        $sut->expects($this->never())
            ->method('issue');

        ah::CallMethod($sut, 'Rotate', [42]);
    }

    function testRotateWhenRecordFound()
    {
        $sut = $this->systemUnderTest(
            'findByAccountAndSignature',
            'clientSignature',
            'issue'
        );
        $pl = $this->createStub(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $sut->expects($this->once())
            ->method('findByAccountAndSignature')
            ->with(42, 'client-signature')
            ->willReturn($pl);
        $sut->expects($this->once())
            ->method('issue')
            ->with($pl);

        ah::CallMethod($sut, 'Rotate', [42]);
    }

    function testRotateThrowsIfIssuingFails()
    {
        $sut = $this->systemUnderTest(
            'findByAccountAndSignature',
            'clientSignature',
            'issue'
        );
        $pl = $this->createStub(PersistentLogin::class);

        $sut->expects($this->once())
            ->method('clientSignature')
            ->willReturn('client-signature');
        $sut->expects($this->once())
            ->method('findByAccountAndSignature')
            ->with(42, 'client-signature')
            ->willReturn($pl);
        $sut->expects($this->once())
            ->method('issue')
            ->with($pl)
            ->willThrowException(new \RuntimeException());

        $this->expectException(\RuntimeException::class);
        ah::CallMethod($sut, 'Rotate', [42]);
    }

    #endregion Rotate

    #region currentTime --------------------------------------------------------

    function testCurrentTime()
    {
        $sut = $this->systemUnderTest();
        $expected = new \DateTime();

        $actual = ah::CallMethod($sut, 'currentTime');
        $this->assertInstanceOf(\DateTime::class, $actual);
        $this->assertEqualsWithDelta(
            $expected->getTimestamp(),
            $actual->getTimestamp(),
            1
        );
    }

    #endregion currentTime

    #region expiryTime ---------------------------------------------------------

    function testExpiryTime()
    {
        $sut = $this->systemUnderTest();
        $expected = new \DateTime('+1 month');

        $actual = ah::CallMethod($sut, 'expiryTime');
        $this->assertInstanceOf(\DateTime::class, $actual);
        $this->assertEqualsWithDelta(
            $expected->getTimestamp(),
            $actual->getTimestamp(),
            1
        );
    }

    #endregion expiryTime

    #region cookieName ---------------------------------------------------------

    function testCookieName()
    {
        $sut = $this->systemUnderTest();
        $cookieService = CookieService::Instance();
        $expected = 'APP_PL';

        $cookieService->expects($this->once())
            ->method('AppSpecificCookieName')
            ->with('PL')
            ->willReturn($expected);

        $actual = ah::CallMethod($sut, 'cookieName');
        $this->assertSame($expected, $actual);
    }

    #endregion cookieName

    #region cookieValue --------------------------------------------------------

    function testCookieValue()
    {
        $sut = $this->systemUnderTest();
        $expected = 'lookup-key.token-value';

        $actual = Ah::CallMethod(
            $sut,
            'cookieValue',
            ['lookup-key', 'token-value']
        );
        $this->assertSame($expected, $actual);
    }

    #endregion cookieValue

    #region parseCookieValue ---------------------------------------------------

    #[TestWith([[null , null     ], ''           ])]
    #[TestWith([[null , null     ], '.'          ])]
    #[TestWith([[null , 'bar'    ], '.bar'       ])]
    #[TestWith([[null , 'bar.baz'], '.bar.baz'   ])]
    #[TestWith([['foo', null     ], 'foo'        ])]
    #[TestWith([['foo', null     ], 'foo.'       ])]
    #[TestWith([['foo', 'bar'    ], 'foo.bar'    ])]
    #[TestWith([['foo', 'bar.'   ], 'foo.bar.'   ])]
    #[TestWith([['foo', 'bar.baz'], 'foo.bar.baz'])]
    function testParseCookieValue(
        array $expected,
        string $cookieValue
    ) {
        $sut = $this->systemUnderTest();
        $actual = ah::CallMethod(
            $sut,
            'parseCookieValue',
            [$cookieValue]
        );
        $this->assertSame($expected, $actual);
    }

    #endregion parseCookieValue

    #region clientSignature ----------------------------------------------------

    function testClientSignature()
    {
        $sut = $this->systemUnderTest();
        $server = Server::Instance();
        $request = Request::Instance();
        $headers = $this->createMock(CArray::class);
        $clientAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $expected = \rtrim(
            \base64_encode(
                \hash('md5', "{$clientAddress}\0{$userAgent}", true)
            ),
            '='
        );

        $server->expects($this->once())
            ->method('ClientAddress')
            ->willReturn($clientAddress);
        $request->expects($this->once())
            ->method('Headers')
            ->willReturn($headers);
        $headers->expects($this->once())
            ->method('GetOrDefault')
            ->with('user-agent', '')
            ->willReturn($userAgent);

        $actual = ah::CallMethod($sut, 'clientSignature');
        $this->assertSame($expected, $actual);
    }

    #endregion clientSignature

    #region findByLookupKey ----------------------------------------------------

    function testFindByLookupKeyReturnsNullIfNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `persistentlogin` WHERE'
               . ' lookupKey = :lookupKey LIMIT 1',
            bindings: ['lookupKey' => 'lookup-key'],
            result: null,
            times: 1
        );

        $pl = ah::CallMethod(
            $sut,
            'findByLookupKey',
            ['lookup-key']
        );
        $this->assertNull($pl);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindByLookupKeyReturnsEntityIfFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `persistentlogin` WHERE'
               . ' lookupKey = :lookupKey LIMIT 1',
            bindings: ['lookupKey' => 'lookup-key'],
            result: [[
                'id' => 17,
                'accountId' => 42,
                'clientSignature' => 'client-signature',
                'lookupKey' => 'lookup-key',
                'tokenHash' => 'token-hash',
                'timeExpires' => '2024-01-01 00:00:00'
            ]],
            times: 1
        );

        $pl = ah::CallMethod(
            $sut,
            'findByLookupKey',
            ['lookup-key']
        );
        $this->assertInstanceOf(PersistentLogin::class, $pl);
        $this->assertSame(17, $pl->id);
        $this->assertSame(42, $pl->accountId);
        $this->assertSame('client-signature', $pl->clientSignature);
        $this->assertSame('lookup-key', $pl->lookupKey);
        $this->assertSame('token-hash', $pl->tokenHash);
        $this->assertSame('2024-01-01 00:00:00',
                          $pl->timeExpires->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findByLookupKey

    #region findByAccountAndSignature ------------------------------------------

    function testFindByAccountAndSignatureReturnsNullIfNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `persistentlogin` WHERE'
               . ' accountId = :accountId AND'
               . ' clientSignature = :clientSignature LIMIT 1',
            bindings: [
                'accountId' => 42,
                'clientSignature' => 'client-signature'
            ],
            result: null,
            times: 1
        );

        $pl = ah::CallMethod(
            $sut,
            'findByAccountAndSignature',
            [42, 'client-signature']
        );
        $this->assertNull($pl);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindByAccountAndSignatureReturnsEntityIfFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `persistentlogin` WHERE'
               . ' accountId = :accountId AND'
               . ' clientSignature = :clientSignature LIMIT 1',
            bindings: [
                'accountId' => 42,
                'clientSignature' => 'client-signature'
            ],
            result: [[
                'id' => 17,
                'accountId' => 42,
                'clientSignature' => 'client-signature',
                'lookupKey' => 'lookup-key',
                'tokenHash' => 'token-hash',
                'timeExpires' => '2024-01-01 00:00:00'
            ]],
            times: 1
        );

        $pl = ah::CallMethod(
            $sut,
            'findByAccountAndSignature',
            [42, 'client-signature']
        );
        $this->assertInstanceOf(PersistentLogin::class, $pl);
        $this->assertSame(17, $pl->id);
        $this->assertSame(42, $pl->accountId);
        $this->assertSame('client-signature', $pl->clientSignature);
        $this->assertSame('lookup-key', $pl->lookupKey);
        $this->assertSame('token-hash', $pl->tokenHash);
        $this->assertSame('2024-01-01 00:00:00',
                          $pl->timeExpires->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findByAccountAndSignature

    #region constructEntity ----------------------------------------------------

    function testConstructEntity()
    {
        $sut = $this->systemUnderTest();

        $pl = ah::CallMethod(
            $sut,
            'constructEntity',
            [42, 'client-signature']
        );
        $this->assertInstanceOf(PersistentLogin::class, $pl);
        $this->assertSame(42, $pl->accountId);
        $this->assertSame('client-signature', $pl->clientSignature);
    }

    #endregion constructEntity

    #region issue --------------------------------------------------------------

    function testIssueThrowsIfRecordCannotBeSaved()
    {
        $sut = $this->systemUnderTest();
        $pl = $this->createMock(PersistentLogin::class);
        $securityService = SecurityService::Instance();

        $securityService->expects($this->exactly(2))
            ->method('GenerateToken')
            ->willReturnMap([
                [32, 'token-value'],
                [8, 'lookup-key']
            ]);
        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('token-value')
            ->willReturn('token-hash');
        $pl->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save persistent login.");
        ah::CallMethod($sut, 'issue', [$pl]);
    }

    function testIssueSucceeds()
    {
        $sut = $this->systemUnderTest(
            'expiryTime',
            'cookieName',
            'cookieValue'
        );
        $pl = $this->createMock(PersistentLogin::class);
        $securityService = SecurityService::Instance();
        $cookieService = CookieService::Instance();
        $expiryTime = new \DateTime('2026-01-01 00:00:00');

        $securityService->expects($this->exactly(2))
            ->method('GenerateToken')
            ->willReturnMap([
                [32, 'token-value'],
                [8, 'lookup-key']
            ]);
        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('token-value')
            ->willReturn('token-hash');
        $sut->expects($this->once())
            ->method('expiryTime')
            ->willReturn($expiryTime);
        $pl->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('cookieName')
            ->willReturn('cookie-name');
        $sut->expects($this->once())
            ->method('cookieValue')
            ->with('lookup-key', 'token-value')
            ->willReturn('cookie-value');
        $cookieService->expects($this->once())
            ->method('SetCookie')
            ->with('cookie-name', 'cookie-value', $expiryTime->getTimestamp());

        ah::CallMethod($sut, 'issue', [$pl]);
    }

    #endregion issue
}
