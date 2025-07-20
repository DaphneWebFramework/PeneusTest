<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\Account\RegisterAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Resource;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(RegisterAction::class)]
class RegisterActionTest extends TestCase
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
            Config::ReplaceInstance($this->config());
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

    private function config()
    {
        $mock = $this->createMock(Config::class);
        $mock->method('Option')->with('Language')->willReturn('en');
        return $mock;
    }

    private function systemUnderTest(string ...$mockedMethods): RegisterAction
    {
        return $this->getMockBuilder(RegisterAction::class)
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

    function testOnExecuteThrowsIfEmailAlreadyRegistered()
    {
        $sut = $this->systemUnderTest('isEmailAlreadyRegistered');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'displayName' => 'John Doe'
            ]);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'This email address is already registered.');
        $this->expectExceptionCode(StatusCode::Conflict->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEmailAlreadyPending()
    {
        $sut = $this->systemUnderTest(
            'isEmailAlreadyRegistered',
            'isEmailAlreadyPending'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'displayName' => 'John Doe'
            ]);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('isEmailAlreadyPending')
            ->with('john@example.com')
            ->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'This email address is already awaiting activation.');
        $this->expectExceptionCode(StatusCode::Conflict->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfCreatePendingAccountFails()
    {
        $sut = $this->systemUnderTest(
            'isEmailAlreadyRegistered',
            'isEmailAlreadyPending',
            'createPendingAccount',
            'sendActivationEmail'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'displayName' => 'John Doe'
            ]);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('isEmailAlreadyPending')
            ->with('john@example.com')
            ->willReturn(false);
        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('createPendingAccount')
            ->with('john@example.com', 'pass1234', 'John Doe', 'code1234')
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('sendActivationEmail');
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame('Failed to create pending account.', $e->getMessage());
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Account registration failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfSendActivationEmailFails()
    {
        $sut = $this->systemUnderTest(
            'isEmailAlreadyRegistered',
            'isEmailAlreadyPending',
            'createPendingAccount',
            'sendActivationEmail'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'displayName' => 'John Doe'
            ]);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('isEmailAlreadyPending')
            ->with('john@example.com')
            ->willReturn(false);
        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('createPendingAccount')
            ->with('john@example.com', 'pass1234', 'John Doe', 'code1234')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendActivationEmail')
            ->with('john@example.com', 'John Doe', 'code1234')
            ->willReturn(false);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                try {
                    return $callback();
                } catch (\Throwable $e) {
                    $this->assertSame('Failed to send activation email.', $e->getMessage());
                    return false;
                }
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Account registration failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDeleteCsrfCookieFails()
    {
        $sut = $this->systemUnderTest(
            'isEmailAlreadyRegistered',
            'isEmailAlreadyPending',
            'createPendingAccount',
            'sendActivationEmail'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();
        $cookieService = CookieService::Instance();
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'displayName' => 'John Doe'
            ]);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('isEmailAlreadyPending')
            ->with('john@example.com')
            ->willReturn(false);
        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('createPendingAccount')
            ->with('john@example.com', 'pass1234', 'John Doe', 'code1234')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendActivationEmail')
            ->with('john@example.com', 'John Doe', 'code1234')
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
        $this->expectExceptionMessage('Account registration failed.');
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'isEmailAlreadyRegistered',
            'isEmailAlreadyPending',
            'createPendingAccount',
            'sendActivationEmail'
        );
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $securityService = SecurityService::Instance();
        $cookieService = CookieService::Instance();
        $database = Database::Instance();

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn([
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'displayName' => 'John Doe'
            ]);
        $sut->expects($this->once())
            ->method('isEmailAlreadyRegistered')
            ->with('john@example.com')
            ->willReturn(false);
        $sut->expects($this->once())
            ->method('isEmailAlreadyPending')
            ->with('john@example.com')
            ->willReturn(false);
        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('createPendingAccount')
            ->with('john@example.com', 'pass1234', 'John Doe', 'code1234')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendActivationEmail')
            ->with('john@example.com', 'John Doe', 'code1234')
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
            'An account activation link has been sent to your email address.',
            $result['message']
        );
    }

    #endregion onExecute

    #region isEmailAlreadyRegistered -------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testIsEmailAlreadyRegistered($returnValue)
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM account WHERE email = :email',
            bindings: ['email' => 'test@example.com'],
            result: [[$returnValue ? 1 : 0]],
            times: 1
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'isEmailAlreadyRegistered',
            ['test@example.com']
        ));
    }

    #endregion isEmailAlreadyRegistered

    #region isEmailAlreadyPending ----------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testIsEmailAlreadyPending($returnValue)
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM pendingaccount WHERE email = :email',
            bindings: ['email' => 'test@example.com'],
            result: [[$returnValue ? 1 : 0]],
            times: 1
        );
        Database::ReplaceInstance($fakeDatabase);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'isEmailAlreadyPending',
            ['test@example.com']
        ));
    }

    #endregion isEmailAlreadyPending

    #region createPendingAccount -----------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testCreatePendingAccount($returnValue)
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $now = new \DateTime();
        $fakeDatabase = new FakeDatabase();
        $fakeDatabase->Expect(
            sql: 'INSERT INTO pendingaccount'
               . ' (email, passwordHash, displayName, activationCode, timeRegistered)'
               . ' VALUES'
               . ' (:email, :passwordHash, :displayName, :activationCode, :timeRegistered)',
            bindings: [
                'email' => 'john@example.com',
                'passwordHash' => 'hash1234',
                'displayName' => 'John Doe',
                'activationCode' => 'code1234',
                'timeRegistered' => $now->format('Y-m-d H:i:s')
            ],
            result: $returnValue ? [] : null,
            lastInsertId: $returnValue ? 23 : 0,
            times: 1
        );
        Database::ReplaceInstance($fakeDatabase);

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('pass1234')
            ->willReturn('hash1234');

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'createPendingAccount',
            ['john@example.com', 'pass1234', 'John Doe', 'code1234', $now]
        ));
    }

    #endregion createPendingAccount

    #region sendActivationEmail ------------------------------------------------

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testSendActivationEmailDelegatesToTrait($returnValue)
    {
        $sut = $this->systemUnderTest('sendTransactionalEmail');
        $resource = Resource::Instance();

        $resource->expects($this->once())
            ->method('PageUrl')
            ->with('activate-account')
            ->willReturn(new CUrl('url/to/page/'));
        $sut->expects($this->once())
            ->method('sendTransactionalEmail')
            ->with(
                'john@example.com',
                'John Doe',
                'url/to/page/code1234',
                [
                    'masthead' => 'email_activate_account_masthead',
                    'intro' => 'email_activate_account_intro',
                    'buttonText' => 'email_activate_account_button_text',
                    'securityNotice' => 'email_activate_account_security_notice'
                ]
            )
            ->willReturn($returnValue);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'sendActivationEmail',
            ['john@example.com', 'John Doe', 'code1234']
        ));
    }

    #endregion sendActivationEmail

    #region Data Providers -----------------------------------------------------

    static function invalidModelDataProvider()
    {
        return [
            'email missing' => [
                'data' => [],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'email invalid' => [
                'data' => [
                    'email' => 'invalid-email'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'password missing' => [
                'data' => [
                    'email' => 'john@example.com'
                ],
                'exceptionMessage' => "Required field 'password' is missing."
            ],
            'password too short' => [
                'data' => [
                    'email' => 'john@example.com',
                    'password' => '1234567'
                ],
                'exceptionMessage' => "Field 'password' must have a minimum length of 8 characters."
            ],
            'password too long' => [
                'data' => [
                    'email' => 'john@example.com',
                    'password' => str_repeat('a', 73)
                ],
                'exceptionMessage' => "Field 'password' must have a maximum length of 72 characters."
            ],
            'displayName missing' => [
                'data' => [
                    'email' => 'john@example.com',
                    'password' => 'pass1234'
                ],
                'exceptionMessage' => "Required field 'displayName' is missing."
            ],
            'displayName invalid' => [
                'data' => [
                    'email' => 'john@example.com',
                    'password' => 'pass1234',
                    'displayName' => '<invalid-display-name>'
                ],
                'exceptionMessage' => 'Display name is invalid. It must start '
                    . 'with a letter or number and may only contain letters, '
                    . 'numbers, spaces, dots, hyphens, and apostrophes.'
            ],
        ];
    }

    #endregion Data Providers
}
