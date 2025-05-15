<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProviderExternal;

use \Peneus\Api\Actions\RegisterAccountAction;

use \Harmonia\Config;
use \Harmonia\Core\CArray;
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Core\CUrl;
use \Harmonia\Database\Database;
use \Harmonia\Database\Queries\InsertQuery;
use \Harmonia\Database\Queries\SelectQuery;
use \Harmonia\Database\ResultSet;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Logger;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Peneus\Resource;
use \Peneus\Systems\MailerSystem\Mailer;
use \Peneus\Translation;
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
    private ?Logger $originalLogger = null;
    private ?Translation $originalTranslation = null;

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
        $this->originalLogger =
            Logger::ReplaceInstance($this->createMock(Logger::class));
        $this->originalTranslation =
            Translation::ReplaceInstance($this->createMock(Translation::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
        Config::ReplaceInstance($this->originalConfig);
        Resource::ReplaceInstance($this->originalResource);
        Logger::ReplaceInstance($this->originalLogger);
        Translation::ReplaceInstance($this->originalTranslation);
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
            "Field 'displayName' must match the required pattern:"
          . " /^[\p{L}\p{N}][\p{L}\p{N} .\-']{1,49}$/u");
        AccessHelper::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfEmailAlreadyRegistered()
    {
        $sut = $this->systemUnderTest('isEmailAlreadyRegistered');
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $translation = Translation::Instance();

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
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_email_already_registered')
            ->willReturn('This email address is already registered.');

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
        $translation = Translation::Instance();

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
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_email_already_pending')
            ->willReturn('This email address is already awaiting activation.');

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
        $translation = Translation::Instance();

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
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_register_account_failed')
            ->willReturn('Account registration failed.');

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
        $translation = Translation::Instance();

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
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_register_account_failed')
            ->willReturn('Account registration failed.');

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
        $translation = Translation::Instance();

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
        $translation->expects($this->once())
            ->method('Get')
            ->with('error_register_account_failed')
            ->willReturn('Account registration failed.');

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
        $translation = Translation::Instance();

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
        $translation->expects($this->once())
            ->method('Get')
            ->with('success_account_activation_link_sent')
            ->willReturn('An account activation link has been sent to your email address.');

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

    function testSendActivationEmailReturnsFalseIfFileOpenFails()
    {
        $sut = $this->systemUnderTest('openFile');
        $resource = Resource::Instance();
        $logger = Logger::Instance();
        $path = new CPath('path/to/email.html');

        $resource->expects($this->once())
            ->method('TemplateFilePath')
            ->with('transactional-email')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn(null);
        $logger->expects($this->once())
            ->method('Error')
            ->with('Email template not found.');

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'sendActivationEmail',
            ['john@example.com', 'John Doe', 'activation-code-123']
        ));
    }

    function testSendActivationEmailReturnsFalseIfTemplateReadFails()
    {
        $sut = $this->systemUnderTest('openFile');
        $resource = Resource::Instance();
        $logger = Logger::Instance();
        $path = new CPath('path/to/email.html');
        $file = $this->createMock(CFile::class);

        $resource->expects($this->once())
            ->method('TemplateFilePath')
            ->with('transactional-email')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(null);
        $file->expects($this->once())
            ->method('Close');
        $logger->expects($this->once())
            ->method('Error')
            ->with('Email template could not be read.');

        $this->assertFalse(AccessHelper::CallMethod(
            $sut,
            'sendActivationEmail',
            ['john@example.com', 'John Doe', 'activation-code-123']
        ));
    }

    #[DataProviderExternal(DataHelper::class, 'BooleanProvider')]
    function testSendActivationEmailReturns($returnValue)
    {
        $sut = $this->systemUnderTest('openFile', 'newMailer');
        $resource = Resource::Instance();
        $logger = Logger::Instance();
        $path = new CPath('path/to/email.html');
        $file = $this->createMock(CFile::class);
        $config = Config::Instance();
        $translation = Translation::Instance();
        $mailer = $this->createMock(Mailer::class);

        $resource->expects($this->once())
            ->method('TemplateFilePath')
            ->with('transactional-email')
            ->willReturn($path);
        $sut->expects($this->once())
            ->method('openFile')
            ->with($path)
            ->willReturn($file);
        $file->expects($this->once())
            ->method('Read')
            ->willReturn(<<<HTML
                <!DOCTYPE html>
                <html lang="{{Language}}">
                <head><title>{{Title}}</title></head>
                <h1>{{MastheadText}}</h1>
                <h2>{{GreetingText}}</h2>
                <p>{{IntroText}}</p>
                <a href="{{ActionUrl}}">{{ButtonText}}</a>
                <p>{{SecurityNoticeText}}</p>
                <footer>
                <p>{{ContactUsText}} <a href="mailto:{{SupportEmail}}">{{SupportEmail}}</a></p>
                <p>{{CopyrightText}}</p>
                </footer>
                </body>
                </html>
            HTML);
        $file->expects($this->once())
            ->method('Close');
        $config->expects($this->any())
            ->method('OptionOrDefault')
            ->willReturnMap([
                ['Language'     , 'en' , 'tr'],
                ['AppName'      , ''   , 'Çiçek Sepeti'],
                ['SupportEmail' , ''   , 'destek@ciceksepeti.com']
            ]);
            $translation->method('Get')
                ->willReturnCallback(function ($key, ...$args) {
                    return match ($key) {
                        'email_activate_account_masthead'
                            => 'Hoş geldiniz!',
                        'email_common_greeting'
                            => sprintf('Merhaba %s,', $args[0]),
                        'email_activate_account_intro'
                            => 'Hesabınızı etkinleştirmek için aşağıdaki butona tıklayın.',
                        'email_activate_account_button_text'
                            => 'Hesabımı Etkinleştir',
                        'email_activate_account_security_notice'
                            => sprintf('E-posta adresiniz %s üzerinde bir kayıt işleminde kullanıldı.', $args[0]),
                        'email_common_contact_us'
                            => 'Yardım gerekirse bize ulaşın:',
                        'email_common_copyright'
                            => sprintf('© %d %s. Tüm hakları saklıdır.', '2025'/*$args[0]*/, $args[1]),
                        default => '',
                    };
                });
        $resource->expects($this->once())
            ->method('PageUrl')
            ->with('activate-account')
            ->willReturn(new CUrl('url/to/activate-account/'));
        $sut->expects($this->once())
            ->method('newMailer')
            ->willReturn($mailer);
        $mailer->expects($this->once())
            ->method('SetAddress')
            ->with('john@example.com')
            ->willReturnSelf();
        $mailer->expects($this->once())
            ->method('SetSubject')
            ->with('Hoş geldiniz!')
            ->willReturnSelf();
        $mailer->expects($this->once())
            ->method('SetBody')
            ->with(<<<HTML
                <!DOCTYPE html>
                <html lang="tr">
                <head><title>Hoş geldiniz!</title></head>
                <h1>Hoş geldiniz!</h1>
                <h2>Merhaba John Doe,</h2>
                <p>Hesabınızı etkinleştirmek için aşağıdaki butona tıklayın.</p>
                <a href="url/to/activate-account/activation-code-123">Hesabımı Etkinleştir</a>
                <p>E-posta adresiniz Çiçek Sepeti üzerinde bir kayıt işleminde kullanıldı.</p>
                <footer>
                <p>Yardım gerekirse bize ulaşın: <a href="mailto:destek@ciceksepeti.com">destek@ciceksepeti.com</a></p>
                <p>© 2025 Çiçek Sepeti. Tüm hakları saklıdır.</p>
                </footer>
                </body>
                </html>
            HTML)
            ->willReturnSelf();
        $mailer->expects($this->once())
            ->method('Send')
            ->willReturn($returnValue);

        $this->assertSame($returnValue, AccessHelper::CallMethod(
            $sut,
            'sendActivationEmail',
            ['john@example.com', 'John Doe', 'activation-code-123']
        ));
    }

    #endregion sendActivationEmail
}
