<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class BuildingApartmentTest extends ApiTestCase
{
    public function testListBuildingsRequiresOrgHeader(): void
    {
        $this->createUser('blist@test.com');
        $org = $this->createOrganization();
        $this->createBuilding($org, '10 Main St');

        // Without X-Organization-Id, non-admin gets empty results
        $client = $this->createAuthClient('blist@test.com');
        $response = $client->request('GET', '/api/buildings');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(0, $data['totalItems']);
    }

    public function testListBuildingsWithOrgHeader(): void
    {
        $user = $this->createUser('blist2@test.com');
        $org = $this->createOrganization();
        $this->createBuilding($org, '10 Main St');
        $this->createBuilding($org, '20 Main St');

        $client = $this->createOrgClient('blist2@test.com', $org->getId());
        $response = $client->request('GET', '/api/buildings');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(2, $data['totalItems']);
    }

    public function testOrgAdminCanCreateBuilding(): void
    {
        $admin = $this->createUser('badmin@test.com');
        $org = $this->createOrganization();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('badmin@test.com', $org->getId());
        $response = $client->request('POST', '/api/buildings', [
            'json' => [
                'organization' => '/api/organizations/' . $org->getId(),
                'address' => '99 New Building',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('99 New Building', $data['address']);
    }

    public function testRegularUserCannotCreateBuilding(): void
    {
        $this->createUser('breg@test.com');
        $org = $this->createOrganization();

        $client = $this->createOrgClient('breg@test.com', $org->getId());
        $client->request('POST', '/api/buildings', [
            'json' => [
                'organization' => '/api/organizations/' . $org->getId(),
                'address' => 'Nope',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testListApartmentsFilteredByOrg(): void
    {
        $user = $this->createUser('aptlist@test.com');
        $org1 = $this->createOrganization('Org 1');
        $building1 = $this->createBuilding($org1);
        $this->createApartment($building1, '1');

        $org2 = $this->createOrganization('Org 2');
        $building2 = $this->createBuilding($org2);
        $this->createApartment($building2, '1');

        $client = $this->createOrgClient('aptlist@test.com', $org1->getId());
        $response = $client->request('GET', '/api/apartments');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1, $data['totalItems']);
    }

    public function testOrgAdminCanCreateApartment(): void
    {
        $admin = $this->createUser('aptadmin@test.com');
        $org = $this->createOrganization();
        $building = $this->createBuilding($org);
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('aptadmin@test.com', $org->getId());
        $response = $client->request('POST', '/api/apartments', [
            'json' => [
                'building' => '/api/buildings/' . $building->getId(),
                'number' => '42',
                'totalArea' => '75.50',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('42', $data['number']);
    }

    public function testCreateParkingUnit(): void
    {
        $admin = $this->createUser('parkadmin@test.com');
        $org = $this->createOrganization();
        $building = $this->createBuilding($org);
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('parkadmin@test.com', $org->getId());
        $response = $client->request('POST', '/api/apartments', [
            'json' => [
                'building' => '/api/buildings/' . $building->getId(),
                'number' => 'P1',
                'totalArea' => '15.00',
                'type' => 'parking',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('P1', $data['number']);
        $this->assertSame('parking', $data['type']);
    }

    public function testFilterApartmentsByType(): void
    {
        $user = $this->createUser('typefilter@test.com');
        $org = $this->createOrganization();
        $building = $this->createBuilding($org);
        $this->createApartment($building, '1', '50.00', 'apartment');
        $this->createApartment($building, '2', '60.00', 'apartment');
        $this->createApartment($building, 'P1', '15.00', 'parking');

        $client = $this->createOrgClient('typefilter@test.com', $org->getId());

        // Filter parking only
        $response = $client->request('GET', '/api/apartments?type=parking');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1, $data['totalItems']);

        // Filter apartments only
        $response = $client->request('GET', '/api/apartments?type=apartment');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(2, $data['totalItems']);
    }

    public function testApartmentTypeDefaultsToApartment(): void
    {
        $admin = $this->createUser('deftype@test.com');
        $org = $this->createOrganization();
        $building = $this->createBuilding($org);
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('deftype@test.com', $org->getId());
        $response = $client->request('POST', '/api/apartments', [
            'json' => [
                'building' => '/api/buildings/' . $building->getId(),
                'number' => '99',
                'totalArea' => '50.00',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('apartment', $data['type']);
    }

    public function testItemLookupBypassesOrgFilter(): void
    {
        $user = $this->createUser('itemlookup@test.com');
        $org = $this->createOrganization();
        $building = $this->createBuilding($org);

        // Get building without org header — item lookup should still work
        $client = $this->createAuthClient('itemlookup@test.com');
        $response = $client->request('GET', '/api/buildings/' . $building->getId());

        $this->assertResponseIsSuccessful();
    }
}
