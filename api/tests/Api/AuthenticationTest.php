<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class AuthenticationTest extends ApiTestCase
{
    public function testLoginSuccess(): void
    {
        $this->createUser('auth@test.com', 'secret123');

        $client = static::createClient();
        $response = $client->request('POST', '/api/login', [
            'json' => ['email' => 'auth@test.com', 'password' => 'secret123'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('token', $data);
    }

    public function testLoginWrongPassword(): void
    {
        $this->createUser('wrong@test.com', 'correct');

        $client = static::createClient();
        $client->request('POST', '/api/login', [
            'json' => ['email' => 'wrong@test.com', 'password' => 'incorrect'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginNonexistentEmail(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [
            'json' => ['email' => 'nobody@test.com', 'password' => 'whatever'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRegisterSuccess(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/register', [
            'json' => [
                'email' => 'newuser@test.com',
                'password' => 'newpass123',
                'firstName' => 'New',
                'lastName' => 'User',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('newuser@test.com', $data['email']);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $this->createUser('dup@test.com');

        $client = static::createClient();
        $client->request('POST', '/api/register', [
            'json' => [
                'email' => 'dup@test.com',
                'password' => 'pass1234',
                'firstName' => 'Dup',
                'lastName' => 'User',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRegisterMissingPassword(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/register', [
            'json' => [
                'email' => 'incomplete@test.com',
                'firstName' => 'Test',
                'lastName' => 'User',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUnauthenticatedAccess(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/organizations');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testJwtContainsCustomClaims(): void
    {
        $this->createUser('claims@test.com', 'secret123', ['ROLE_PLATFORM_ADMIN'], 'John', 'Doe');

        $client = static::createClient();
        $response = $client->request('POST', '/api/login', [
            'json' => ['email' => 'claims@test.com', 'password' => 'secret123'],
        ]);

        $token = $response->toArray()['token'];
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true);

        $this->assertSame('John', $payload['firstName']);
        $this->assertSame('Doe', $payload['lastName']);
        $this->assertTrue($payload['isPlatformAdmin']);
    }
}
