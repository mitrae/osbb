<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class ResidentTest extends ApiTestCase
{
    public function testOrgAdminCanCreateResident(): void
    {
        $admin = $this->createUser('resadmin@test.com');
        $org = $this->createOrganization();
        $building = $this->createBuilding($org);
        $apartment = $this->createApartment($building);
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('resadmin@test.com', $org->getId());
        $response = $client->request('POST', '/api/residents', [
            'json' => [
                'firstName' => 'Ivan',
                'lastName' => 'Petrov',
                'apartment' => '/api/apartments/' . $apartment->getId(),
                'ownedArea' => '30.00',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('Ivan', $data['firstName']);
        $this->assertSame('30.00', $data['ownedArea']);
    }

    public function testRegularUserCannotCreateResident(): void
    {
        $this->createUser('resreg@test.com');
        $org = $this->createOrganization();
        $building = $this->createBuilding($org);
        $apartment = $this->createApartment($building);

        $client = $this->createOrgClient('resreg@test.com', $org->getId());
        $client->request('POST', '/api/residents', [
            'json' => [
                'firstName' => 'Nope',
                'lastName' => 'Nope',
                'apartment' => '/api/apartments/' . $apartment->getId(),
                'ownedArea' => '10.00',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testListResidentsFilteredByOrg(): void
    {
        $user = $this->createUser('reslist@test.com');
        [$org1, $building1, $apt1] = $this->createOrgStructure('Org A');
        [$org2] = $this->createOrgStructure('Org B');

        $client = $this->createOrgClient('reslist@test.com', $org1->getId());
        $response = $client->request('GET', '/api/residents');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1, $data['totalItems']);
    }

    public function testOrgAdminCanLinkUserToResident(): void
    {
        $admin = $this->createUser('reslink_admin@test.com');
        $linkedUser = $this->createUser('reslink_user@test.com');
        [$org, $building, $apartment, $resident] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('reslink_admin@test.com', $org->getId());
        $response = $client->request('PATCH', '/api/residents/' . $resident->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['user' => '/api/users/' . $linkedUser->getId()],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertRelationHasId($data['user'], $linkedUser->getId(), '/api/users/');
    }

    public function testDeleteResidentUnlinksUser(): void
    {
        $admin = $this->createUser('resdel_admin@test.com');
        $linkedUser = $this->createUser('resdel_user@test.com');
        [$org, $building, $apartment, $resident] = $this->createOrgStructure();
        $resident->setUser($linkedUser);
        $this->getEntityManager()->flush();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('resdel_admin@test.com', $org->getId());
        $client->request('DELETE', '/api/residents/' . $resident->getId());

        $this->assertResponseStatusCodeSame(204);
    }
}
