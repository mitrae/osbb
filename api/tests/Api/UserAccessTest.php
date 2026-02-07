<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class UserAccessTest extends ApiTestCase
{
    public function testUserCanViewOwnProfile(): void
    {
        $user = $this->createUser('uaown@test.com', 'test1234', ['ROLE_USER'], 'Self', 'Viewer');

        $client = $this->createAuthClient('uaown@test.com');
        $response = $client->request('GET', '/api/users/' . $user->getId());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('uaown@test.com', $data['email']);
        $this->assertSame('Self', $data['firstName']);
    }

    public function testRegularUserCannotViewOtherProfile(): void
    {
        $user = $this->createUser('uareg@test.com');
        $other = $this->createUser('uaother@test.com', 'test1234', ['ROLE_USER'], 'Other', 'Person');

        $client = $this->createAuthClient('uareg@test.com');
        $client->request('GET', '/api/users/' . $other->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testOrgManagerCanViewUserProfile(): void
    {
        $manager = $this->createUser('uamgr@test.com', 'test1234', ['ROLE_USER'], 'Manager', 'One');
        $target = $this->createUser('uatarget@test.com', 'test1234', ['ROLE_USER'], 'Target', 'User');
        $org = $this->createOrganization('Manager Org');
        $this->createMembership($manager, $org, 'ROLE_MANAGER');

        $client = $this->createOrgClient('uamgr@test.com', $org->getId());
        $response = $client->request('GET', '/api/users/' . $target->getId());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('uatarget@test.com', $data['email']);
        $this->assertSame('Target', $data['firstName']);
    }

    public function testOrgAdminCanViewUserProfile(): void
    {
        $admin = $this->createUser('uaadm@test.com', 'test1234', ['ROLE_USER'], 'Admin', 'One');
        $target = $this->createUser('uaadmtarget@test.com', 'test1234', ['ROLE_USER'], 'Target', 'Two');
        $org = $this->createOrganization('Admin Org');
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('uaadm@test.com', $org->getId());
        $response = $client->request('GET', '/api/users/' . $target->getId());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('Target', $data['firstName']);
        $this->assertSame('Two', $data['lastName']);
    }

    public function testPlatformAdminCanViewAnyUserProfile(): void
    {
        $pa = $this->createUser('uapa@test.com', 'test1234', ['ROLE_PLATFORM_ADMIN'], 'Platform', 'Admin');
        $target = $this->createUser('uapatarget@test.com', 'test1234', ['ROLE_USER'], 'Any', 'User');

        $client = $this->createAuthClient('uapa@test.com');
        $response = $client->request('GET', '/api/users/' . $target->getId());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('uapatarget@test.com', $data['email']);
    }

    public function testManagerWithoutOrgHeaderCannotViewOtherProfile(): void
    {
        $manager = $this->createUser('uanohead@test.com');
        $target = $this->createUser('uanohead_t@test.com');
        $org = $this->createOrganization('No Header Org');
        $this->createMembership($manager, $org, 'ROLE_MANAGER');

        // Auth client without X-Organization-Id header — ORG_ROLE_MANAGER voter denies
        $client = $this->createAuthClient('uanohead@test.com');
        $client->request('GET', '/api/users/' . $target->getId());

        $this->assertResponseStatusCodeSame(403);
    }
}
