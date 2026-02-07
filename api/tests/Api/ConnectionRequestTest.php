<?php

namespace App\Tests\Api;

use App\Entity\ConnectionRequest;
use App\Tests\ApiTestCase;

class ConnectionRequestTest extends ApiTestCase
{
    public function testUserCanCreateConnectionRequest(): void
    {
        $user = $this->createUser('conn@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();

        $client = $this->createAuthClient('conn@test.com');
        $response = $client->request('POST', '/api/connection_requests', [
            'json' => [
                'organization' => '/api/organizations/' . $org->getId(),
                'building' => '/api/buildings/' . $building->getId(),
                'apartment' => '/api/apartments/' . $apartment->getId(),
                'fullName' => 'Test Person',
                'phone' => '+380501234567',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('pending', $data['status']);
        $this->assertSame('Test Person', $data['fullName']);
    }

    public function testConnectionRequestAutoSetsUser(): void
    {
        $user = $this->createUser('connauto@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();

        $client = $this->createAuthClient('connauto@test.com');
        $response = $client->request('POST', '/api/connection_requests', [
            'json' => [
                'organization' => '/api/organizations/' . $org->getId(),
                'building' => '/api/buildings/' . $building->getId(),
                'apartment' => '/api/apartments/' . $apartment->getId(),
                'fullName' => 'Auto User',
                'phone' => '+380501234567',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertRelationHasId($data['user'], $user->getId(), '/api/users/');
    }

    public function testValidatesBuildingBelongsToOrg(): void
    {
        $user = $this->createUser('connval@test.com');
        [$org1, $building1, $apartment1] = $this->createOrgStructure('Org 1');
        [$org2, $building2] = $this->createOrgStructure('Org 2');

        // Building from org2, but selecting org1
        $client = $this->createAuthClient('connval@test.com');
        $client->request('POST', '/api/connection_requests', [
            'json' => [
                'organization' => '/api/organizations/' . $org1->getId(),
                'building' => '/api/buildings/' . $building2->getId(),
                'apartment' => '/api/apartments/' . $apartment1->getId(),
                'fullName' => 'Bad Request',
                'phone' => '+380501234567',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testValidatesApartmentBelongsToBuilding(): void
    {
        $user = $this->createUser('connval2@test.com');
        [$org, $building1, $apartment1] = $this->createOrgStructure();
        $building2 = $this->createBuilding($org, '2nd Building');
        $apartment2 = $this->createApartment($building2, '99');

        // Apartment from building2, but selecting building1
        $client = $this->createAuthClient('connval2@test.com');
        $client->request('POST', '/api/connection_requests', [
            'json' => [
                'organization' => '/api/organizations/' . $org->getId(),
                'building' => '/api/buildings/' . $building1->getId(),
                'apartment' => '/api/apartments/' . $apartment2->getId(),
                'fullName' => 'Bad Request',
                'phone' => '+380501234567',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAdminCanApproveAndLinkResident(): void
    {
        $user = $this->createUser('connapprove_user@test.com');
        $admin = $this->createUser('connapprove_admin@test.com');
        [$org, $building, $apartment, $resident] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        // Create connection request via direct DB
        $em = $this->getEntityManager();
        $cr = new ConnectionRequest();
        $cr->setUser($user);
        $cr->setOrganization($org);
        $cr->setBuilding($building);
        $cr->setApartment($apartment);
        $cr->setFullName('Approve Me');
        $cr->setPhone('+380501234567');
        $cr->setStatus('pending');
        $em->persist($cr);
        $em->flush();

        $client = $this->createOrgClient('connapprove_admin@test.com', $org->getId());
        $response = $client->request('PATCH', '/api/connection_requests/' . $cr->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'status' => 'approved',
                'resident' => '/api/residents/' . $resident->getId(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('approved', $data['status']);

        // Verify resident is now linked to user by re-fetching from DB
        $em->clear();
        $updatedResident = $em->getRepository(\App\Entity\Resident::class)->find($resident->getId());
        $this->assertNotNull($updatedResident->getUser());
        $this->assertSame($user->getId(), $updatedResident->getUser()->getId());
    }

    public function testAdminCanRejectRequest(): void
    {
        $user = $this->createUser('connreject_user@test.com');
        $admin = $this->createUser('connreject_admin@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $em = $this->getEntityManager();
        $cr = new ConnectionRequest();
        $cr->setUser($user);
        $cr->setOrganization($org);
        $cr->setBuilding($building);
        $cr->setApartment($apartment);
        $cr->setFullName('Reject Me');
        $cr->setPhone('+380501234567');
        $cr->setStatus('pending');
        $em->persist($cr);
        $em->flush();

        $client = $this->createOrgClient('connreject_admin@test.com', $org->getId());
        $response = $client->request('PATCH', '/api/connection_requests/' . $cr->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['status' => 'rejected'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('rejected', $data['status']);
    }

    public function testUserSeesOnlyOwnRequests(): void
    {
        $user1 = $this->createUser('connown1@test.com');
        $user2 = $this->createUser('connown2@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();

        $em = $this->getEntityManager();
        foreach ([$user1, $user2] as $u) {
            $cr = new ConnectionRequest();
            $cr->setUser($u);
            $cr->setOrganization($org);
            $cr->setBuilding($building);
            $cr->setApartment($apartment);
            $cr->setFullName($u->getEmail());
            $cr->setPhone('+380501234567');
            $cr->setStatus('pending');
            $em->persist($cr);
        }
        $em->flush();

        // User1 should only see their own
        $client = $this->createAuthClient('connown1@test.com');
        $response = $client->request('GET', '/api/connection_requests');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1, $data['totalItems']);
    }

    public function testOrgAdminSeesAllOrgRequests(): void
    {
        $user1 = $this->createUser('connadmin_u1@test.com');
        $user2 = $this->createUser('connadmin_u2@test.com');
        $admin = $this->createUser('connadmin_a@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $em = $this->getEntityManager();
        foreach ([$user1, $user2] as $u) {
            $cr = new ConnectionRequest();
            $cr->setUser($u);
            $cr->setOrganization($org);
            $cr->setBuilding($building);
            $cr->setApartment($apartment);
            $cr->setFullName($u->getEmail());
            $cr->setPhone('+380501234567');
            $cr->setStatus('pending');
            $em->persist($cr);
        }
        $em->flush();

        $client = $this->createOrgClient('connadmin_a@test.com', $org->getId());
        $response = $client->request('GET', '/api/connection_requests');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(2, $data['totalItems']);
    }
}
