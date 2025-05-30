<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Api\Actions\Traits\LoginUrlBuilder;

use \Harmonia\Core\CUrl;
use \Peneus\Resource;
use \TestToolkit\AccessHelper;

class _LoginUrlBuilder { use LoginUrlBuilder; }

#[CoversClass(_LoginUrlBuilder::class)]
class LoginUrlBuilderTest extends TestCase
{
    private ?Resource $originalResource = null;

    protected function setUp(): void
    {
        $this->originalResource =
            Resource::ReplaceInstance($this->createMock(Resource::class));
    }

    protected function tearDown(): void
    {
        Resource::ReplaceInstance($this->originalResource);
    }

    function testReturnsExpectedUrl()
    {
        $sut = new _LoginUrlBuilder();
        $resource = Resource::Instance();

        $resource->expects($this->exactly(2))
            ->method('PageUrl')
            ->willReturnCallback(function(string $pageId) {
                return match ($pageId) {
                    'home'  =>
                        new CUrl('https://example.com/pages/home/'),
                    'login' =>
                        new CUrl('https://example.com/pages/login/'),
                    default =>
                        $this->fail("Unexpected page ID: $pageId")
                };
            });

        $this->assertSame(
            'https://example.com/pages/login/?redirect=%2Fpages%2Fhome%2F',
            AccessHelper::CallMethod($sut, 'buildLoginUrl')
        );
    }
}
