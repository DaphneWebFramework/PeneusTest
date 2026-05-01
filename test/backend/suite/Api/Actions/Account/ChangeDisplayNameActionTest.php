<?php declare(strict_types=1);
namespace suite\Api\Actions\Account;

use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\ChangeDisplayNameAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \TestToolkit\AccessHelper as ah;
use \TestToolkit\Context;

#[CoversClass(ChangeDisplayNameAction::class)]
class ChangeDisplayNameActionTest extends TestCase
{
    private ?Request $originalRequest = null;
    private ?Database $originalDatabase = null;

    protected function setUp(): void
    {
        $this->originalRequest =
            Request::ReplaceInstance($this->createMock(Request::class));
        $this->originalDatabase =
            Database::ReplaceInstance($this->createMock(Database::class));
    }

    protected function tearDown(): void
    {
        Request::ReplaceInstance($this->originalRequest);
        Database::ReplaceInstance($this->originalDatabase);
    }

    private function systemUnderTest(string ...$mockedMethods): ChangeDisplayNameAction
    {
        return $this->getMockBuilder(ChangeDisplayNameAction::class)
            ->onlyMethods($mockedMethods)
            ->getMock();
    }

    #region onExecute ----------------------------------------------------------

    private function contextForOnExecute(
        bool $ensureLoggedInSucceeds = true,
        bool $findAccountSucceeds = true,
        bool $validatePayloadSucceeds = true,
        bool $doTransactionSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest(
            'ensureLoggedIn',
            'findAccount',
            'validatePayload',
            'doTransaction'
        );
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 17;
        $account = $this->createStub(Account::class);
        $payload = new \stdClass();
        $payload->displayName = 'Display Name';

        $ctx->sut->expects($ctx->chain())
            ->method('ensureLoggedIn')
            ->willReturnCallback(fn() => $ensureLoggedInSucceeds
                ? $accountView
                : throw new \RuntimeException('ENSURE_LOGGED_IN_FAILED'));
        $ctx->sut->expects($ctx->chainIf($ensureLoggedInSucceeds))
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturnCallback(fn() => $findAccountSucceeds
                ? $account
                : throw new \RuntimeException('FIND_ACCOUNT_FAILED'));
        $ctx->sut->expects($ctx->chainIf($findAccountSucceeds))
            ->method('validatePayload')
            ->willReturnCallback(fn() => $validatePayloadSucceeds
                ? $payload
                : throw new \RuntimeException('VALIDATE_PAYLOAD_FAILED'));
        $ctx->sut->expects($ctx->chainIf($validatePayloadSucceeds))
            ->method('doTransaction')
            ->with($account, $payload->displayName)
            ->willReturnCallback(fn() => $doTransactionSucceeds
                ? null
                : throw new \RuntimeException('DO_TRANSACTION_FAILED'));

        return $ctx;
    }

    function testOnExecuteFailsIfEnsureLoggedInFails()
    {
        $ctx = $this->contextForOnExecute(ensureLoggedInSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ENSURE_LOGGED_IN_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfFindAccountFails()
    {
        $ctx = $this->contextForOnExecute(findAccountSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FIND_ACCOUNT_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfValidatePayloadFails()
    {
        $ctx = $this->contextForOnExecute(validatePayloadSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VALIDATE_PAYLOAD_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteFailsIfDoTransactionFails()
    {
        $ctx = $this->contextForOnExecute(doTransactionSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DO_TRANSACTION_FAILED');
        ah::CallMethod($ctx->sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $ctx = $this->contextForOnExecute();
        $actual = ah::CallMethod($ctx->sut, 'onExecute');
        $this->assertNull($actual);
    }

    #endregion onExecute

    #region validatePayload ----------------------------------------------------

    private function contextForValidatePayload(
        array $payload
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest();
        $request = Request::Instance();
        $formParams = $this->createMock(CArray::class);

        $request->expects($ctx->chain())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($ctx->chain())
            ->method('ToArray')
            ->willReturn($payload);

        return $ctx;
    }

    #[DataProvider('invalidPayloadProvider')]
    function testValidatePayloadFails(array $payload)
    {
        $ctx = $this->contextForValidatePayload($payload);
        $this->expectException(\RuntimeException::class);
        ah::CallMethod($ctx->sut, 'validatePayload');
    }

    function testValidatePayloadSucceeds()
    {
        $payload = ['displayName' => 'Display Name'];
        $ctx = $this->contextForValidatePayload($payload);
        $expected = (object)$payload;
        $actual = ah::CallMethod($ctx->sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region doTransaction ------------------------------------------------------

    private function contextForDoTransaction(
        bool $accountSaveSucceeds = true
    ): Context
    {
        $ctx = new Context($this);
        $ctx->sut = $this->systemUnderTest();
        $database = Database::Instance();
        $ctx->account = $this->createMock(Account::class);
        $ctx->displayName = 'Display Name';

        $database->expects($ctx->chain())
            ->method('WithTransaction')
            ->willReturnCallback(fn($callback) => $callback());
        $ctx->account->expects($ctx->chain())
            ->method('Save')
            ->willReturn($accountSaveSucceeds);

        return $ctx;
    }

    function testDoTransactionFailsIfAccountSaveFails()
    {
        $ctx = $this->contextForDoTransaction(accountSaveSucceeds: false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to save account.");
        ah::CallMethod($ctx->sut, 'doTransaction', [
            $ctx->account,
            $ctx->displayName
        ]);
    }

    function testDoTransactionSucceeds()
    {
        $ctx = $this->contextForDoTransaction();
        ah::CallMethod($ctx->sut, 'doTransaction', [
            $ctx->account,
            $ctx->displayName
        ]);
        $this->assertSame($ctx->displayName, $ctx->account->displayName);
    }

    #endregion doTransaction

    #region Data Providers -----------------------------------------------------

    static function invalidPayloadProvider()
    {
        return [
            'displayName.required' => [[
                // empty
            ]],
            'displayName.regex' => [[
                'displayName' => '<invalid-display-name>'
            ]],
        ];
    }

    #endregion Data Providers
}
