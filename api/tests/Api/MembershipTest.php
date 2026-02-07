<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class MembershipTest extends ApiTestCase
{
    public function testOrgAdminCanCreateMembership(): void
    {
        $admin = $this->createUser('memadmin@test.com');
        $newUser = $this->createUser('memnew@test.com');
        $org = $this->createOrganization();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('memadmin@test.com', $org->getId());
        $response = $client->request('POST', '/api/organization_memberships', [
            'json' => [
                'user' => '/api/users/' . $newUser->getId(),
                'organization' => '/api/organizations/' . $org->getId(),
                'role' => 'ROLE_MANAGER',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('ROLE_MANAGER', $data['role']);
    }

    public function testRegularUserCannotCreateMembership(): void
    {
        $user = $this->createUser('memreg@test.com');
        $target = $this->createUser('memtarget@test.com');
        $org = $this->createOrganization();

        $client = $this->createOrgClient('memreg@test.com', $org->getId());
        $client->request('POST', '/api/organization_memberships', [
            'json' => [
                'user' => '/api/users/' . $target->getId(),
                'organization' => '/api/organizations/' . $org->getId(),
                'role' => 'ROLE_ADMIN',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDuplicateMembershipFails(): void
    {
        $admin = $this->createUser('memdup_admin@test.com');
        $user = $this->createUser('memdup_user@test.com');
        $org = $this->createOrganization();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');
        $this->createMembership($user, $org, 'ROLE_MANAGER');

        $client = $this->createOrgClient('memdup_admin@test.com', $org->getId());
        $client->request('POST', '/api/organization_memberships', [
            'json' => [
                'user' => '/api/users/' . $user->getId(),
                'organization' => '/api/organizations/' . $org->getId(),
                'role' => 'ROLE_ADMIN',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUserSeesOwnMemberships(): void
    {
        $user = $this->createUser('memown@test.com');
        $other = $this->createUser('memother@test.com');
        $org = $this->createOrganization();
        $this->createMembership($user, $org, 'ROLE_MANAGER');
        $this->createMembership($other, $org, 'ROLE_ADMIN');

        // Without org context, regular user only sees their own
        $client = $this->createAuthClient('memown@test.com');
        $response = $client->request('GET', '/api/organization_memberships');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1, $data['totalItems']);
    }

    public function testOrgAdminSeesAllOrgMemberships(): void
    {
        $admin = $this->createUser('memadmall@test.com');
        $user = $this->createUser('memall_user@test.com');
        $org = $this->createOrganization();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');
        $this->createMembership($user, $org, 'ROLE_MANAGER');

        $client = $this->createOrgClient('memadmall@test.com', $org->getId());
        $response = $client->request('GET', '/api/organization_memberships');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(2, $data['totalItems']);
    }

    public function testOnlyPlatformAdminCanDeleteMembership(): void
    {
        $admin = $this->createUser('memdel_admin@test.com');
        $user = $this->createUser('memdel_user@test.com');
        $org = $this->createOrganization();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');
        $membership = $this->createMembership($user, $org, 'ROLE_MANAGER');

        // Org admin cannot delete
        $client = $this->createOrgClient('memdel_admin@test.com', $org->getId());
        $client->request('DELETE', '/api/organization_memberships/' . $membership->getId());
        $this->assertResponseStatusCodeSame(403);

        // Platform admin can delete
        $platformAdmin = $this->createUser('memdel_pa@test.com', 'test1234', ['ROLE_PLATFORM_ADMIN']);
        $paClient = $this->createAuthClient('memdel_pa@test.com');
        $paClient->request('DELETE', '/api/organization_memberships/' . $membership->getId());
        $this->assertResponseStatusCodeSame(204);
    }
}
