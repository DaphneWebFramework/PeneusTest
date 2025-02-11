<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Services\SecurityService;

use \TestToolkit\AccessHelper;

#[CoversClass(SecurityService::class)]
class SecurityServiceTest extends TestCase
{
    private const TOKEN_PATTERN = '/^[a-f0-9]{64}$/';
    private const CSRF_TOKEN_COOKIE_VALUE_PATTERN = '/^[a-f0-9]{120}$/';

    #region HashPassword -------------------------------------------------------

    function testHashPasswordWithEmptyPassword()
    {
        $securityService = new SecurityService();
        $password = '';
        $hash = $securityService->HashPassword($password);
        $this->assertTrue(\password_verify($password, $hash));
    }

    function testHashPasswordWithNonEmptyPassword()
    {
        $securityService = new SecurityService();
        $password = 'pass123';
        $hash = $securityService->HashPassword($password);
        $this->assertTrue(\password_verify($password, $hash));
    }

    #endregion HashPassword

    #region VerifyPassword -----------------------------------------------------

    function testVerifyPasswordWithInvalidPassword()
    {
        $securityService = new SecurityService();
        $password = 'pass123';
        $hash = \password_hash($password, \PASSWORD_DEFAULT);
        $this->assertFalse($securityService->VerifyPassword('invalid', $hash));
    }

    function testVerifyPasswordWithEmptyPassword()
    {
        $securityService = new SecurityService();
        $password = '';
        $hash = \password_hash($password, \PASSWORD_DEFAULT);
        $this->assertTrue($securityService->VerifyPassword($password, $hash));
    }

    function testVerifyPasswordWithNonEmptyPassword()
    {
        $securityService = new SecurityService();
        $password = 'pass123';
        $hash = \password_hash($password, \PASSWORD_DEFAULT);
        $this->assertTrue($securityService->VerifyPassword($password, $hash));
    }

    #endregion VerifyPassword

    #region GenerateToken ------------------------------------------------------

    function testGenerateToken()
    {
        $securityService = new SecurityService();
        $token = $securityService->GenerateToken();
        $this->assertMatchesRegularExpression(self::TOKEN_PATTERN, $token);
    }

    #endregion GenerateToken

    #region GenerateCsrfToken --------------------------------------------------

    function testGenerateCsrfToken()
    {
        $securityService = new SecurityService();
        $csrfToken = $securityService->GenerateCsrfToken();
        $this->assertMatchesRegularExpression(
            self::TOKEN_PATTERN, $csrfToken->token);
        $this->assertMatchesRegularExpression(
            self::CSRF_TOKEN_COOKIE_VALUE_PATTERN, $csrfToken->cookieValue);
        $deobfuscatedCookieValue = AccessHelper::CallNonPublicMethod(
            $securityService, 'deobfuscate', [$csrfToken->cookieValue]);
        $this->assertTrue(\password_verify($csrfToken->token, $deobfuscatedCookieValue));
    }

    #endregion GenerateCsrfToken

    #region VerifyCsrfToken ----------------------------------------------------

    function testVerifyCsrfTokenWithInvalidToken()
    {
        $securityService = new SecurityService();
        $csrfToken = $securityService->GenerateCsrfToken();
        $csrfToken->token = 'invalid';
        $this->assertFalse($securityService->VerifyCsrfToken($csrfToken));
    }

    function testVerifyCsrfTokenWithInvalidCookieValue()
    {
        $securityService = new SecurityService();
        $csrfToken = $securityService->GenerateCsrfToken();
        $csrfToken->cookieValue = 'invalid';
        $this->assertFalse($securityService->VerifyCsrfToken($csrfToken));
    }

    function testVerifyCsrfTokenWithValidTokenAndCookieValue()
    {
        $securityService = new SecurityService();
        $csrfToken = $securityService->GenerateCsrfToken();
        $this->assertTrue($securityService->VerifyCsrfToken($csrfToken));
    }

    #endregion VerifyCsrfToken
}
