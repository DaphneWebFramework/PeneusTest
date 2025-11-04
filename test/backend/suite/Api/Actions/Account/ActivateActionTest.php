<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\ActivateAction;

use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(ActivateAction::class)]
class ActivateActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Resource $originalResource = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        Resource::ReplaceInstance($this->originalResource);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): ActivateAction
    {
        return $this->getMockBuilder(ActivateAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfRequestValidationFails()
    {
        $sut = $this->systemUnderTest(
            'validateRequest'
        );

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPendingAccountNotFound()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findPendingAccount'
        );

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'activationCode' => 'code1234'
            ]);
        $sut->expects($this->once())
            ->method('findPendingAccount')
            ->with('code1234')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEmailAlreadyRegistered()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findPendingAccount',
            'ensureNotRegistered'
        );
        $pa = $this->createStub(PendingAccount::class);
        $pa->email = 'john@example.com';

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'activationCode' => 'code1234'
            ]);
        $sut->expects($this->once())
            ->method('findPendingAccount')
            ->with('code1234')
            ->willReturn($pa);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoActivateFails()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findPendingAccount',
            'ensureNotRegistered',
            'doActivate'
        );
        $pa = $this->createStub(PendingAccount::class);
        $pa->email = 'john@example.com';
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'activationCode' => 'code1234'
            ]);
        $sut->expects($this->once())
            ->method('findPendingAccount')
            ->with('code1234')
            ->willReturn($pa);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('doActivate')
            ->with($pa)
            ->willThrowException(new \RuntimeException());
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Account activation failed.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findPendingAccount',
            'ensureNotRegistered',
            'doActivate'
        );
        $pa = $this->createStub(PendingAccount::class);
        $pa->email = 'john@example.com';
        $database = Database::Instance();
        $cookieService = CookieService::Instance();
        $redirectUrl = new CUrl('/url/to/login');
        $resource = Resource::Instance();

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'activationCode' => 'code1234'
            ]);
        $sut->expects($this->once())
            ->method('findPendingAccount')
            ->with('code1234')
            ->willReturn($pa);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('doActivate')
            ->with($pa);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');
        $resource->expects($this->once())
            ->method('LoginPageUrl')
            ->with('home')
            ->willReturn($redirectUrl);

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('redirectUrl', $result);
        $this->assertEquals($redirectUrl, $result['redirectUrl']);
    }

    #endregion onExecute

    #region validateRequest ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
    function testValidateRequestThrows(
        array $data,
        string $exceptionMessage
    ) {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($data);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        ah::CallMethod($sut, 'validateRequest');
    }

    function testValidateRequestSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $data = [
            'activationCode' => \str_repeat('0123456789AbCdEf', 4)
        ];
        $expected = (object)$data;

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($data);

        $actual = ah::CallMethod($sut, 'validateRequest');
        $this->assertEquals($expected, $actual);
    }

    #endregion validateRequest

    #region findPendingAccount -------------------------------------------------

    function testFindPendingAccountThrowsIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `pendingaccount`'
               . ' WHERE activationCode = :activationCode LIMIT 1',
            bindings: ['activationCode' => 'code1234'],
            result: null,
            times: 1
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'No account is awaiting activation for the given code.');
        $this->expectExceptionCode(StatusCode::NotFound->value);
        ah::CallMethod($sut, 'findPendingAccount', ['code1234']);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindPendingAccountReturnsEntityIfRecordFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `pendingaccount`'
               . ' WHERE activationCode = :activationCode LIMIT 1',
            bindings: ['activationCode' => 'code1234'],
            result: [[
                'id' => 42,
                'email' => 'john@example.com',
                'passwordHash' => 'hash1234',
                'displayName' => 'John',
                'activationCode' => 'code1234',
                'timeRegistered' => '2025-01-01 10:00:00'
            ]],
            times: 1
        );

        $pa = ah::CallMethod($sut, 'findPendingAccount', ['code1234']);
        $this->assertInstanceOf(PendingAccount::class, $pa);
        $this->assertSame(42, $pa->id);
        $this->assertSame('john@example.com', $pa->email);
        $this->assertSame('hash1234', $pa->passwordHash);
        $this->assertSame('John', $pa->displayName);
        $this->assertSame('code1234', $pa->activationCode);
        $this->assertSame('2025-01-01 10:00:00',
            $pa->timeRegistered->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findPendingAccount

    #region ensureNotRegistered ------------------------------------------------

    function testEnsureNotRegisteredThrowsIfCountIsNotZero()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `account` WHERE email = :email',
            bindings: ['email' => 'john@example.com'],
            result: [[1]],
            times: 1
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("This account is already registered.");
        $this->expectExceptionCode(StatusCode::Conflict->value);
        ah::CallMethod($sut, 'ensureNotRegistered', ['john@example.com']);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testEnsureNotRegisteredSucceedsIfCountIsZero()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `account` WHERE email = :email',
            bindings: ['email' => 'john@example.com'],
            result: [[0]],
            times: 1
        );

        ah::CallMethod($sut, 'ensureNotRegistered', ['john@example.com']);
        $this->expectNotToPerformAssertions();
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion ensureNotRegistered

    #region doActivate ---------------------------------------------------------

    function testDoActivateThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest('constructAccount');
        $pa = $this->createStub(PendingAccount::class);
        $account = $this->createMock(Account::class);

        $sut->expects($this->once())
            ->method('constructAccount')
            ->with($pa)
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        ah::CallMethod($sut, 'doActivate', [$pa]);
    }

    function testDoActivateThrowsIfPendingAccountDeleteFails()
    {
        $sut = $this->systemUnderTest('constructAccount');
        $pa = $this->createMock(PendingAccount::class);
        $account = $this->createMock(Account::class);

        $sut->expects($this->once())
            ->method('constructAccount')
            ->with($pa)
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $pa->expects($this->once())
            ->method('Delete')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete pending account.");
        ah::CallMethod($sut, 'doActivate', [$pa]);
    }

    function testDoActivateSucceeds()
    {
        $sut = $this->systemUnderTest('constructAccount');
        $pa = $this->createMock(PendingAccount::class);
        $account = $this->createMock(Account::class);

        $sut->expects($this->once())
            ->method('constructAccount')
            ->with($pa)
            ->willReturn($account);
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $pa->expects($this->once())
            ->method('Delete')
            ->willReturn(true);

        ah::CallMethod($sut, 'doActivate', [$pa]);
    }

    #endregion doActivate

    #region constructAccount ---------------------------------------------------

    function testConstructAccount()
    {
        $sut = $this->systemUnderTest();
        $pa = $this->createStub(PendingAccount::class);
        $pa->email = 'john@example.com';
        $pa->passwordHash = 'hash1234';
        $pa->displayName = 'John';
        $pa->activationCode = 'code1234';
        $pa->timeRegistered = new \DateTime();

        $account = ah::CallMethod($sut, 'constructAccount', [$pa]);
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('hash1234', $account->passwordHash);
        $this->assertSame('John', $account->displayName);
        $this->assertEqualsWithDelta(\time(), $account->timeActivated->getTimestamp(), 1);
        $this->assertNull($account->timeLastLogin);
    }

    #endregion constructAccount

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'activationCode missing' => [
                'data' => [],
                'exceptionMessage' => "Activation code is required."
            ],
            'activationCode invalid' => [
                'data' => [
                    'activationCode' => 'invalid-code'
                ],
                'exceptionMessage' => "Activation code format is invalid."
            ],
        ];
    }

    #endregion Data Providers
}
