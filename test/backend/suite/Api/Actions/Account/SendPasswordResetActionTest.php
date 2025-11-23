<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\TestWith;

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
use \Peneus\Model\PasswordReset;
use \Peneus\Resource;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(SendPasswordResetAction::class)]
class SendPasswordResetActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Config $originalConfig = null;
    private ?Resource $originalResource = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
        $this->originalConfig =
            Config::ReplaceInstance($this->createMock(Config::class));
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
        $this->originalCookieService =
            CookieService::ReplaceInstance($this->createMock(CookieService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        Config::ReplaceInstance($this->originalConfig);
        Resource::ReplaceInstance($this->originalResource);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): SendPasswordResetAction
    {
        return $this->getMockBuilder(SendPasswordResetAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfPayloadValidationFails()
    {
        $sut = $this->systemUnderTest('validatePayload');

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteBypassesDatabaseTransactionIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('validatePayload', 'findAccount',
            'doSend');
        $payload = (object)[
            'email' => 'john@example.com'
        ];
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn(null);
        $database->expects($this->never())
            ->method('WithTransaction');
        $sut->expects($this->never())
            ->method('doSend');
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');

        $result = ah::CallMethod($sut, 'onExecute');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertSame(
            "A password reset link has been sent to your email address.",
            $result['message']
        );
    }

    function testOnExecuteThrowsIfDoSendFails()
    {
        $sut = $this->systemUnderTest('validatePayload', 'findAccount',
            'doSend');
        $payload = (object)[
            'email' => 'john@example.com'
        ];
        $account = $this->createStub(Account::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doSend')
            ->with($account)
            ->willThrowException(new \RuntimeException());
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "We couldn't send the email. Please try again later.");
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('validatePayload', 'findAccount',
            'doSend');
        $payload = (object)[
            'email' => 'john@example.com'
        ];
        $account = $this->createStub(Account::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with('john@example.com')
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('doSend')
            ->with($account);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');

        $result = ah::CallMethod($sut, 'onExecute');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertSame(
            "A password reset link has been sent to your email address.",
            $result['message']
        );
    }

    #endregion onExecute

    #region validatePayload ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
    function testValidatePayloadThrows(array $payload, string $exceptionMessage)
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $payload = [
            'email' => 'john@example.com'
        ];
        $expected = (object)$payload;

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $this->assertEquals($expected, ah::CallMethod($sut, 'validatePayload'));
    }

    #endregion validatePayload

    #region findAccount --------------------------------------------------------

    function testFindAccountReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE email = :email LIMIT 1',
            bindings: ['email' => 'john@example.com'],
            result: null,
            times: 1
        );

        $account = ah::CallMethod($sut, 'findAccount', ['john@example.com']);
        $this->assertNull($account);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountReturnsEntityIfRecordFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE email = :email LIMIT 1',
            bindings: ['email' => 'john@example.com'],
            result: [[
                'id' => 42,
                'email' => 'john@example.com',
                'passwordHash' => 'hash1234',
                'displayName' => 'John',
                'timeActivated' => '2024-01-01 00:00:00',
                'timeLastLogin' => '2025-01-01 00:00:00'
            ]],
            times: 1
        );

        $account = ah::CallMethod($sut, 'findAccount', ['john@example.com']);
        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame(42, $account->id);
        $this->assertSame('john@example.com', $account->email);
        $this->assertSame('hash1234', $account->passwordHash);
        $this->assertSame('John', $account->displayName);
        $this->assertSame('2024-01-01 00:00:00',
            $account->timeActivated->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-01 00:00:00',
            $account->timeLastLogin->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findAccount

    #region doSend -------------------------------------------------------------

    function testDoSendThrowsIfPasswordResetSaveFails()
    {
        $sut = $this->systemUnderTest('findOrConstructPasswordReset', 'sendEmail');
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $securityService = SecurityService::Instance();
        $pr = $this->createMock(PasswordReset::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('findOrConstructPasswordReset')
            ->with(42)
            ->willReturn($pr);
        $pr->expects($this->once())
            ->method('Save')
            ->willReturn(false);
        $sut->expects($this->never())
            ->method('sendEmail');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save password reset.");
        ah::CallMethod($sut, 'doSend', [$account]);
    }

    function testDoSendThrowsIfSendEmailFails()
    {
        $sut = $this->systemUnderTest('findOrConstructPasswordReset', 'sendEmail');
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $account->email = 'john@example.com';
        $account->displayName = 'John';
        $securityService = SecurityService::Instance();
        $pr = $this->createMock(PasswordReset::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('findOrConstructPasswordReset')
            ->with(42)
            ->willReturn($pr);
        $pr->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendEmail')
            ->with('john@example.com', 'John', 'code1234')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to send email.");
        ah::CallMethod($sut, 'doSend', [$account]);
    }

    function testDoSendSucceeds()
    {
        $sut = $this->systemUnderTest('findOrConstructPasswordReset', 'sendEmail');
        $account = $this->createStub(Account::class);
        $account->id = 42;
        $account->email = 'john@example.com';
        $account->displayName = 'John';
        $securityService = SecurityService::Instance();
        $pr = $this->createMock(PasswordReset::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('findOrConstructPasswordReset')
            ->with(42)
            ->willReturn($pr);
        $pr->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendEmail')
            ->with('john@example.com', 'John', 'code1234')
            ->willReturn(true);

        ah::CallMethod($sut, 'doSend', [$account]);
    }

    #endregion doSend

    #region findOrConstructPasswordReset ---------------------------------------

    function testFindOrConstructPasswordResetWithExistingRecord()
    {
        $sut = $this->systemUnderTest('findPasswordReset', 'constructPasswordReset');
        $pr = $this->createStub(PasswordReset::class);

        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with(42)
            ->willReturn($pr);
        $sut->expects($this->never())
            ->method('constructPasswordReset');

        $this->assertSame($pr, ah::CallMethod(
            $sut,
            'findOrConstructPasswordReset',
            [42]
        ));
    }

    function testFindOrConstructPasswordResetWithNonExistingRecord()
    {
        $sut = $this->systemUnderTest('findPasswordReset', 'constructPasswordReset');
        $pr = $this->createStub(PasswordReset::class);

        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with(42)
            ->willReturn(null);
        $sut->expects($this->once())
            ->method('constructPasswordReset')
            ->with(42)
            ->willReturn($pr);

        $this->assertSame($pr, ah::CallMethod(
            $sut,
            'findOrConstructPasswordReset',
            [42]
        ));
    }

    #endregion findOrConstructPasswordReset

    #region findPasswordReset --------------------------------------------------

    function testFindPasswordResetReturnsNullWhenNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset` WHERE accountId = :accountId LIMIT 1',
            bindings: ['accountId' => 42],
            result: null,
            times: 1
        );

        $pr = ah::CallMethod($sut, 'findPasswordReset', [42]);
        $this->assertNull($pr);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindPasswordResetReturnsEntityWhenFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset` WHERE accountId = :accountId LIMIT 1',
            bindings: ['accountId' => 42],
            result: [[
                'id' => 1,
                'accountId' => 42,
                'resetCode' => 'code1234',
                'timeRequested' => '2024-12-31 00:00:00'
            ]],
            times: 1
        );

        $pr = ah::CallMethod($sut, 'findPasswordReset', [42]);
        $this->assertInstanceOf(PasswordReset::class, $pr);
        $this->assertSame(1, $pr->id);
        $this->assertSame(42, $pr->accountId);
        $this->assertSame('code1234', $pr->resetCode);
        $this->assertSame('2024-12-31 00:00:00',
            $pr->timeRequested->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findPasswordReset

    #region constructPasswordReset ---------------------------------------------

    function testConstructPasswordReset()
    {
        $sut = $this->systemUnderTest();

        $pr = ah::CallMethod($sut, 'constructPasswordReset', [42]);
        $this->assertInstanceOf(PasswordReset::class, $pr);
        $this->assertSame(42, $pr->accountId);
    }

    #endregion constructPasswordReset

    #region sendEmail ----------------------------------------------------------

    #[TestWith([true])]
    #[TestWith([false])]
    function testSendEmail($returnValue)
    {
        $sut = $this->systemUnderTest('sendTransactionalEmail');
        $config = Config::Instance();
        $actionUrl = $this->createMock(CUrl::class);
        $resource = Resource::Instance();

        $config->expects($this->once())
            ->method('OptionOrDefault')
            ->with('AppName', '')
            ->willReturn('Example');
        $resource->expects($this->once())
            ->method('PageUrl')
            ->with('reset-password')
            ->willReturn($actionUrl);
        $actionUrl->expects($this->once())
            ->method('Extend')
            ->with('code1234')
            ->willReturnSelf();
        $actionUrl->expects($this->once())
            ->method('__toString')
            ->willReturn('https://example.com/pages/reset-password/code1234');
        $sut->expects($this->once())
            ->method('sendTransactionalEmail')
            ->with(
                'john@example.com',
                'John',
                'https://example.com/pages/reset-password/code1234',
                [
                    'heroText' =>
                        "Reset your password",
                    'introText' =>
                        "Follow the link below to choose a new password.",
                    'buttonText' =>
                        "Reset My Password",
                    'disclaimerText' =>
                        "You received this email because a password reset was"
                      . " requested for your account on Example. If you did"
                      . " not request this, you can safely ignore this email."
                ]
            )
            ->willReturn($returnValue);

        $this->assertSame(
            $returnValue,
            ah::CallMethod($sut, 'sendEmail', [
                'john@example.com',
                'John',
                'code1234'
            ])
        );
    }

    #endregion sendEmail

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'email missing' => [
                'payload' => [],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'email invalid' => [
                'payload' => ['email' => 'invalid-email'],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ]
        ];
    }

    #endregion Data Providers
}
