<?php

namespace Tests;

use Packback\Lti1p3\LtiDeployment;
use PHPUnit\Framework\TestCase;

class LtiDeploymentTest extends TestCase
{
    public function setUp(): void
    {
        $this->deployment = new LtiDeployment();
    }

    public function testItInstantiates()
    {
        $this->assertInstanceOf(LtiDeployment::class, $this->deployment);
    }

    public function testItGetsDeploymentId()
    {
        $result = $this->deployment->getDeploymentId();

        $this->assertNull($result);
    }

    public function testItSetsDeploymentId()
    {
        $expected = 'expected';

        $this->deployment->setDeploymentId($expected);

        $this->assertEquals($expected, $this->deployment->getDeploymentId());
    }
}
