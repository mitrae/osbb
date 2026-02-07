<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class OrganizationTest extends ApiTestCase
{
    public function testListOrganizations(): void
    {
        $user = $this->createUser('orglist@test.com');
        $this->createOrganization('Org A');
        $this->createOrganization('Org B');

        $client = $this->createAuthClient('orglist@test.com');
        $response = $client->request('GET', '/api/organizations');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(2, $data['totalItems']);
    }

    public function testGetOrganization(): void
    {
        $user = $this->createUser('orgget@test.com');
        $org = $this->createOrganization('Get Org');

        $client = $this->createAuthClient('orgget@test.com');
        $response = $client->request('GET', '/api/organizations/' . $org->getId());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('Get Org', $data['name']);
    }

    public function testPlatformAdminCanCreateOrg(): void
    {
        $this->createUser('padmin@test.com', 'test1234', ['ROLE_PLATFORM_ADMIN']);

        $client = $this->createAuthClient('padmin@test.com');
        $response = $client->request('POST', '/api/organizations', [
            'json' => [
                'name' => 'New Org',
                'address' => '456 Admin St',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('New Org', $data['name']);
    }

    public function testRegularUserCannotCreateOrg(): void
    {
        $this->createUser('reguser@test.com');

        $client = $this->createAuthClient('reguser@test.com');
        $client->request('POST', '/api/organizations', [
            'json' => [
                'name' => 'Unauthorized Org',
                'address' => '789 No Way',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testPlatformAdminCanUpdateOrg(): void
    {
        $this->createUser('padmin2@test.com', 'test1234', ['ROLE_PLATFORM_ADMIN']);
        $org = $this->createOrganization('Old Name');

        $client = $this->createAuthClient('padmin2@test.com');
        $response = $client->request('PATCH', '/api/organizations/' . $org->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Updated Name'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('Updated Name', $data['name']);
    }

    public function testOrgAdminCanUpdateOrg(): void
    {
        $admin = $this->createUser('orgadmin@test.com');
        $org = $this->createOrganization('Admin Org');
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('orgadmin@test.com', $org->getId());
        $response = $client->request('PATCH', '/api/organizations/' . $org->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Admin Updated'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('Admin Updated', $data['name']);
    }

    public function testPlatformAdminCanDeleteOrg(): void
    {
        $this->createUser('padmin3@test.com', 'test1234', ['ROLE_PLATFORM_ADMIN']);
        $org = $this->createOrganization('Delete Me');

        $client = $this->createAuthClient('padmin3@test.com');
        $client->request('DELETE', '/api/organizations/' . $org->getId());

        $this->assertResponseStatusCodeSame(204);
    }
}
