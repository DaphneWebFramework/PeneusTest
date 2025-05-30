<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\RegisterAccountAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Queries\InsertQuery;
use \Harmonia\Systems\DatabaseSystem\Queries\SelectQuery;
use \Harmonia\Systems\DatabaseSystem\ResultSet;
use \Peneus\Resource;
use \TestToolkit\AccessHelper;
use \TestToolkit\DataHelper;

#[CoversClass(RegisterAccountAction::class)]
class RegisterAccountActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): RegisterAccountAction
    {
        return $this->getMockBuilder(RegisterAccountAction::class)
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
            ->willReturn([
                'password' => 'pass1234',
                'displayName' => 'John Doe'
            ]);

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
                'email' => 'not-an-email',
                'password' => 'pass1234',
                'displayName' => 'John Doe'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Field 'email' must be a valid email address.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordIsMissing()
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
                'email' => 'john@example.com',
                'displayName' => 'John Doe'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required field 'password' is missing.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordTooShort()
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
                'email' => 'john@example.com',
                'password' => '1234567',
                'displayName' => 'John Doe'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'password' must have a minimum length of 8 characters.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPasswordTooLong()
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
                'email' => 'john@example.com',
                'password' => str_repeat('a', 73),
                'displayName' => 'John Doe'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Field 'password' must have a maximum length of 72 characters.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDisplayNameIsMissing()
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
                'email' => 'john@example.com',
                'password' => 'pass1234'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required field 'displayName' is missing.");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDisplayNameIsInvalid()
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
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'displayName' => '<script>'
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Display name is invalid. It must start with a letter or number'
          . ' and may only contain letters, numbers, spaces, dots, hyphens,'
          . ' and apostrophes.');
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
        $this->expectExceptionMessage('This email address is already registered.');
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
        $this->expectExceptionMessage('This email address is already awaiting activation.');
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
            ->willReturn('activation-code-123');
        $sut->expects($this->once())
            ->method('createPendingAccount')
            ->with('john@example.com', 'pass1234', 'John Doe', 'activation-code-123')
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
            ->willReturn('activation-code-123');
        $sut->expects($this->once())
            ->method('createPendingAccount')
            ->with('john@example.com', 'pass1234', 'John Doe', 'activation-code-123')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendActivationEmail')
            ->with('john@example.com', 'John Doe', 'activation-code-123')
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
            ->willReturn('activation-code-123');
        $sut->expects($this->once())
            ->method('createPendingAccount')
            ->with('john@example.com', 'pass1234', 'John Doe', 'activation-code-123')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendActivationEmail')
            ->with('john@example.com', 'John Doe', 'activation-code-123')
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

    function testOnExecuteSucceedsIfDatabaseTransactionSucceeds()
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
            ->willReturn('activation-code-123');
        $sut->expects($this->once())
            ->method('createPendingAccount')
            ->with('john@example.com', 'pass1234', 'John Doe', 'activation-code-123')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendActivationEmail')
            ->with('john@example.com', 'John Doe', 'activation-code-123')
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

    function testIsEmailAlreadyRegisteredReturnsTrue()
    {
        $sut = $this->systemUnderTest();
        $database = Database::Instance();
        $resultSet = $this->createMock(ResultSet::class);

        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame(
                    'account',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    'COUNT(*)',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    'email = :email',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    ['email' => 'test@example.com'],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $resultSet->expects($this->once())
            ->method('Row')
            ->with(ResultSet::ROW_MODE_NUMERIC)
            ->willReturn([1]);

        $this->assertTrue(AccessHelper::CallMethod(
            $sut,
            'isEmailAlreadyRegistered',
            ['test@example.com']
        ));
    }

    function testIsEmailAlreadyRegisteredReturnsFalse()
    {
        $sut = $this->systemUnderTest();
        $database = Database::Instance();
        $resultSet = $this->createMock(ResultSet::class);

        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame(
                    'account',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    'COUNT(*)',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    'email = :email',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    ['email' => 'test@example.com'],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $resultSet->expects($this->once())
            ->method('Row')
            ->with(ResultSet::ROW_MODE_NUMERIC)
            ->willReturn([0]);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'isEmailAlreadyRegistered',
            ['test@example.com']
        ));
    }

    #endregion isEmailAlreadyRegistered

    #region isEmailAlreadyPending ----------------------------------------------

    function testIsEmailAlreadyPendingReturnsTrue()
    {
        $sut = $this->systemUnderTest();
        $database = Database::Instance();
        $resultSet = $this->createMock(ResultSet::class);

        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame(
                    'pendingaccount',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    'COUNT(*)',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    'email = :email',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    ['email' => 'test@example.com'],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $resultSet->expects($this->once())
            ->method('Row')
            ->with(ResultSet::ROW_MODE_NUMERIC)
            ->willReturn([1]);

        $this->assertTrue(AccessHelper::CallMethod(
            $sut,
            'isEmailAlreadyPending',
            ['test@example.com']
        ));
    }

    function testIsEmailAlreadyPendingReturnsFalse()
    {
        $sut = $this->systemUnderTest();
        $database = Database::Instance();
        $resultSet = $this->createMock(ResultSet::class);

        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(SelectQuery::class, $query);
                $this->assertSame(
                    'pendingaccount',
                    AccessHelper::GetProperty($query, 'table')
                );
                $this->assertSame(
                    'COUNT(*)',
                    AccessHelper::GetProperty($query, 'columns')
                );
                $this->assertSame(
                    'email = :email',
                    AccessHelper::GetProperty($query, 'condition')
                );
                $this->assertSame(
                    ['email' => 'test@example.com'],
                    $query->Bindings()
                );
                return true;
            }))
            ->willReturn($resultSet);
        $resultSet->expects($this->once())
            ->method('Row')
            ->with(ResultSet::ROW_MODE_NUMERIC)
            ->willReturn([0]);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'isEmailAlreadyPending',
            ['test@example.com']
        ));
    }

    #endregion isEmailAlreadyPending

    #region createPendingAccount -----------------------------------------------

    function testCreatePendingAccountReturnsTrueWhenSaveSucceeds()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $database = Database::Instance();

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('plain-password')
            ->willReturn('hashed-password');
        $database->expects($this->once())
            ->method('Execute')
            ->with($this->callback(function($query) {
                $this->assertInstanceOf(InsertQuery::class, $query);
                $this->assertSame(
                    'pendingaccount',
                    AccessHelper::GetProperty($query, 'table')
                );
                $bindings = $query->Bindings();
                $this->assertSame(
                    'john@example.com',
                    $bindings['email']
                );
                $this->assertSame(
                    'hashed-password',
                    $bindings['passwordHash']
                );
                $this->assertSame(
                    'John Doe',
                    $bindings['displayName']
                );
                $this->assertSame(
                    'activation-code-123',
                    $bindings['activationCode']
                );
                $this->assertEqualsWithDelta(
                    time(),
                    \DateTime::createFromFormat(
                        'Y-m-d H:i:s',
                        $bindings['timeRegistered']
                    )->getTimestamp(),
                    1
                );
                return true;
            }))
            ->willReturn($this->createStub(ResultSet::class));
        $database->expects($this->once())
            ->method('LastInsertId')
            ->willReturn(23);

        $this->assertTrue(AccessHelper::CallMethod(
            $sut,
            'createPendingAccount',
            ['john@example.com', 'plain-password', 'John Doe', 'activation-code-123']
        ));
    }

    function testCreatePendingAccountReturnsFalseWhenSaveFails()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $database = Database::Instance();

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('plain-password')
            ->willReturn('hashed-password');
        $database->expects($this->once())
            ->method('Execute')
            ->willReturn(null);

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'createPendingAccount',
            ['john@example.com', 'plain-password', 'John Doe', 'activation-code-123']
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
                'url/to/page/activation-code-123',
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
            ['john@example.com', 'John Doe', 'activation-code-123']
        ));
    }

    #endregion sendActivationEmail
}
