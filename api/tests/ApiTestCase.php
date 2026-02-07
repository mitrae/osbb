<?php

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Apartment;
use App\Entity\Building;
use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\Resident;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends BaseApiTestCase
{
    private static array $tokenCache = [];

    protected function setUp(): void
    {
        self::$tokenCache = [];
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    protected function getToken(string $email, string $password = 'test1234'): string
    {
        $key = $email . ':' . $password;
        if (isset(self::$tokenCache[$key])) {
            return self::$tokenCache[$key];
        }

        $client = static::createClient();
        $response = $client->request('POST', '/api/login', [
            'json' => ['email' => $email, 'password' => $password],
        ]);

        $data = $response->toArray();
        self::$tokenCache[$key] = $data['token'];

        return $data['token'];
    }

    protected function createAuthClient(string $email, string $password = 'test1234'): Client
    {
        $token = $this->getToken($email, $password);

        return static::createClient([], [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
    }

    protected function createOrgClient(string $email, int $orgId, string $password = 'test1234'): Client
    {
        $token = $this->getToken($email, $password);

        return static::createClient([], [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'X-Organization-Id' => (string) $orgId,
            ],
        ]);
    }

    protected function createUser(
        string $email,
        string $password = 'test1234',
        array $roles = ['ROLE_USER'],
        string $firstName = 'Test',
        string $lastName = 'User',
    ): User {
        $em = $this->getEntityManager();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function createOrganization(string $name = 'Test Org', ?string $city = null, string $address = '123 Test St'): Organization
    {
        $em = $this->getEntityManager();

        $org = new Organization();
        $org->setName($name);
        $org->setCity($city);
        $org->setAddress($address);

        $em->persist($org);
        $em->flush();

        return $org;
    }

    protected function createBuilding(Organization $org, string $address = '1 Building St'): Building
    {
        $em = $this->getEntityManager();

        $building = new Building();
        $building->setOrganization($org);
        $building->setAddress($address);

        $em->persist($building);
        $em->flush();

        return $building;
    }

    protected function createApartment(Building $building, string $number = '1', string $totalArea = '50.00', string $type = 'apartment'): Apartment
    {
        $em = $this->getEntityManager();

        $apt = new Apartment();
        $apt->setBuilding($building);
        $apt->setNumber($number);
        $apt->setTotalArea($totalArea);
        $apt->setType($type);

        $em->persist($apt);
        $em->flush();

        return $apt;
    }

    protected function createResident(
        Apartment $apartment,
        string $firstName = 'Resident',
        string $lastName = 'Test',
        string $ownedArea = '50.00',
        ?User $user = null,
    ): Resident {
        $em = $this->getEntityManager();

        $resident = new Resident();
        $resident->setFirstName($firstName);
        $resident->setLastName($lastName);
        $resident->setApartment($apartment);
        $resident->setOwnedArea($ownedArea);
        if ($user) {
            $resident->setUser($user);
        }

        $em->persist($resident);
        $em->flush();

        return $resident;
    }

    protected function createMembership(User $user, Organization $org, string $role = OrganizationMembership::ROLE_ADMIN): OrganizationMembership
    {
        $em = $this->getEntityManager();

        $membership = new OrganizationMembership();
        $membership->setUser($user);
        $membership->setOrganization($org);
        $membership->setRole($role);

        $em->persist($membership);
        $em->flush();

        return $membership;
    }

    /**
     * Assert that a relation field contains the expected entity ID.
     * Works with both IRI strings and embedded objects.
     */
    protected function assertRelationHasId(mixed $fieldValue, int $expectedId, string $iriPrefix): void
    {
        if (is_array($fieldValue)) {
            $this->assertSame($expectedId, $fieldValue['id']);
        } else {
            $this->assertStringContainsString($iriPrefix . $expectedId, (string) $fieldValue);
        }
    }

    /**
     * Creates a full org structure: Organization → Building → Apartment → Resident
     * Returns [org, building, apartment, resident]
     */
    protected function createOrgStructure(string $orgName = 'Test Org', ?User $residentUser = null): array
    {
        $org = $this->createOrganization($orgName);
        $building = $this->createBuilding($org);
        $apartment = $this->createApartment($building);
        $resident = $this->createResident($apartment, user: $residentUser);

        return [$org, $building, $apartment, $resident];
    }
}
