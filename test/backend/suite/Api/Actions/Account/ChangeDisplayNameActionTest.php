<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;
use \PHPUnit\Framework\Attributes\DataProvider;

use \Peneus\Api\Actions\Account\ChangeDisplayNameAction;

use \Harmonia\Core\CArray;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \TestToolkit\AccessHelper as ah;

#[CoversClass(ChangeDisplayNameAction::class)]
class ChangeDisplayNameActionTest extends TestCase
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

    private function systemUnderTest(string ...$mockedMethods): ChangeDisplayNameAction
    {
        return $this->getMockBuilder(ChangeDisplayNameAction::class)
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

    function testOnExecuteThrowsIfAccountNotFound()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'findAccount');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfPayloadValidationFails()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'findAccount',
            'validatePayload');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
        $account = $this->createStub(Account::class);

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('validatePayload')
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteThrowsIfDoChangeFails()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'findAccount',
            'validatePayload', 'doChange');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
        $account = $this->createStub(Account::class);
        $payload = (object)[
            'displayName' => 'Alice'
        ];

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('doChange')
            ->with($account, $payload->displayName)
            ->willThrowException(new \RuntimeException('Expected message.'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected message.');
        ah::CallMethod($sut, 'onExecute');
    }

    function testOnExecuteSucceeds()
    {
        $sut = $this->systemUnderTest('ensureLoggedIn', 'findAccount',
            'validatePayload', 'doChange');
        $accountView = $this->createStub(AccountView::class);
        $accountView->id = 42;
        $account = $this->createStub(Account::class);
        $payload = (object)[
            'displayName' => 'Alice'
        ];

        $sut->expects($this->once())
            ->method('ensureLoggedIn')
            ->willReturn($accountView);
        $sut->expects($this->once())
            ->method('findAccount')
            ->with($accountView->id)
            ->willReturn($account);
        $sut->expects($this->once())
            ->method('validatePayload')
            ->willReturn($payload);
        $sut->expects($this->once())
            ->method('doChange')
            ->with($account, $payload->displayName);

        ah::CallMethod($sut, 'onExecute');
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
            'displayName' => 'Alice'
        ];
        $expected = (object)$payload;

        $request->expects($this->once())
            ->method('FormParams')
            ->willReturn($formParams);
        $formParams->expects($this->once())
            ->method('ToArray')
            ->willReturn($payload);

        $actual = ah::CallMethod($sut, 'validatePayload');
        $this->assertEquals($expected, $actual);
    }

    #endregion validatePayload

    #region doChange -----------------------------------------------------------

    function testDoChangeThrowsIfAccountSaveFails()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);

        $account->expects($this->once())
            ->method('Save')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to change display name.");
        ah::CallMethod($sut, 'doChange', [$account, 'Alice']);
        $this->assertSame('Alice', $account->displayName);
    }

    function testDoChangeSucceeds()
    {
        $sut = $this->systemUnderTest();
        $account = $this->createMock(Account::class);

        $account->expects($this->once())
            ->method('Save')
            ->willReturn(true);

        ah::CallMethod($sut, 'doChange', [$account, 'Alice']);
        $this->assertSame('Alice', $account->displayName);
    }

    #endregion doChange

    #region Data Providers -----------------------------------------------------

    /**
     * @return array<string, array{
     *   payload: array<string, mixed>,
     *   exceptionMessage: string
     * }>
     */
    static function invalidPayloadProvider()
    {
        return [
            'displayName missing' => [
                'payload' => [],
                'exceptionMessage' => "Required field 'displayName' is missing."
            ],
            'displayName invalid' => [
                'payload' => [ 'displayName' => '<invalid-display-name>' ],
                'exceptionMessage' => 'Display name is invalid. It must start'
                    . ' with a letter or number and may only contain letters,'
                    . ' numbers, spaces, dots, hyphens, and apostrophes.'
            ],
        ];
    }

    #endregion Data Providers
}
