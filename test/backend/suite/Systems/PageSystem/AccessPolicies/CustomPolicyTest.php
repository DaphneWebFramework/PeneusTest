<?php declare(strict_types=1);
use \PHPUnit\Framework\TestCase;
use \PHPUnit\Framework\Attributes\CoversClass;

use \Peneus\Systems\PageSystem\AccessPolicies\CustomPolicy;

#[CoversClass(CustomPolicy::class)]
class CustomPolicyTest extends TestCase
{
    function testEnforceCallsTheCallback()
    {
        $called = false;
        $policy = new CustomPolicy(function() use(&$called) {
            $called = true;
        });
        $policy->Enforce();
        $this->assertTrue($called);
    }
}
