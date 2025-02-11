<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Services\SecurityService;

use \Peneus\Services\CsrfToken;
use \TestToolkit\AccessHelper;

#[CoversClass(SecurityService::class)]
class SecurityServiceTest extends TestCase
{
    private const TOKEN_PATTERN = '/^[a-f0-9]{64}$/';
    private const CSRF_TOKEN_COOKIE_VALUE_PATTERN = '/^[a-f0-9]{120}$/';

    private ?SecurityService $originalSecurityService = null;

    protected function setUp(): void
    {
        $this->originalSecurityService = SecurityService::ReplaceInstance(null);
    }

    protected function tearDown(): void
    {
        SecurityService::ReplaceInstance($this->originalSecurityService);
    }

    #region HashPassword -------------------------------------------------------

    function testHashPasswordWithEmptyPassword()
    {
        $securityService = SecurityService::Instance();
        $password = '';
        $hash = $securityService->HashPassword($password);
        $this->assertTrue(\password_verify($password, $hash));
    }

    function testHashPasswordWithNonEmptyPassword()
    {
        $securityService = SecurityService::Instance();
        $password = 'pass123';
        $hash = $securityService->HashPassword($password);
        $this->assertTrue(\password_verify($password, $hash));
    }

    #endregion HashPassword

    #region VerifyPassword -----------------------------------------------------

    function testVerifyPasswordWithInvalidPassword()
    {
        $securityService = SecurityService::Instance();
        $password = 'pass123';
        $hash = \password_hash($password, \PASSWORD_DEFAULT);
        $this->assertFalse($securityService->VerifyPassword('invalid', $hash));
    }

    function testVerifyPasswordWithEmptyPassword()
    {
        $securityService = SecurityService::Instance();
        $password = '';
        $hash = \password_hash($password, \PASSWORD_DEFAULT);
        $this->assertTrue($securityService->VerifyPassword($password, $hash));
    }

    function testVerifyPasswordWithNonEmptyPassword()
    {
        $securityService = SecurityService::Instance();
        $password = 'pass123';
        $hash = \password_hash($password, \PASSWORD_DEFAULT);
        $this->assertTrue($securityService->VerifyPassword($password, $hash));
    }

    #endregion VerifyPassword

    #region GenerateToken ------------------------------------------------------

    function testGenerateToken()
    {
        $securityService = SecurityService::Instance();
        $token = $securityService->GenerateToken();
        $this->assertMatchesRegularExpression(self::TOKEN_PATTERN, $token);
    }

    #endregion GenerateToken

    #region GenerateCsrfToken --------------------------------------------------

    function testGenerateCsrfToken()
    {
        $securityService = SecurityService::Instance();
        $csrfToken = $securityService->GenerateCsrfToken();
        $this->assertMatchesRegularExpression(
            self::TOKEN_PATTERN, $csrfToken->Token());
        $this->assertMatchesRegularExpression(
            self::CSRF_TOKEN_COOKIE_VALUE_PATTERN, $csrfToken->CookieValue());
        $deobfuscatedCookieValue = AccessHelper::CallNonPublicMethod(
            $securityService, 'deobfuscate', [$csrfToken->CookieValue()]);
        $this->assertTrue(\password_verify($csrfToken->Token(),
                                           $deobfuscatedCookieValue));
    }

    #endregion GenerateCsrfToken

    #region VerifyCsrfToken ----------------------------------------------------

    function testVerifyCsrfTokenWithEmptyTokenAndEmptyCookieValue()
    {
        $securityService = SecurityService::Instance();
        $csrfToken = new CsrfToken('', '');
        $this->assertFalse($securityService->VerifyCsrfToken($csrfToken));
    }

    function testVerifyCsrfTokenWithInvalidTokenAndInvalidCookieValue()
    {
        $securityService = SecurityService::Instance();
        $csrfToken = new CsrfToken('invalid', 'invalid');
        $this->assertFalse($securityService->VerifyCsrfToken($csrfToken));
    }

    function testVerifyCsrfTokenWithValidTokenAndEmptyCookieValue()
    {
        $securityService = SecurityService::Instance();
        $csrfToken = new CsrfToken($securityService->GenerateToken(), '');
        $this->assertFalse($securityService->VerifyCsrfToken($csrfToken));
    }

    function testVerifyCsrfTokenWithValidTokenAndInvalidCookieValue()
    {
        $securityService = SecurityService::Instance();
        // Odd number of characters.
        $csrfToken = new CsrfToken($securityService->GenerateToken(), 'invalid');
        $this->assertFalse($securityService->VerifyCsrfToken($csrfToken));
        // Even number of characters.
        $csrfToken = new CsrfToken($securityService->GenerateToken(), 'invalid0');
        $this->assertFalse($securityService->VerifyCsrfToken($csrfToken));
    }

    function testVerifyCsrfTokenWithValidTokenAndValidCookieValue()
    {
        $securityService = SecurityService::Instance();
        $csrfToken = $securityService->GenerateCsrfToken();
        $this->assertTrue($securityService->VerifyCsrfToken($csrfToken));
    }

    #endregion VerifyCsrfToken
}
