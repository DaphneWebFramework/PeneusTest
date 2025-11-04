<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\ResetPasswordAction;

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

#[CoversClass(ResetPasswordAction::class)]
class ResetPasswordActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;
    private ?Resource $originalResource = null;
    private ?SecurityService $originalSecurityService = null;
    private ?CookieService $originalCookieService = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
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
        Resource::ReplaceInstance($this->originalResource);
        SecurityService::ReplaceInstance($this->originalSecurityService);
        CookieService::ReplaceInstance($this->originalCookieService);
    }

    private function systemUnderTest(string ...$mockedMethods): ResetPasswordAction
    {
        return $this->getMockBuilder(ResetPasswordAction::class)
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
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfAccountAndPasswordResetNotFound()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findAccountAndPasswordReset'
        );

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'resetCode' => 'code1234'
            ]);
        $sut->expects($this->once())
            ->method('findAccountAndPasswordReset')
            ->with('code1234')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoResetFails()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findAccountAndPasswordReset',
            'doReset'
        );
        $account = $this->createStub(Account::class);
        $pr = $this->createStub(PasswordReset::class);
        $database = Database::Instance();

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'resetCode' => 'code1234',
                'newPassword' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findAccountAndPasswordReset')
            ->with('code1234')
            ->willReturn([$account, $pr]);
        $sut->expects($this->once())
            ->method('doReset')
            ->with($account, 'pass1234', $pr)
            ->willThrowException(new \RuntimeException());
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Password reset failed.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest(
            'validateRequest',
            'findAccountAndPasswordReset',
            'doReset'
        );
        $account = $this->createStub(Account::class);
        $pr = $this->createStub(PasswordReset::class);
        $database = Database::Instance();
        $cookieService = CookieService::Instance();
        $redirectUrl = new CUrl('/url/to/login');
        $resource = Resource::Instance();

        $sut->expects($this->once())
            ->method('validateRequest')
            ->willReturn((object)[
                'resetCode' => 'code1234',
                'newPassword' => 'pass1234'
            ]);
        $sut->expects($this->once())
            ->method('findAccountAndPasswordReset')
            ->with('code1234')
            ->willReturn([$account, $pr]);
        $sut->expects($this->once())
            ->method('doReset')
            ->with($account, 'pass1234', $pr);
        $database->expects($this->once())
            ->method('WithTransaction')
            ->willReturnCallback(function($callback) {
                $callback();
            });
        $cookieService->expects($this->once())
            ->method('DeleteCsrfCookie');
        $resource->expects($this->once())
            ->method('LoginPageUrl')
            ->with('home')
            ->willReturn($redirectUrl);

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('redirectUrl', $result);
        $this->assertEquals($redirectUrl, $result['redirectUrl']);
    }

    #endregion onExecute

    #region validateRequest ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
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
        ah::CallMethod($sut, 'validateRequest');
    }

    function testValidateRequestSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);
        $data = [
            'resetCode' => \str_repeat('0123456789AbCdEf', 4),
            'newPassword' => 'pass1234'
        ];
        $expected = (object)$data;

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($data);

        $this->assertEquals($expected, ah::CallMethod($sut, 'validateRequest'));
    }

    #endregion validateRequest

    #region findAccountAndPasswordReset ----------------------------------------

    function testFindAccountAndPasswordResetThrowsIfPasswordResetNotFound()
    {
        $sut = $this->systemUnderTest('findPasswordReset');

        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with('code1234')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "This password reset request is no longer valid.");
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($sut, 'findAccountAndPasswordReset', ['code1234']);
    }

    function testFindAccountAndPasswordResetThrowsIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('findPasswordReset', 'findAccount');
        $pr = $this->createStub(PasswordReset::class);
        $pr->accountId = 42;

        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with('code1234')
            ->willReturn($pr);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with(42)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "This password reset request is no longer valid.");
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($sut, 'findAccountAndPasswordReset', ['code1234']);
    }

    function testFindAccountAndPasswordResetSucceeds()
    {
        $sut = $this->systemUnderTest('findPasswordReset', 'findAccount');
        $pr = $this->createStub(PasswordReset::class);
        $pr->accountId = 42;
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('findPasswordReset')
            ->with('code1234')
            ->willReturn($pr);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with(42)
            ->willReturn($account);

        $this->assertSame(
            [$account, $pr],
            ah::CallMethod($sut, 'findAccountAndPasswordReset', ['code1234'])
        );
    }

    #endregion findAccountAndPasswordReset

    #region findPasswordReset --------------------------------------------------

    function testFindPasswordResetReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset`'
               . ' WHERE resetCode = :resetCode LIMIT 1',
            bindings: ['resetCode' => 'code1234'],
            result: null,
            times: 1
        );

        $pr = ah::CallMethod($sut, 'findPasswordReset', ['code1234']);
        $this->assertNull($pr);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindPasswordResetReturnsEntityIfRecordFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `passwordreset`'
               . ' WHERE resetCode = :resetCode LIMIT 1',
            bindings: ['resetCode' => 'code1234'],
            result: [[
                'id' => 1,
                'accountId' => 42,
                'resetCode' => 'code1234',
                'timeRequested' => '2024-12-31 00:00:00'
            ]],
            times: 1
        );

        $pr = ah::CallMethod($sut, 'findPasswordReset', ['code1234']);
        $this->assertInstanceOf(PasswordReset::class, $pr);
        $this->assertSame(1, $pr->id);
        $this->assertSame(42, $pr->accountId);
        $this->assertSame('code1234', $pr->resetCode);
        $this->assertSame('2024-12-31 00:00:00',
            $pr->timeRequested->format('Y-m-d H:i:s'));
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    #endregion findPasswordReset

    #region findAccount --------------------------------------------------------

    function testFindAccountReturnsNullIfRecordNotFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

        $fakeDatabase->Expect(
            sql: 'SELECT * FROM `account` WHERE `id` = :id LIMIT 1',
            bindings: ['id' => 42],
            result: null,
            times: 1
        );

        $account = ah::CallMethod($sut, 'findAccount', [42]);
        $this->assertNull($account);
        $fakeDatabase->VerifyAllExpectationsMet();
    }

    function testFindAccountReturnsEntityIfRecordFound()
    {
        $sut = $this->systemUnderTest();
        $fakeDatabase = new FakeDatabase();
        Database::ReplaceInstance($fakeDatabase);

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

    #region doReset ------------------------------------------------------------

    function testDoResetThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $pr = $this->createStub(PasswordReset::class);
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('pass1234')
            ->willReturn('hash1234');
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        ah::CallMethod($sut, 'doReset', [$account, 'pass1234', $pr]);
    }

    function testDoResetThrowsIfPasswordResetDeleteFails()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $pr = $this->createMock(PasswordReset::class);
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('pass1234')
            ->willReturn('hash1234');
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $pr->expects($this->once())
            ->method('Delete')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to delete password reset.");
        ah::CallMethod($sut, 'doReset', [$account, 'pass1234', $pr]);
    }

    function testDoResetSucceeds()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);
        $pr = $this->createMock(PasswordReset::class);
        $securityService = SecurityService::Instance();

        $securityService->expects($this->once())
            ->method('HashPassword')
            ->with('pass1234')
            ->willReturn('hash1234');
        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $pr->expects($this->once())
            ->method('Delete')
            ->willReturn(true);

        ah::CallMethod($sut, 'doReset', [$account, 'pass1234', $pr]);
        $this->assertSame('hash1234', $account->passwordHash);
    }

    #endregion doReset

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'resetCode missing' => [
                'data' => [],
                'exceptionMessage' => 'Reset code is required.'
            ],
            'resetCode invalid' => [
                'data' => [
                    'resetCode' => 'invalid-code'
                ],
                'exceptionMessage' => 'Reset code format is invalid.'
            ],
            'newPassword missing' => [
                'data' => [
                    'resetCode' => str_repeat('a', 64)
                ],
                'exceptionMessage' => "Required field 'newPassword' is missing."
            ],
            'newPassword too short' => [
                'data' => [
                    'resetCode' => str_repeat('a', 64),
                    'newPassword' => '1234567'
                ],
                'exceptionMessage' => "Field 'newPassword' must have a minimum length of 8 characters."
            ],
            'newPassword too long' => [
                'data' => [
                    'resetCode' => str_repeat('a', 64),
                    'newPassword' => str_repeat('a', 73)
                ],
                'exceptionMessage' => "Field 'newPassword' must have a maximum length of 72 characters."
            ],
        ];
    }

    #endregion Data Providers
}
