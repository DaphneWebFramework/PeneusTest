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
use \TestToolkit\AccessHelper as AH;

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

    function testOnExecuteThrowsIfRequestValidationFails()
    {
        $sut = $this->systemUnderTest('validateRequest');

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAlreadyRegistered()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'ensureNotRegistered'
        );

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'email' => 'john@example.com'
            ]);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAlreadyPending()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'ensureNotRegistered',
            'ensureNotPending'
        );

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'email' => 'john@example.com'
            ]);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('ensureNotPending')
            ->with('john@example.com')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoRegisterFails()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'ensureNotRegistered',
            'ensureNotPending',
            'doRegister'
        );
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'displayName' => 'John'
            ]);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('ensureNotPending')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('doRegister')
            ->with('john@example.com', 'pass1234', 'John')
            ->willThrowException(new \RuntimeException());
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Account registration failed.");
        $this->expectExceptionCode(StatusCode::InternalServerError->value);
        AH::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'ensureNotRegistered',
            'ensureNotPending',
            'doRegister'
        );
        $database = Database::Instance();
        $cookieService = CookieService::Instance();

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'email' => 'john@example.com',
                'password' => 'pass1234',
                'displayName' => 'John'
            ]);
        $sut->expects($this->once())
            ->method('ensureNotRegistered')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('ensureNotPending')
            ->with('john@example.com');
        $sut->expects($this->once())
            ->method('doRegister')
            ->with('john@example.com', 'pass1234', 'John');
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');

        $result = AH::CallMethod($sut, 'onExecute');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertSame(
            "An account activation link has been sent to your email address.",
            $result['message']
        );
    }

    #endregion onExecute

    #region validateRequest ----------------------------------------------------

    #[DataProvider('invalidRequestDataProvider')]
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
        AH::CallMethod($sut, 'validateRequest');
    }

    function testValidateRequestSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $data = [
            'email' => 'john@example.com',
            'password' => 'pass1234',
            'displayName' => 'John'
        ];
        $expected = (object)$data;

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($data);

        $this->assertEquals($expected, AH::CallMethod($sut, 'validateRequest'));
    }

    #endregion validateRequest

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
        AH::CallMethod($sut, 'ensureNotRegistered', ['john@example.com']);
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

        AH::CallMethod($sut, 'ensureNotRegistered', ['john@example.com']);
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
        AH::CallMethod($sut, 'ensureNotPending', ['john@example.com']);
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

        AH::CallMethod($sut, 'ensureNotPending', ['john@example.com']);
        $this->expectNotToPerformAssertions();
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion ensureNotPending

    #region doRegister ---------------------------------------------------------

    function testDoRegisterThrowsIfPendingAccountSaveFails()
    {
        $sut = $this->systemUnderTest('constructPendingAccount');
        $securityService = SecurityService::Instance();
        $pa = $this->createMock(PendingAccount::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('constructPendingAccount')
            ->with('john@example.com', 'pass1234', 'John', 'code1234')
            ->willReturn($pa);
        $pa->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save pending account.");
        AH::CallMethod($sut, 'doRegister', [
            'john@example.com',
            'pass1234',
            'John'
        ]);
    }

    function testDoRegisterThrowsIfSendEmailFails()
    {
        $sut = $this->systemUnderTest(
            'constructPendingAccount',
            'sendEmail'
        );
        $securityService = SecurityService::Instance();
        $pa = $this->createMock(PendingAccount::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('constructPendingAccount')
            ->with('john@example.com', 'pass1234', 'John', 'code1234')
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
        AH::CallMethod($sut, 'doRegister', [
            'john@example.com',
            'pass1234',
            'John'
        ]);
    }

    function testDoRegisterSucceeds()
    {
        $sut = $this->systemUnderTest(
            'constructPendingAccount',
            'sendEmail'
        );
        $securityService = SecurityService::Instance();
        $pa = $this->createMock(PendingAccount::class);

        $securityService->expects($this->once())
            ->method('GenerateToken')
            ->willReturn('code1234');
        $sut->expects($this->once())
            ->method('constructPendingAccount')
            ->with('john@example.com', 'pass1234', 'John', 'code1234')
            ->willReturn($pa);
        $pa->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $sut->expects($this->once())
            ->method('sendEmail')
            ->with('john@example.com', 'John', 'code1234')
            ->willReturn(true);

        AH::CallMethod($sut, 'doRegister', [
            'john@example.com',
            'pass1234',
            'John'
        ]);
    }

    #endregion doRegister

    #region constructPendingAccount --------------------------------------------

    function testConstructPendingAccount()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('pass1234')
            ->willReturn('hash1234');

        $pa = AH::CallMethod($sut, 'constructPendingAccount', [
            'john@example.com',
            'pass1234',
            'John',
            'code1234'
        ]);
        $this->assertInstanceOf(PendingAccount::class, $pa);
        $this->assertSame('john@example.com', $pa->email);
        $this->assertSame('hash1234', $pa->passwordHash);
        $this->assertSame('John', $pa->displayName);
        $this->assertSame('code1234', $pa->activationCode);
        $this->assertEqualsWithDelta(
            \time(),
            $pa->timeRegistered->getTimestamp(),
            1
        );
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
            AH::CallMethod($sut, 'sendEmail', [
                'john@example.com',
                'John',
                'code1234'
            ])
        );
    }

    #endregion sendEmail

    #region Data Providers -----------------------------------------------------

    static function invalidRequestDataProvider()
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
                'exceptionMessage' => 'Display name is invalid. It must start'
                    . ' with a letter or number and may only contain letters,'
                    . ' numbers, spaces, dots, hyphens, and apostrophes.'
            ],
        ];
    }

    #endregion Data Providers
}
