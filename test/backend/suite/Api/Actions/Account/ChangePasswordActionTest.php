<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\ChangePasswordAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Fakes\FakeDatabase;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(ChangePasswordAction::class)]
class ChangePasswordActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?AccountService $originalAccountService = null;
    private ?SecurityService $originalSecurityService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance(new FakeDatabase());
        $this->originalAccountService =
            AccountService::ReplaceInstance($this->createMock(AccountService::class));
        $this->originalSecurityService =
            SecurityService::ReplaceInstance($this->createMock(SecurityService::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
        AccountService::ReplaceInstance($this->originalAccountService);
        SecurityService::ReplaceInstance($this->originalSecurityService);
    }

    private function systemUnderTest(string ...$mockedMethods): ChangePasswordAction
    {
        return $this->getMockBuilder(ChangePasswordAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    function testOnExecuteThrowsIfUserNotLoggedIn()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn');

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountIsNotLocal()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'ensureLocalAccount');
        $accountView = $this->createStub(AccountView::class);

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('ensureLocalAccount')
            ->with($accountView)
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'ensureLocalAccount',
            'findAccount');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('ensureLocalAccount')
            ->with($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfRequestValidationFails()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'ensureLocalAccount',
            'findAccount', 'validateRequest');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('ensureLocalAccount')
            ->with($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfCurrentPasswordVerificationFails()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'ensureLocalAccount',
            'findAccount', 'validateRequest', 'verifyCurrentPassword');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
        $account = $this->createStub(Account::class);
        $account->passwordHash = 'hash1234';
        $payload = (object)[
            'currentPassword' => 'pass1234',
            'newPassword' => 'pass5678'
        ];

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('ensureLocalAccount')
            ->with($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('verifyCurrentPassword')
            ->with($payload->currentPassword, $account->passwordHash)
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoChangeFails()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'ensureLocalAccount',
            'findAccount', 'validateRequest', 'verifyCurrentPassword',
            'doChange');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
        $account = $this->createStub(Account::class);
        $account->passwordHash = 'hash1234';
        $payload = (object)[
            'currentPassword' => 'pass1234',
            'newPassword' => 'pass5678'
        ];

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('ensureLocalAccount')
            ->with($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('verifyCurrentPassword')
            ->with($payload->currentPassword, $account->passwordHash);
        $sut->expects($this->once())
            ->method('doChange')
            ->with($account, $payload->newPassword)
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'ensureLocalAccount',
            'findAccount', 'validateRequest', 'verifyCurrentPassword',
            'doChange');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
        $account = $this->createStub(Account::class);
        $account->passwordHash = 'hash1234';
        $payload = (object)[
            'currentPassword' => 'pass1234',
            'newPassword' => 'pass5678'
        ];

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('ensureLocalAccount')
            ->with($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('verifyCurrentPassword')
            ->with($payload->currentPassword, $account->passwordHash);
        $sut->expects($this->once())
            ->method('doChange')
            ->with($account, $payload->newPassword);

        ah::CallMethod($sut, 'onExecute');
    }

    #endregion onExecute

    #region ensureLoggedIn -----------------------------------------------------

    function testEnsureLoggedInThrowsIfUserIsNotLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "You do not have permission to perform this action.");
        $this->expectExceptionCode(StatusCode::Unauthorized->value);
        ah::CallMethod($sut, 'ensureLoggedIn');
    }

    function testEnsureLoggedInSucceedsIfUserIsLoggedIn()
    {
        $sut = $this->systemUnderTest();
        $accountService = AccountService::Instance();
        $accountView = $this->createStub(AccountView::class);

        $accountService->expects($this->once())
            ->method('SessionAccount')
            ->willReturn($accountView);

        $this->assertSame($accountView, ah::CallMethod($sut, 'ensureLoggedIn'));
    }

    #endregion ensureLoggedIn

    #region ensureLocalAccount -------------------------------------------------

    function testEnsureLocalAccountThrowsIfAccountIsNotLocal()
    {
        $sut = $this->systemUnderTest();
        $accountView = $this->createStub(AccountView::class);
        $accountView->isLocal = false;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "This account does not have a local password.");
        $this->expectExceptionCode(StatusCode::Forbidden->value);
        ah::CallMethod($sut, 'ensureLocalAccount', [$accountView]);
    }

    function testEnsureLocalAccountSucceedsIfAccountIsLocal()
    {
        $sut = $this->systemUnderTest();
        $accountView = $this->createStub(AccountView::class);
        $accountView->isLocal = true;

        $this->expectNotToPerformAssertions();
        ah::CallMethod($sut, 'ensureLocalAccount', [$accountView]);
    }

    #endregion ensureLocalAccount

    #region findAccount --------------------------------------------------------

    function testFindAccountThrowsIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: null,
            times: 1
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Account not found.");
        $this->expectExceptionCode(StatusCode::NotFound->value);
        ah::CallMethod($sut, 'findAccount', [42]);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountReturnsEntityIfRecordFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = Database::Instance();

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
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

        $account = ah::CallMethod($sut, 'findAccount', [42]);
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

    #region validateRequest ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
    function testValidateRequestThrows(
        array $payload,
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
            ->willReturn($payload);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        ah::CallMethod($sut, 'validateRequest');
    }

    function testValidateRequestSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $payload = [
            'currentPassword' => 'pass1234',
            'newPassword' => 'pass5678'
        ];
        $expected = (object)$payload;

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $actual = ah::CallMethod($sut, 'validateRequest');
        $this->assertEquals($expected, $actual);
    }

    #endregion validateRequest

    #region verifyCurrentPassword ----------------------------------------------

    function testVerifyCurrentPasswordThrowsIfPasswordIsIncorrect()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('pass1234', 'hash1234')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Current password is incorrect.");
        ah::CallMethod($sut, 'verifyCurrentPassword', ['pass1234', 'hash1234']);
    }

    function testVerifyCurrentPasswordSucceedsIfPasswordIsCorrect()
    {
        $sut = $this->systemUnderTest();
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('VerifyPassword')
            ->with('pass1234', 'hash1234')
            ->willReturn(true);

        ah::CallMethod($sut, 'verifyCurrentPassword', ['pass1234', 'hash1234']);
    }

    #endregion verifyCurrentPassword

    #region doChange -----------------------------------------------------------

    function testDoChangeThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('pass5678')
            ->willReturn('hash5678');
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to change password.");
        ah::CallMethod($sut, 'doChange', [$account, 'pass5678']);
        $this->assertSame('hash5678', $account->passwordHash);
    }

    function testDoChangeSucceeds()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('pass5678')
            ->willReturn('hash5678');
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);

        ah::CallMethod($sut, 'doChange', [$account, 'pass5678']);
        $this->assertSame('hash5678', $account->passwordHash);
    }

    #endregion doChange

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'currentPassword missing' => [
                'payload' => [],
                'exceptionMessage' => "Required field 'currentPassword' is missing."
            ],
            'currentPassword too short' => [
                'payload' => [
                    'currentPassword' => '1234567'
                ],
                'exceptionMessage' => "Field 'currentPassword' must have a minimum length of 8 characters."
            ],
            'currentPassword too long' => [
                'payload' => [
                    'currentPassword' => str_repeat('a', 73)
                ],
                'exceptionMessage' => "Field 'currentPassword' must have a maximum length of 72 characters."
            ],
            'newPassword missing' => [
                'payload' => [
                    'currentPassword' => 'pass1234'
                ],
                'exceptionMessage' => "Required field 'newPassword' is missing."
            ],
            'newPassword too short' => [
                'payload' => [
                    'currentPassword' => 'pass1234',
                    'newPassword' => '1234567'
                ],
                'exceptionMessage' => "Field 'newPassword' must have a minimum length of 8 characters."
            ],
            'newPassword too long' => [
                'payload' => [
                    'currentPassword' => 'pass1234',
                    'newPassword' => str_repeat('a', 73)
                ],
                'exceptionMessage' => "Field 'newPassword' must have a maximum length of 72 characters."
            ],
        ];
    }

    #endregion Data Providers
}
