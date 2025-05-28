<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\SendPasswordResetAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\Account;
use \TestToolkit\AccessHelper;

#[CoversClass(SendPasswordResetAction::class)]
class SendPasswordResetActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;
    private ?Config $originalConfig = null;

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
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
        Config::ReplaceInstance($this->originalConfig);
    }

    private function config()
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

    function testOnExecuteThrowsIfEmailIsMissing()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required field 'email' is missing.");

        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEmailIsInvalid()
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
                'email' => 'not-an-email'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'email' must be a valid email address.");

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
            ->willReturn('reset-code-abc');
        $sut->expects($this->once())
            ->method('createPasswordReset')
            ->with(42, 'reset-code-abc')
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
            ->willReturn('reset-code-abc');
        $sut->expects($this->once())
            ->method('createPasswordReset')
            ->with(42, 'reset-code-abc')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with('john@example.com', 'John', 'reset-code-abc')
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
            ->willReturn('reset-code-abc');
        $sut->expects($this->once())
            ->method('createPasswordReset')
            ->with(42, 'reset-code-abc')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with('john@example.com', 'John', 'reset-code-abc')
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

    function testOnExecuteSucceedsIfDatabaseTransactionSucceeds()
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
            ->willReturn('reset-code-abc');
        $sut->expects($this->once())
            ->method('createPasswordReset')
            ->with(42, 'reset-code-abc')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with('john@example.com', 'John', 'reset-code-abc')
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
}
