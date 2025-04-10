<?php

namespace Tests;

use Mockery;
use Packback\Lti1p3\Interfaces\ILtiRegistration;
use Packback\Lti1p3\Interfaces\ILtiServiceConnector;
use Packback\Lti1p3\LtiNamesRolesProvisioningService;

class LtiNamesRolesProvisioningServiceTest extends TestCase
{
    private $connector;
    private $registration;
    public function setUp(): void
    {
        $this->connector = Mockery::mock(ILtiServiceConnector::class);
        $this->registration = Mockery::mock(ILtiRegistration::class);
    }

    public function testItInstantiates()
    {
        $nrps = new LtiNamesRolesProvisioningService($this->connector, $this->registration, []);

        $this->assertInstanceOf(LtiNamesRolesProvisioningService::class, $nrps);
    }

    public function testItGetsMembers()
    {
        $expected = ['members'];

        $nrps = new LtiNamesRolesProvisioningService($this->connector, $this->registration, [
            'context_memberships_url' => 'url',
        ]);
        $this->connector->shouldReceive('getAll')
            ->once()->andReturn($expected);

        $result = $nrps->getMembers();

        $this->assertEquals($expected, $result);
    }

    public function testItGetsMembersForResourceLink()
    {
        $expected = ['members'];

        $nrps = new LtiNamesRolesProvisioningService($this->connector, $this->registration, [
            'context_memberships_url' => 'url',
        ]);
        $this->connector->shouldReceive('getAll')
            ->withArgs(function ($registration, $scope, $request, $key) {
                return $request->getUrl() === 'url?rlid=resource-link-id' && $key === 'members';
            })
            ->once()->andReturn($expected);

        $result = $nrps->getMembers(['rlid' => 'resource-link-id']);

        $this->assertEquals($expected, $result);
    }

    public function testItGetsContext()
    {
        $expected = ['context'];

        $nrps = new LtiNamesRolesProvisioningService($this->connector, $this->registration, [
            'context_memberships_url' => 'url',
        ]);
        $this->connector->shouldReceive('makeServiceRequest')
            ->once()->andReturn($expected);

        $result = $nrps->getContext();
        echo json_encode($result);
        $this->assertEquals($expected, $result);
    }

}
