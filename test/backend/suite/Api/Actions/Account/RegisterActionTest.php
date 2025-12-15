<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;
use \PHPUnit\Framework\Attributes\TestWith;

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
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(RegisterAction::class)]
class RegisterActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): RegisterAction
    {
        return $this->getMockBuilder(RegisterAction::class)
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

    function testOnExecuteThrowsIfAlreadyRegistered()
    {
        $sut = $this->systemUnderTest('validatePayload', 'ensureNotRegistered');
        $payload = (object)[
            'email' => 'john@example.com'
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAlreadyPending()
    {
        $sut = $this->systemUnderTest('validatePayload', 'ensureNotRegistered',
            'ensureNotPending');
        $payload = (object)[
            'email' => 'john@example.com'
        ];

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('ensureNotPending')
            ->with('john@example.com')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoRegisterFails()
    {
        $sut = $this->systemUnderTest('validatePayload', 'ensureNotRegistered',
            'ensureNotPending', 'doRegister');
        $payload = (object)[
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'displayName' => 'John'
        ];
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('ensureNotPending')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('doRegister')
            ->with($payload)
            ->willThrowException(new \RuntimeException());
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Account registration failed.");
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('validatePayload', 'ensureNotRegistered',
            'ensureNotPending', 'doRegister');
        $payload = (object)[
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'displayName' => 'John'
        ];
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('ensureNotPending')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('doRegister')
            ->with($payload);
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
            "An account activation link has been sent to your email address.",
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
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $payload = [
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'displayName' => 'John'
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

    #region ensureNotPending ---------------------------------------------------

    function testEnsureNotPendingThrowsIfCountIsNotZero()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `pendingaccount` WHERE email = :email',
            bindings: ['email' => 'john@example.com'],
            result: [[1]],
            times: 1
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("This account is already awaiting activation.");
        $this->expectExceptionCode(StatusCode::Conflict->value);
        ah::CallMethod($sut, 'ensureNotPending', ['john@example.com']);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testEnsureNotPendingSucceedsIfCountIsZero()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT COUNT(*) FROM `pendingaccount` WHERE email = :email',
            bindings: ['email' => 'john@example.com'],
            result: [[0]],
            times: 1
        );

        ah::CallMethod($sut, 'ensureNotPending', ['john@example.com']);
        $this->expectNotToPerformAssertions();
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion ensureNotPending

    #region doRegister ---------------------------------------------------------

    function testDoRegisterThrowsIfPendingAccountSaveFails()
    {
        $sut = $this->systemUnderTest('constructPendingAccount');
        $securityService = SecurityService::Instance();
        $payload = (object)[
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'displayName' => 'John'
        ];
        $pa = $this->createMock(PendingAccount::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('constructPendingAccount')
            ->with($payload, 'code1234')
            ->willReturn($pa);
        $pa->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save pending account.");
        ah::CallMethod($sut, 'doRegister', [$payload]);
    }

    function testDoRegisterThrowsIfSendEmailFails()
    {
        $sut = $this->systemUnderTest('constructPendingAccount', 'sendEmail');
        $securityService = SecurityService::Instance();
        $payload = (object)[
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'displayName' => 'John'
        ];
        $pa = $this->createMock(PendingAccount::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('constructPendingAccount')
            ->with($payload, 'code1234')
            ->willReturn($pa);
        $pa->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendEmail')
            ->with('john@example.com', 'John', 'code1234')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to send email.");
        ah::CallMethod($sut, 'doRegister', [$payload]);
    }

    function testDoRegisterSucceeds()
    {
        $sut = $this->systemUnderTest('constructPendingAccount', 'sendEmail');
        $securityService = SecurityService::Instance();
        $payload = (object)[
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'displayName' => 'John'
        ];
        $pa = $this->createMock(PendingAccount::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('constructPendingAccount')
            ->with($payload, 'code1234')
            ->willReturn($pa);
        $pa->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendEmail')
            ->with('john@example.com', 'John', 'code1234')
            ->willReturn(true);

        ah::CallMethod($sut, 'doRegister', [$payload]);
    }

    #endregion doRegister

    #region constructPendingAccount --------------------------------------------

    function testConstructPendingAccount()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();
        $payload = (object)[
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'displayName' => 'John'
        ];

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('pass1234')
            ->willReturn('hash1234');

        $pa = ah::CallMethod($sut, 'constructPendingAccount', [$payload, 'code1234']);
        $this->assertInstanceOf(PendingAccount::class, $pa);
        $this->assertSame(0, $pa->id);
        $this->assertSame($payload->email, $pa->email);
        $this->assertSame('hash1234', $pa->passwordHash);
        $this->assertSame($payload->displayName, $pa->displayName);
        $this->assertSame('code1234', $pa->activationCode);
        $this->assertEqualsWithDelta(\time(), $pa->timeRegistered->getTimestamp(), 1);
    }

    #endregion constructPendingAccount

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
            ->with('activate-account')
            ->willReturn($actionUrl);
        $actionUrl->expects($this->once())
            ->method('Extend')
            ->with('code1234')
            ->willReturnSelf();
        $actionUrl->expects($this->once())
            ->method('__toString')
            ->willReturn('https://example.com/pages/activate-account/code1234');
        $sut->expects($this->once())
            ->method('sendTransactionalEmail')
            ->with(
                'john@example.com',
                'John',
                'https://example.com/pages/activate-account/code1234',
                [
                    'heroText' =>
                        "Welcome to Example!",
                    'introText' =>
                        "You're almost there! Just click the button below to"
                      . " activate your account.",
                    'buttonText' =>
                        "Activate My Account",
                    'disclaimerText' =>
                        "You received this email because your email address was"
                      . " used to register on Example. If this wasn't you, you"
                      . " can safely ignore this email."
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
                'payload' => [
                    'email' => 'invalid-email'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'password missing' => [
                'payload' => [
                    'email' => 'john@example.com'
                ],
                'exceptionMessage' => "Required field 'password' is missing."
            ],
            'password too short' => [
                'payload' => [
                    'email' => 'john@example.com',
                    'password' => '1234567'
                ],
                'exceptionMessage' => "Field 'password' must have a minimum length of 8 characters."
            ],
            'password too long' => [
                'payload' => [
                    'email' => 'john@example.com',
                    'password' => str_repeat('a', 73)
                ],
                'exceptionMessage' => "Field 'password' must have a maximum length of 72 characters."
            ],
            'displayName missing' => [
                'payload' => [
                    'email' => 'john@example.com',
                    'password' => 'pass1234'
                ],
                'exceptionMessage' => "Required field 'displayName' is missing."
            ],
            'displayName invalid' => [
                'payload' => [
                    'email' => 'john@example.com',
                    'password' => 'pass1234',
                    'displayName' => '<invalid-display-name>'
                ],
                'exceptionMessage' => 'Display name is invalid. It must start'
                    . ' with a letter or number and may only contain letters,'
                    . ' numbers, spaces, dots, hyphens, and apostrophes.'
            ],
        ];
    }

    #endregion Data Providers
}
