<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\PageSystem\AccessPolicies\AnyonePolicy;

#[CoversClass(AnyonePolicy::class)]
class AnyonePolicyTest extends TestCase
{
    function testEnforceDoesNothing()
    {
        $sut = new AnyonePolicy();
        $sut->Enforce();
        $this->assertTrue(true);
    }
}
