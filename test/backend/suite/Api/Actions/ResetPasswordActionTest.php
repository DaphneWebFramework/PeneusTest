<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\ResetPasswordAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Logger;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\PasswordReset;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(ResetPasswordAction::class)]
class ResetPasswordActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;
    private ?Config $originalConfig = null;
    private ?Logger $originalLogger = null;

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
            Config::ReplaceInstance($this->config());
        $this->originalLogger =
            Logger::ReplaceInstance($this->createStub(Logger::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
        Config::ReplaceInstance($this->originalConfig);
        Logger::ReplaceInstance($this->originalLogger);
    }

    private function config()
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(string ...$mockedMethods): ResetPasswordAction
    {
        return $this->getMockBuilder(ResetPasswordAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfResetCodeMissing()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn(['newPassword' => 'pass1234']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Reset code is required.');

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfResetCodeInvalid()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'resetCode' => 'not-a-valid-code',
                'newPassword' => 'pass1234'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Reset code format is invalid.');

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfNewPasswordIsMissing()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'resetCode' => str_repeat('a', 64),
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required field 'newPassword' is missing.");

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfNewPasswordTooShort()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'resetCode' => str_repeat('a', 64),
                'newPassword' => '1234567'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'newPassword' must have a minimum length of 8 characters.");

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfNewPasswordTooLong()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'resetCode' => str_repeat('a', 64),
                'newPassword' => str_repeat('a', 73)
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'newPassword' must have a maximum length of 72 characters.");

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordResetNotFound()
    {
        $sut = $this->systemUnderTest('findPasswordReset');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $resetCode = str_repeat('a', 64); // valid format

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'resetCode' => $resetCode,
                'newPassword' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with($resetCode)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No password reset record found for the given code.');
        $this->expectExceptionCode(StatusCode::NotFound->value);

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('findPasswordReset', 'findAccount');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $resetCode = str_repeat('a', 64);
        $passwordReset = $this->createStub(PasswordReset::class);
        $passwordReset->accountId = 42;

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'resetCode' => $resetCode,
                'newPassword' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with($resetCode)
            ->willReturn($passwordReset);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($passwordReset->accountId)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No account is associated with the password reset record.');
        $this->expectExceptionCode(StatusCode::NotFound->value);

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfUpdatePasswordFails()
    {
        $sut = $this->systemUnderTest(
            'findPasswordReset',
            'findAccount',
            'updatePassword'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $resetCode = str_repeat('a', 64);
        $passwordReset = $this->createMock(PasswordReset::class);
        $passwordReset->accountId = 42;
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'resetCode' => $resetCode,
                'newPassword' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with($resetCode)
            ->willReturn($passwordReset);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($passwordReset->accountId)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('updatePassword')
            ->with($account, 'pass1234')
            ->willReturn(false);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    $callback();
                } catch (\Throwable $e) {
                    $this->assertSame(
                        'Failed to update account password.',
                        $e->getMessage()
                    );
                    return false;
                }
                $this->fail('Expected exception not thrown.');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Password reset failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordResetDeleteFails()
    {
        $sut = $this->systemUnderTest(
            'findPasswordReset',
            'findAccount',
            'updatePassword'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $resetCode = str_repeat('a', 64);
        $passwordReset = $this->createMock(PasswordReset::class);
        $passwordReset->accountId = 42;
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'resetCode' => $resetCode,
                'newPassword' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with($resetCode)
            ->willReturn($passwordReset);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($passwordReset->accountId)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('updatePassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $passwordReset->expects($this->once())
            ->method('Delete')
            ->willReturn(false);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    $callback();
                } catch (\Throwable $e) {
                    $this->assertSame(
                        'Failed to delete password reset record.',
                        $e->getMessage()
                    );
                    return false;
                }
                $this->fail('Expected exception not thrown.');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Password reset failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDeleteCsrfCookieFails()
    {
        $sut = $this->systemUnderTest(
            'findPasswordReset',
            'findAccount',
            'updatePassword'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $resetCode = str_repeat('a', 64);
        $passwordReset = $this->createMock(PasswordReset::class);
        $passwordReset->accountId = 42;
        $account = $this->createStub(Account::class);
        $cookieService = CookieService::Instance();
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'resetCode' => $resetCode,
                'newPassword' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with($resetCode)
            ->willReturn($passwordReset);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($passwordReset->accountId)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('updatePassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $passwordReset->expects($this->once())
            ->method('Delete')
            ->willReturn(true);
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie')
            ->willThrowException(new \RuntimeException);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    $callback();
                } catch (\Throwable $e) {
                    return false;
                }
                $this->fail('Expected exception not thrown.');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Password reset failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceedsIfDatabaseTransactionSucceeds()
    {
        $sut = $this->systemUnderTest(
            'findPasswordReset',
            'findAccount',
            'updatePassword',
            'buildLoginUrl'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $resetCode = str_repeat('a', 64);
        $passwordReset = $this->createMock(PasswordReset::class);
        $passwordReset->accountId = 42;
        $account = $this->createStub(Account::class);
        $cookieService = CookieService::Instance();
        $database = Database::Instance();
        $redirectUrl = '/redirect/to/login';

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'resetCode' => $resetCode,
                'newPassword' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with($resetCode)
            ->willReturn($passwordReset);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($passwordReset->accountId)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('updatePassword')
            ->with($account, 'pass1234')
            ->willReturn(true);
        $passwordReset->expects($this->once())
            ->method('Delete')
            ->willReturn(true);
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });
        $sut->expects($this->once())
            ->method('buildLoginUrl')
            ->willReturn($redirectUrl);

        $result = AccessHelper::CallMethod($sut, 'onExecute');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('redirectUrl', $result);
        $this->assertSame($redirectUrl, $result['redirectUrl']);
    }

    #endregion onExecute

    #region findPasswordReset --------------------------------------------------

    function testFindPasswordResetReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM passwordreset WHERE resetCode = :resetCode LIMIT 1',
            bindings: ['resetCode' => 'code1234'],
            result: null
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertNull(AccessHelper::CallMethod(
            $sut,
            'findPasswordReset',
            ['code1234']
        ));
    }

    function testFindPasswordResetReturnsEntityWhenFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM passwordreset WHERE resetCode = :resetCode LIMIT 1',
            bindings: ['resetCode' => 'code1234'],
            result: [[
                'id' => 1,
                'accountId' => 42,
                'resetCode' => 'code1234',
                'timeRequested' => '2024-12-31 00:00:00'
            ]]
        );
        Database::ReplaceInstance($fakeDatabase);

        $passwordReset = AccessHelper::CallMethod(
            $sut,
            'findPasswordReset',
            ['code1234']
        );
        $this->assertInstanceOf(PasswordReset::class, $passwordReset);
        $this->assertSame(1, $passwordReset->id);
        $this->assertSame(42, $passwordReset->accountId);
        $this->assertSame('code1234', $passwordReset->resetCode);
    }

    #endregion findPasswordReset

    #region findAccount --------------------------------------------------------

    function testFindAccountReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM account WHERE id = :id LIMIT 1',
            bindings: ['id' => 42],
            result: null
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertNull(AccessHelper::CallMethod($sut, 'findAccount', [42]));
    }

    function testFindAccountReturnsEntityWhenFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT * FROM account WHERE id = :id LIMIT 1',
            bindings: ['id' => 42],
            result: [[
                'id' => 42,
                'email' => 'john@example.com',
                'passwordHash' => 'hash1234',
                'displayName' => 'John',
                'timeActivated' => '2024-01-01 00:00:00',
                'timeLastLogin' => '2024-01-02 00:00:00'
            ]]
        );
        Database::ReplaceInstance($fakeDatabase);

        $account = AccessHelper::CallMethod($sut, 'findAccount', [42]);
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(42, $account->id);
        $this->assertSame('john@example.com', $account->email);
    }

    #endregion findAccount

    #region updatePassword -----------------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testUpdatePassword($returnValue)
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $security = SecurityService::Instance();

        $security->expects($this->once())
            ->method('HashPassword')
            ->with('pass5678')
            ->willReturn('hashed');
        $account->expects($this->once())
            ->method('Save')
            ->willReturn($returnValue);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'updatePassword',
            [$account, 'pass5678']
        ));
    }

    #endregion updatePassword
}
