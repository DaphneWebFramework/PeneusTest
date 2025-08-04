<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\Account\SendPasswordResetAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Resource;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(SendPasswordResetAction::class)]
class SendPasswordResetActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;
    private ?Config $originalConfig = null;
    private ?Resource $originalResource = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->createConfig());
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
        Config::ReplaceInstance($this->originalConfig);
        Resource::ReplaceInstance($this->originalResource);
    }

    private function createConfig(): Config
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(string ...$mockedMethods): SendPasswordResetAction
    {
        return $this->getMockBuilder(SendPasswordResetAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    #[DataProvider('invalidModelDataProvider')]
    function testOnExecuteThrowsForInvalidModelData(
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
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteReturnsSuccessEvenIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('findAccount');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'nobody@example.com'
            ]);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('nobody@example.com')
            ->willReturn(null);

        $result = AccessHelper::CallMethod($sut, 'onExecute');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertSame(
            'A password reset link has been sent to your email address.',
            $result['message']
        );
    }

    function testOnExecuteThrowsIfCreatePasswordResetFails()
    {
        $sut = $this->systemUnderTest(
            'findAccount',
            'createPasswordReset',
            'sendPasswordResetEmail'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $account->email = 'john@example.com';
        $account->displayName = 'John';
        $securityService = SecurityService::Instance();
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['email' => 'john@example.com']);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('createPasswordReset')
            ->with(42, 'code1234')
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('sendPasswordResetEmail');
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame(
                        'Failed to save password reset record.',
                        $e->getMessage()
                    );
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sending password reset link failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfSendPasswordResetEmailFails()
    {
        $sut = $this->systemUnderTest(
            'findAccount',
            'createPasswordReset',
            'sendPasswordResetEmail'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $account->email = 'john@example.com';
        $account->displayName = 'John';
        $securityService = SecurityService::Instance();
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['email' => 'john@example.com']);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('createPasswordReset')
            ->with(42, 'code1234')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with('john@example.com', 'John', 'code1234')
            ->willReturn(false);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame(
                        'Failed to send password reset email.',
                        $e->getMessage()
                    );
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sending password reset link failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDeleteCsrfCookieFails()
    {
        $sut = $this->systemUnderTest(
            'findAccount',
            'createPasswordReset',
            'sendPasswordResetEmail'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $account->email = 'john@example.com';
        $account->displayName = 'John';
        $securityService = SecurityService::Instance();
        $cookieService = CookieService::Instance();
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['email' => 'john@example.com']);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('createPasswordReset')
            ->with(42, 'code1234')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with('john@example.com', 'John', 'code1234')
            ->willReturn(true);
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie')
            ->willThrowException(new \RuntimeException);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sending password reset link failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'findAccount',
            'createPasswordReset',
            'sendPasswordResetEmail'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $account->email = 'john@example.com';
        $account->displayName = 'John';
        $securityService = SecurityService::Instance();
        $cookieService = CookieService::Instance();
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['email' => 'john@example.com']);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('createPasswordReset')
            ->with(42, 'code1234')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with('john@example.com', 'John', 'code1234')
            ->willReturn(true);
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });

        $result = AccessHelper::CallMethod($sut, 'onExecute');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertSame(
            'A password reset link has been sent to your email address.',
            $result['message']
        );
    }

    #endregion onExecute

    #region findAccount --------------------------------------------------------

    function testFindAccountReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE email = :email LIMIT 1',
            bindings: ['email' => 'john@example.com'],
            result: null,
            times: 1
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertNull(AccessHelper::CallMethod(
            $sut,
            'findAccount',
            ['john@example.com']
        ));
    }

    function testFindAccountReturnsEntityWhenFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE email = :email LIMIT 1',
            bindings: ['email' => 'john@example.com'],
            result: [[
                'id' => 23,
                'email' => 'john@example.com',
                'passwordHash' => 'hash1234',
                'displayName' => 'John',
                'timeActivated' => '2024-01-01 00:00:00',
                'timeLastLogin' => '2025-01-01 00:00:00'
            ]],
            times: 1
        );
        Database::ReplaceInstance($fakeDatabase);

        $account = AccessHelper::CallMethod(
            $sut,
            'findAccount',
            ['john@example.com']
        );
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(23, $account->id);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('hash1234', $account->passwordHash);
        $this->assertSame('John', $account->displayName);
        $this->assertSame('2024-01-01 00:00:00',
            $account->timeActivated->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-01 00:00:00',
            $account->timeLastLogin->format('Y-m-d H:i:s'));
    }

    #endregion findAccount

    #region createPasswordReset ------------------------------------------------

    function testCreatePasswordResetUpdatesWhenRecordExists()
    {
        $sut = $this->systemUnderTest();
        $now = new \DateTime();
        $passwordResetId = 1;
        $accountId = 42;
        $resetCode = 'code1234';
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset` WHERE accountId = :accountId LIMIT 1',
            bindings: ['accountId' => $accountId],
            result: [[
                'id' => $passwordResetId,
                'accountId' => $accountId,
                'resetCode' => 'old-code',
                'timeRequested' => '2024-01-01 00:00:00'
            ]],
            times: 1
        );
        $fakeDatabase->Expect(
            sql: 'UPDATE `passwordreset` SET'
               . ' `accountId` = :accountId, `resetCode` = :resetCode,'
               . ' `timeRequested` = :timeRequested'
               . ' WHERE `id` = :id',
            bindings: [
                'id' => $passwordResetId,
                'accountId' => $accountId,
                'resetCode' => $resetCode,
                'timeRequested' => $now->format('Y-m-d H:i:s')
            ]
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertTrue(AccessHelper::CallMethod(
            $sut,
            'createPasswordReset',
            [$accountId, $resetCode, $now]
        ));
    }

    function testCreatePasswordResetInsertsWhenRecordDoesNotExist()
    {
        $sut = $this->systemUnderTest();
        $now = new \DateTime();
        $accountId = 42;
        $resetCode = 'code1234';
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset` WHERE accountId = :accountId LIMIT 1',
            bindings: ['accountId' => $accountId],
            result: null, // no existing record
            times: 1
        );
        $fakeDatabase->Expect(
            sql: 'INSERT INTO `passwordreset`'
               . ' (`accountId`, `resetCode`, `timeRequested`)'
               . ' VALUES (:accountId, :resetCode, :timeRequested)',
            bindings: [
                'accountId' => $accountId,
                'resetCode' => $resetCode,
                'timeRequested' => $now->format('Y-m-d H:i:s')
            ]
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertTrue(AccessHelper::CallMethod(
            $sut,
            'createPasswordReset',
            [$accountId, $resetCode, $now]
        ));
    }

    function testCreatePasswordResetReturnsFalseIfSaveFails()
    {
        $sut = $this->systemUnderTest();
        $now = new \DateTime();
        $accountId = 42;
        $resetCode = 'code1234';
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset` WHERE accountId = :accountId LIMIT 1',
            bindings: ['accountId' => $accountId],
            result: null, // no existing record
            times: 1
        );
        $fakeDatabase->Expect(
            sql: 'INSERT INTO `passwordreset`'
               . ' (`accountId`, `resetCode`, `timeRequested`)'
               . ' VALUES (:accountId, :resetCode, :timeRequested)',
            bindings: [
                'accountId' => $accountId,
                'resetCode' => $resetCode,
                'timeRequested' => $now->format('Y-m-d H:i:s')
            ],
            result: null // simulate failure
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'createPasswordReset',
            [$accountId, $resetCode, $now]
        ));
    }

    #endregion createPasswordReset

    #region sendPasswordResetEmail ---------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testSendActivationEmailDelegatesToTrait($returnValue)
    {
        $sut = $this->systemUnderTest('sendTransactionalEmail');
        $resource = Resource::Instance();

        $resource->expects($this->once())
            ->method('PageUrl')
            ->with('reset-password')
            ->willReturn(new CUrl('url/to/page/'));
        $sut->expects($this->once())
            ->method('sendTransactionalEmail')
            ->with(
                'john@example.com',
                'John Doe',
                'url/to/page/code1234',
                [
                    'masthead' => 'email_reset_password_masthead',
                    'intro' => 'email_reset_password_intro',
                    'buttonText' => 'email_reset_password_button_text',
                    'securityNotice' => 'email_reset_password_security_notice'
                ]
            )
            ->willReturn($returnValue);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'sendPasswordResetEmail',
            ['john@example.com', 'John Doe', 'code1234']
        ));
    }

    #endregion sendPasswordResetEmail

    #region Data Providers -----------------------------------------------------

    static function invalidModelDataProvider()
    {
        return [
            'email missing' => [
                'data' => [],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'email invalid' => [
                'data' => ['email' => 'invalid-email'],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ]
        ];
    }

    #endregion Data Providers
}
