<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Services\Model\CsrfToken;

#[CoversClass(CsrfToken::class)]
class CsrfTokenTest extends TestCase
{
    #region Token --------------------------------------------------------------

    function testToken()
    {
        $csrfToken = new CsrfToken('token', 'cookie-value');
        $this->assertEquals('token', $csrfToken->Token());
    }

    #endregion Token

    #region CookieValue --------------------------------------------------------

    function testCookieValue()
    {
        $csrfToken = new CsrfToken('token', 'cookie-value');
        $this->assertEquals('cookie-value', $csrfToken->CookieValue());
    }

    #endregion CookieValue
}
