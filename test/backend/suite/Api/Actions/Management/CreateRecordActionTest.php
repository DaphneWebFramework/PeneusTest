<?php declare(strict_types=1);
namespace suite\Api\Actions\Management;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Management\CreateRecordAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\SecurityService;
use \Peneus\Model\AccountRole; // sample
use \Peneus\Services\AccountService;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(CreateRecordAction::class)]
class CreateRecordActionTest extends TestCase
{
    private ?Request $originalRequest = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
    }

    private function systemUnderTest(string ...$mockedMethods): CreateRecordAction
    {
        return $this->getMockBuilder(CreateRecordAction::class)
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

    function testOnExecuteThrowsIfEntitySaveFails()
    {
        $sut = $this->systemUnderTest('validatePayload', 'constructEntity');
        $payload = (object)[
            'entityClass' => AccountRole::class,
            'data' => [
                'accountId' => 1,
                'role' => 10
            ]
        ];
        $entity = $this->createMock(AccountRole::class);

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('constructEntity')
            ->with($payload->entityClass, $payload->data)
            ->willReturn($entity);
        $entity->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to create record.");
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('validatePayload', 'constructEntity');
        $payload = (object)[
            'entityClass' => AccountRole::class,
            'data' => [
                'accountId' => 1,
                'role' => 10
            ]
        ];
        $entity = $this->createMock(AccountRole::class);
        $entity->id = 42;

        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('constructEntity')
            ->with($payload->entityClass, $payload->data)
            ->willReturn($entity);
        $entity->expects($this->once())
            ->method('Save')
            ->willReturn(true);

        $result = ah::CallMethod($sut, 'onExecute');
        $this->assertSame(['id' => 42], $result);
    }

    #endregion onExecute

    #region validatePayload ----------------------------------------------------

    #[DataProvider('invalidPayloadProvider')]
    function testValidatePayloadThrows(
        array $query,
        array $body,
        string $exceptionMessage
    ) {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($query);
        $request->expects($this->any()) // any: QueryParams validation might fail
            ->method('JsonBody')
            ->willReturn($body);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->expectExceptionCode(StatusCode::BadRequest->value);
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadThrowsIfModelResolutionFails()
    {
        $sut = $this->systemUnderTest('resolveEntityClass');
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $query = ['table' => 'not-a-table'];

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($query);
        $sut->expects($this->once())
            ->method('resolveEntityClass')
            ->with('not-a-table')
            ->willThrowException(new \InvalidArgumentException('Expected message.'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'validatePayload');
    }

    function testValidatePayloadSucceeds()
    {
        $sut = $this->systemUnderTest();
        $request = Request::Instance();
        $queryParams = $this->createMock(CArray::class);
        $query = ['table' => 'accountrole'];
        $body = [
            'accountId' => 1,
            'role' => 10
        ];
        $expected = (object)[
            'entityClass' => AccountRole::class,
            'data' => $body
        ];

        $request->expects($this->once())
            ->method('QueryParams')
            ->willReturn($queryParams);
        $queryParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($query);
        $request->expects($this->once())
            ->method('JsonBody')
            ->willReturn($body);

        $actual = ah::CallMethod($sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region constructEntity ----------------------------------------------------

    function testConstructEntity()
    {
        $sut = $this->systemUnderTest();
        $entityClass = AccountRole::class;

        $actual = ah::CallMethod($sut, 'constructEntity', [$entityClass, []]);
        $this->assertInstanceOf($entityClass, $actual);
    }

    #endregion constructEntity

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'table missing' => [
                'query' => [],
                'body' => [],
                'exceptionMessage' => "Required field 'table' is missing."
            ],
            'table not string' => [
                'query' => ['table' => 123],
                'body' => [],
                'exceptionMessage' => "Field 'table' must be a string."
            ],
            #region Account
            'account: email missing' => [
                'query' => ['table' => 'account'],
                'body' => [],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'account: email invalid' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'email' => 'invalid-email'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'account: passwordHash missing' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'email' => 'john@example.com'
                ],
                'exceptionMessage' => "Required field 'passwordHash' is missing."
            ],
            'account: passwordHash invalid' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => 'invalid-hash'
                ],
                'exceptionMessage' => "Field 'passwordHash' must match the required pattern: "
                    . SecurityService::PASSWORD_HASH_PATTERN
            ],
            'account: displayName missing' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123'
                ],
                'exceptionMessage' => "Required field 'displayName' is missing."
            ],
            'account: displayName invalid' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => '<invalid-display-name>'
                ],
                'exceptionMessage' => "Field 'displayName' must match the required pattern: "
                    . AccountService::DISPLAY_NAME_PATTERN
            ],
            'account: timeActivated missing' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John'
                ],
                'exceptionMessage' => "Required field 'timeActivated' is missing."
            ],
            'account: timeActivated invalid' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'timeActivated' => '01-01-2025'
                ],
                'exceptionMessage' => "Field 'timeActivated' must match the exact datetime format: Y-m-d H:i:s"
            ],
            'account: timeLastLogin missing' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'timeActivated' => '2025-01-01 00:00:00'
                ],
                'exceptionMessage' => "Required field 'timeLastLogin' is missing."
            ],
            'account: timeLastLogin invalid' => [
                'query' => ['table' => 'account'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'timeActivated' => '2025-01-01 00:00:00',
                    'timeLastLogin' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeLastLogin' must match the exact datetime format: Y-m-d H:i:s"
            ],
            #endregion Account
            #region AccountRole
            'accountRole: accountId missing' => [
                'query' => ['table' => 'accountrole'],
                'body' => [],
                'exceptionMessage' => "Required field 'accountId' is missing."
            ],
            'accountRole: accountId not an integer' => [
                'query' => ['table' => 'accountrole'],
                'body' => [
                    'accountId' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'accountId' must be an integer."
            ],
            'accountRole: accountId less than one' => [
                'query' => ['table' => 'accountrole'],
                'body' => [
                    'accountId' => 0
                ],
                'exceptionMessage' => "Field 'accountId' must have a minimum value of 1."
            ],
            'accountRole: role missing' => [
                'query' => ['table' => 'accountrole'],
                'body' => [
                    'accountId' => 1
                ],
                'exceptionMessage' => "Required field 'role' is missing."
            ],
            'accountRole: role invalid enum value' => [
                'query' => ['table' => 'accountrole'],
                'body' => [
                    'accountId' => 1,
                    'role' => 999
                ],
                'exceptionMessage' => "Field 'role' must be a valid value of enum 'Peneus\Model\Role'."
            ],
            #endregion AccountRole
            #region PendingAccount
            'pendingAccount: email missing' => [
                'query' => ['table' => 'pendingaccount'],
                'body' => [],
                'exceptionMessage' => "Required field 'email' is missing."
            ],
            'pendingAccount: email invalid' => [
                'query' => ['table' => 'pendingaccount'],
                'body' => [
                    'email' => 'invalid-email'
                ],
                'exceptionMessage' => "Field 'email' must be a valid email address."
            ],
            'pendingAccount: passwordHash missing' => [
                'query' => ['table' => 'pendingaccount'],
                'body' => [
                    'email' => 'john@example.com',
                ],
                'exceptionMessage' => "Required field 'passwordHash' is missing."
            ],
            'pendingAccount: passwordHash invalid' => [
                'query' => ['table' => 'pendingaccount'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => 'invalid-hash'
                ],
                'exceptionMessage' => "Field 'passwordHash' must match the required pattern: "
                    . SecurityService::PASSWORD_HASH_PATTERN
            ],
            'pendingAccount: displayName missing' => [
                'query' => ['table' => 'pendingaccount'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123'
                ],
                'exceptionMessage' => "Required field 'displayName' is missing."
            ],
            'pendingAccount: displayName invalid' => [
                'query' => ['table' => 'pendingaccount'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => '<invalid-display-name>'
                ],
                'exceptionMessage' => "Field 'displayName' must match the required pattern: "
                    . AccountService::DISPLAY_NAME_PATTERN
            ],
            'pendingAccount: activationCode missing' => [
                'query' => ['table' => 'pendingaccount'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John'
                ],
                'exceptionMessage' => "Required field 'activationCode' is missing."
            ],
            'pendingAccount: activationCode invalid' => [
                'query' => ['table' => 'pendingaccount'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'activationCode' => 'invalid-code'
                ],
                'exceptionMessage' => "Field 'activationCode' must match the required pattern: "
                    . SecurityService::TOKEN_DEFAULT_PATTERN
            ],
            'pendingAccount: timeRegistered missing' => [
                'query' => ['table' => 'pendingaccount'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'activationCode' => \str_repeat('a', 64),
                ],
                'exceptionMessage' => "Required field 'timeRegistered' is missing."
            ],
            'pendingAccount: timeRegistered invalid' => [
                'query' => ['table' => 'pendingaccount'],
                'body' => [
                    'email' => 'john@example.com',
                    'passwordHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'displayName' => 'John',
                    'activationCode' => \str_repeat('a', 64),
                    'timeRegistered' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeRegistered' must match the exact datetime format: Y-m-d H:i:s"
            ],
            #endregion PendingAccount
            #region PasswordReset
            'passwordReset: accountId missing' => [
                'query' => ['table' => 'passwordreset'],
                'body' => [],
                'exceptionMessage' => "Required field 'accountId' is missing."
            ],
            'passwordReset: accountId not an integer' => [
                'query' => ['table' => 'passwordreset'],
                'body' => [
                    'accountId' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'accountId' must be an integer."
            ],
            'passwordReset: accountId less than one' => [
                'query' => ['table' => 'passwordreset'],
                'body' => [
                    'accountId' => 0
                ],
                'exceptionMessage' => "Field 'accountId' must have a minimum value of 1."
            ],
            'passwordReset: resetCode missing' => [
                'query' => ['table' => 'passwordreset'],
                'body' => [
                    'accountId' => 1
                ],
                'exceptionMessage' => "Required field 'resetCode' is missing."
            ],
            'passwordReset: resetCode invalid' => [
                'query' => ['table' => 'passwordreset'],
                'body' => [
                    'accountId' => 1,
                    'resetCode' => 'invalid-token'
                ],
                'exceptionMessage' => "Field 'resetCode' must match the required pattern: "
                    . SecurityService::TOKEN_DEFAULT_PATTERN
            ],
            'passwordReset: timeRequested missing' => [
                'query' => ['table' => 'passwordreset'],
                'body' => [
                    'accountId' => 1,
                    'resetCode' => \str_repeat('a', 64)
                ],
                'exceptionMessage' => "Required field 'timeRequested' is missing."
            ],
            'passwordReset: timeRequested invalid' => [
                'query' => ['table' => 'passwordreset'],
                'body' => [
                    'accountId' => 1,
                    'resetCode' => \str_repeat('a', 64),
                    'timeRequested' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeRequested' must match the exact datetime format: Y-m-d H:i:s"
            ],
            #endregion PasswordReset
            #region PersistentLogin
            'persistentLogin: accountId missing' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [],
                'exceptionMessage' => "Required field 'accountId' is missing."
            ],
            'persistentLogin: accountId not an integer' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [
                    'accountId' => 'not-an-integer'
                ],
                'exceptionMessage' => "Field 'accountId' must be an integer."
            ],
            'persistentLogin: accountId less than one' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [
                    'accountId' => 0
                ],
                'exceptionMessage' => "Field 'accountId' must have a minimum value of 1."
            ],
            'persistentLogin: clientSignature missing' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [
                    'accountId' => 1
                ],
                'exceptionMessage' => "Required field 'clientSignature' is missing."
            ],
            'persistentLogin: clientSignature invalid' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [
                    'accountId' => 1,
                    'clientSignature' => 'invalid-signature'
                ],
                'exceptionMessage' => "Field 'clientSignature' must match the required pattern: /^[0-9a-zA-Z+\/]{22,24}$/"
            ],
            'persistentLogin: lookupKey missing' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22)
                ],
                'exceptionMessage' => "Required field 'lookupKey' is missing."
            ],
            'persistentLogin: lookupKey invalid' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22),
                    'lookupKey' => 'invalid-key'
                ],
                'exceptionMessage' => "Field 'lookupKey' must match the required pattern: /^[0-9a-fA-F]{16}$/"
            ],
            'persistentLogin: tokenHash missing' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22),
                    'lookupKey' => \str_repeat('a', 16)
                ],
                'exceptionMessage' => "Required field 'tokenHash' is missing."
            ],
            'persistentLogin: tokenHash invalid' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22),
                    'lookupKey' => \str_repeat('a', 16),
                    'tokenHash' => 'invalid-hash'
                ],
                'exceptionMessage' => "Field 'tokenHash' must match the required pattern: "
                    . SecurityService::PASSWORD_HASH_PATTERN
            ],
            'persistentLogin: timeExpires missing' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22),
                    'lookupKey' => \str_repeat('a', 16),
                    'tokenHash' => '$2y$10$12345678901234567890123456789012345678901234567890123'
                ],
                'exceptionMessage' => "Required field 'timeExpires' is missing."
            ],
            'persistentLogin: timeExpires invalid' => [
                'query' => ['table' => 'persistentlogin'],
                'body' => [
                    'accountId' => 1,
                    'clientSignature' => \str_repeat('a', 22),
                    'lookupKey' => \str_repeat('a', 16),
                    'tokenHash' => '$2y$10$12345678901234567890123456789012345678901234567890123',
                    'timeExpires' => 'not-a-datetime'
                ],
                'exceptionMessage' => "Field 'timeExpires' must match the exact datetime format: Y-m-d H:i:s"
            ],
            #endregion PersistentLogin
        ];
    }

    #endregion Data Providers
}
