<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class RequestTest extends ApiTestCase
{
    public function testOrgMemberCanCreateRequest(): void
    {
        $user = $this->createUser('reqcreate@test.com');
        [$org, $building, $apartment, $resident] = $this->createOrgStructure();
        $this->createMembership($user, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('reqcreate@test.com', $org->getId());
        $response = $client->request('POST', '/api/requests', [
            'json' => [
                'title' => 'Fix the roof',
                'description' => 'The roof is leaking in building 1',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('Fix the roof', $data['title']);
        $this->assertSame('open', $data['status']);
    }

    public function testRequestAutoSetsAuthorAndOrg(): void
    {
        $user = $this->createUser('reqauto@test.com');
        [$org, $building, $apartment, $resident] = $this->createOrgStructure();
        $this->createMembership($user, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('reqauto@test.com', $org->getId());
        $response = $client->request('POST', '/api/requests', [
            'json' => [
                'title' => 'Test Auto',
                'description' => 'Auto-set test',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertRelationHasId($data['author'], $user->getId(), '/api/users/');
        $this->assertRelationHasId($data['organization'], $org->getId(), '/api/organizations/');
    }

    public function testCreateRequestRequiresOrgContext(): void
    {
        $user = $this->createUser('reqnoorg@test.com');

        // No X-Organization-Id header → ORG_MEMBER voter denies access (no org context)
        $client = $this->createAuthClient('reqnoorg@test.com');
        $client->request('POST', '/api/requests', [
            'json' => [
                'title' => 'No Org',
                'description' => 'Missing org header',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotCreateRequest(): void
    {
        $user = $this->createUser('reqnonmem@test.com');
        $org = $this->createOrganization('Closed Org');

        $client = $this->createOrgClient('reqnonmem@test.com', $org->getId());
        $client->request('POST', '/api/requests', [
            'json' => [
                'title' => 'Denied',
                'description' => 'Should be denied',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testResidentCanCreateRequest(): void
    {
        $user = $this->createUser('reqres@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();
        // Link user as resident (no membership needed)
        $this->createResident($apartment, 'Res', 'User', '25.00', $user);

        $client = $this->createOrgClient('reqres@test.com', $org->getId());
        $response = $client->request('POST', '/api/requests', [
            'json' => [
                'title' => 'Resident Request',
                'description' => 'Created by resident',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
    }

    public function testListRequestsFilteredByOrg(): void
    {
        $admin = $this->createUser('reqlist_admin@test.com');
        [$org1] = $this->createOrgStructure('Org 1');
        [$org2] = $this->createOrgStructure('Org 2');
        $this->createMembership($admin, $org1, 'ROLE_ADMIN');
        $this->createMembership($admin, $org2, 'ROLE_ADMIN');

        // Create request in org1
        $client1 = $this->createOrgClient('reqlist_admin@test.com', $org1->getId());
        $client1->request('POST', '/api/requests', [
            'json' => ['title' => 'Org1 Request', 'description' => 'In org 1'],
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Create request in org2
        $client2 = $this->createOrgClient('reqlist_admin@test.com', $org2->getId());
        $client2->request('POST', '/api/requests', [
            'json' => ['title' => 'Org2 Request', 'description' => 'In org 2'],
        ]);
        $this->assertResponseStatusCodeSame(201);

        // List with org1 context — should see only 1
        $client3 = $this->createOrgClient('reqlist_admin@test.com', $org1->getId());
        $response = $client3->request('GET', '/api/requests');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1, $data['totalItems']);
    }

    public function testFilterRequestsByAuthor(): void
    {
        $author1 = $this->createUser('reqfilt_a1@test.com', 'test1234', ['ROLE_USER'], 'Alice', 'One');
        $author2 = $this->createUser('reqfilt_a2@test.com', 'test1234', ['ROLE_USER'], 'Bob', 'Two');
        [$org] = $this->createOrgStructure();
        $this->createMembership($author1, $org, 'ROLE_ADMIN');
        $this->createMembership($author2, $org, 'ROLE_MANAGER');

        // Create requests as author1
        $client1 = $this->createOrgClient('reqfilt_a1@test.com', $org->getId());
        $client1->request('POST', '/api/requests', [
            'json' => ['title' => 'By Alice 1', 'description' => 'First by Alice'],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $client1->request('POST', '/api/requests', [
            'json' => ['title' => 'By Alice 2', 'description' => 'Second by Alice'],
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Create request as author2
        $client2 = $this->createOrgClient('reqfilt_a2@test.com', $org->getId());
        $client2->request('POST', '/api/requests', [
            'json' => ['title' => 'By Bob', 'description' => 'One by Bob'],
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Filter by author1
        $response = $client1->request('GET', '/api/requests?author=/api/users/' . $author1->getId());
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(2, $data['totalItems']);
        foreach ($data['member'] as $req) {
            $this->assertRelationHasId($req['author'], $author1->getId(), '/api/users/');
        }

        // Filter by author2
        $response = $client1->request('GET', '/api/requests?author=/api/users/' . $author2->getId());
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1, $data['totalItems']);
        $this->assertRelationHasId($data['member'][0]['author'], $author2->getId(), '/api/users/');
    }

    public function testOrderRequestsByCreatedAt(): void
    {
        $admin = $this->createUser('reqord@test.com');
        [$org] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        // Create requests via entity manager with explicit timestamps to ensure ordering
        $em = $this->getEntityManager();
        $titles = ['First', 'Second', 'Third'];
        foreach ($titles as $i => $title) {
            $req = new \App\Entity\Request();
            $req->setTitle($title);
            $req->setDescription("Request $title");
            $req->setAuthor($admin);
            $req->setOrganization($org);
            // Use reflection to set createdAt with distinct timestamps
            $ref = new \ReflectionProperty($req, 'createdAt');
            $ref->setValue($req, new \DateTimeImmutable("2026-01-0" . ($i + 1) . " 12:00:00"));
            $em->persist($req);
        }
        $em->flush();

        $client = $this->createOrgClient('reqord@test.com', $org->getId());

        // Order desc — newest first
        $response = $client->request('GET', '/api/requests?order[createdAt]=desc');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(3, $data['totalItems']);
        $this->assertSame('Third', $data['member'][0]['title']);
        $this->assertSame('First', $data['member'][2]['title']);

        // Order asc — oldest first
        $response = $client->request('GET', '/api/requests?order[createdAt]=asc');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('First', $data['member'][0]['title']);
        $this->assertSame('Third', $data['member'][2]['title']);
    }

    public function testFilterRequestsByAuthorAndStatus(): void
    {
        $author = $this->createUser('reqcomb@test.com');
        [$org] = $this->createOrgStructure();
        $this->createMembership($author, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('reqcomb@test.com', $org->getId());

        // Create two requests
        $r1 = $client->request('POST', '/api/requests', [
            'json' => ['title' => 'Open Req', 'description' => 'stays open'],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $r2 = $client->request('POST', '/api/requests', [
            'json' => ['title' => 'Closed Req', 'description' => 'will close'],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $r2Id = $r2->toArray()['id'];

        // Close one
        $client->request('PATCH', '/api/requests/' . $r2Id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['status' => 'closed'],
        ]);
        $this->assertResponseIsSuccessful();

        // Filter by author + status=open → only the open request
        $response = $client->request('GET', '/api/requests?author=/api/users/' . $author->getId() . '&status=open');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1, $data['totalItems']);
        $this->assertSame('Open Req', $data['member'][0]['title']);
    }

    public function testAdminCanUpdateRequestStatus(): void
    {
        $admin = $this->createUser('reqstatus_admin@test.com');
        [$org] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        // Create a request
        $client = $this->createOrgClient('reqstatus_admin@test.com', $org->getId());
        $response = $client->request('POST', '/api/requests', [
            'json' => ['title' => 'Status Test', 'description' => 'Test status update'],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $requestId = $response->toArray()['id'];

        // Update status
        $response = $client->request('PATCH', '/api/requests/' . $requestId, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['status' => 'in_progress'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('in_progress', $data['status']);
    }
}
