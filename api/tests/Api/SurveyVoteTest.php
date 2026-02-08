<?php

namespace App\Tests\Api;

use App\Entity\Survey;
use App\Entity\SurveyQuestion;
use App\Tests\ApiTestCase;

class SurveyVoteTest extends ApiTestCase
{
    private function createSurveyEntity($org, $admin, array $extra = []): Survey
    {
        $em = $this->getEntityManager();
        $survey = new Survey();
        $survey->setTitle($extra['title'] ?? 'Test Survey');
        $survey->setOrganization($org);
        $survey->setCreatedBy($admin);
        $survey->setStartDate(new \DateTimeImmutable('-1 day'));
        $survey->setEndDate(new \DateTimeImmutable('+30 days'));
        if (isset($extra['propertyType'])) {
            $survey->setPropertyType($extra['propertyType']);
        }
        $em->persist($survey);
        return $survey;
    }

    public function testOrgAdminCanCreateSurvey(): void
    {
        $admin = $this->createUser('survadmin@test.com');
        [$org] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('survadmin@test.com', $org->getId());
        $response = $client->request('POST', '/api/surveys', [
            'json' => [
                'title' => 'Annual Budget',
                'description' => 'Vote on the 2026 budget',
                'organization' => '/api/organizations/' . $org->getId(),
                'startDate' => '2026-01-01T00:00:00+00:00',
                'endDate' => '2026-12-31T23:59:59+00:00',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('Annual Budget', $data['title']);
        if (array_key_exists('isActive', $data)) {
            $this->assertTrue($data['isActive']);
        }
    }

    public function testCanCreateSurveyQuestion(): void
    {
        $admin = $this->createUser('survq@test.com');
        [$org] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $survey = $this->createSurveyEntity($org, $admin);
        $this->getEntityManager()->flush();

        $client = $this->createOrgClient('survq@test.com', $org->getId());
        $response = $client->request('POST', '/api/survey_questions', [
            'json' => [
                'survey' => '/api/surveys/' . $survey->getId(),
                'questionText' => 'Do you approve the budget?',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('Do you approve the budget?', $data['questionText']);
    }

    public function testOrgMemberCanVote(): void
    {
        $admin = $this->createUser('votadmin@test.com');
        $voter = $this->createUser('voter@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');
        $this->createResident($apartment, 'Voter', 'Person', '35.50', $voter);

        $em = $this->getEntityManager();
        $survey = $this->createSurveyEntity($org, $admin, ['title' => 'Vote Survey']);

        $question = new SurveyQuestion();
        $question->setSurvey($survey);
        $question->setQuestionText('Approve?');
        $em->persist($question);
        $em->flush();

        $client = $this->createOrgClient('voter@test.com', $org->getId());
        $response = $client->request('POST', '/api/survey_votes', [
            'json' => [
                'question' => '/api/survey_questions/' . $question->getId(),
                'vote' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertTrue($data['vote']);
    }

    public function testVoteWeightFromOwnedArea(): void
    {
        $admin = $this->createUser('vweight_admin@test.com');
        $voter = $this->createUser('vweight_voter@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');
        $this->createResident($apartment, 'Weight', 'Voter', '45.75', $voter);

        $em = $this->getEntityManager();
        $survey = $this->createSurveyEntity($org, $admin, ['title' => 'Weight Survey']);

        $question = new SurveyQuestion();
        $question->setSurvey($survey);
        $question->setQuestionText('Weight test?');
        $em->persist($question);
        $em->flush();

        $client = $this->createOrgClient('vweight_voter@test.com', $org->getId());
        $response = $client->request('POST', '/api/survey_votes', [
            'json' => [
                'question' => '/api/survey_questions/' . $question->getId(),
                'vote' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('45.75', $data['weight']);
    }

    public function testReVoteUpdatesExistingVote(): void
    {
        $admin = $this->createUser('vdup_admin@test.com');
        $voter = $this->createUser('vdup_voter@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');
        $this->createResident($apartment, 'Dup', 'Voter', '20.00', $voter);

        $em = $this->getEntityManager();
        $survey = $this->createSurveyEntity($org, $admin, ['title' => 'Dup Survey']);

        $question = new SurveyQuestion();
        $question->setSurvey($survey);
        $question->setQuestionText('Dup test?');
        $em->persist($question);
        $em->flush();

        $client = $this->createOrgClient('vdup_voter@test.com', $org->getId());

        // First vote: Yes
        $response = $client->request('POST', '/api/survey_votes', [
            'json' => [
                'question' => '/api/survey_questions/' . $question->getId(),
                'vote' => true,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertTrue($data['vote']);

        // Re-vote: No — should succeed and update the existing vote
        $response = $client->request('POST', '/api/survey_votes', [
            'json' => [
                'question' => '/api/survey_questions/' . $question->getId(),
                'vote' => false,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertFalse($data['vote']);
        $this->assertSame('20.00', $data['weight']);
    }

    public function testNonMemberCannotVote(): void
    {
        $admin = $this->createUser('vnon_admin@test.com');
        $outsider = $this->createUser('vnon_outsider@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $em = $this->getEntityManager();
        $survey = $this->createSurveyEntity($org, $admin, ['title' => 'Non-member Survey']);

        $question = new SurveyQuestion();
        $question->setSurvey($survey);
        $question->setQuestionText('Restricted?');
        $em->persist($question);
        $em->flush();

        $client = $this->createOrgClient('vnon_outsider@test.com', $org->getId());
        $client->request('POST', '/api/survey_votes', [
            'json' => [
                'question' => '/api/survey_questions/' . $question->getId(),
                'vote' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMembershipOnlyUserCanVote(): void
    {
        $admin = $this->createUser('vmem_admin@test.com');
        $member = $this->createUser('vmem_member@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');
        $this->createMembership($member, $org, 'ROLE_MANAGER');

        $em = $this->getEntityManager();
        $survey = $this->createSurveyEntity($org, $admin, ['title' => 'Membership Vote Survey']);

        $question = new SurveyQuestion();
        $question->setSurvey($survey);
        $question->setQuestionText('Member vote?');
        $em->persist($question);
        $em->flush();

        $client = $this->createOrgClient('vmem_member@test.com', $org->getId());
        $response = $client->request('POST', '/api/survey_votes', [
            'json' => [
                'question' => '/api/survey_questions/' . $question->getId(),
                'vote' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('0.00', $data['weight']);
    }

    public function testPropertyTypeSurveyOnlyCountsMatchingArea(): void
    {
        $admin = $this->createUser('vpt_admin@test.com');
        $voter = $this->createUser('vpt_voter@test.com');
        $org = $this->createOrganization();
        $building = $this->createBuilding($org);
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $apt = $this->createApartment($building, '1', '50.00', 'apartment');
        $this->createResident($apt, 'Voter', 'Apt', '50.00', $voter);
        $parking = $this->createApartment($building, 'P1', '15.00', 'parking');
        $this->createResident($parking, 'Voter', 'Park', '15.00', $voter);

        $em = $this->getEntityManager();
        $survey = $this->createSurveyEntity($org, $admin, ['title' => 'Parking Survey', 'propertyType' => 'parking']);

        $question = new SurveyQuestion();
        $question->setSurvey($survey);
        $question->setQuestionText('Parking question?');
        $em->persist($question);
        $em->flush();

        $client = $this->createOrgClient('vpt_voter@test.com', $org->getId());
        $response = $client->request('POST', '/api/survey_votes', [
            'json' => [
                'question' => '/api/survey_questions/' . $question->getId(),
                'vote' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('15.00', $data['weight']);
    }

    public function testPropertyTypeSurveyBlocksNonMatchingOwner(): void
    {
        $admin = $this->createUser('vptblock_admin@test.com');
        $aptOwner = $this->createUser('vptblock_aptowner@test.com');
        $org = $this->createOrganization();
        $building = $this->createBuilding($org);
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $apt = $this->createApartment($building, '1', '50.00', 'apartment');
        $this->createResident($apt, 'Apt', 'Owner', '50.00', $aptOwner);

        $em = $this->getEntityManager();
        $survey = $this->createSurveyEntity($org, $admin, ['title' => 'Parking Only Survey', 'propertyType' => 'parking']);

        $question = new SurveyQuestion();
        $question->setSurvey($survey);
        $question->setQuestionText('Parking only?');
        $em->persist($question);
        $em->flush();

        $client = $this->createOrgClient('vptblock_aptowner@test.com', $org->getId());
        $client->request('POST', '/api/survey_votes', [
            'json' => [
                'question' => '/api/survey_questions/' . $question->getId(),
                'vote' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSurveyAutoSetsCreatedBy(): void
    {
        $admin = $this->createUser('survauto@test.com');
        [$org] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');

        $client = $this->createOrgClient('survauto@test.com', $org->getId());
        $response = $client->request('POST', '/api/surveys', [
            'json' => [
                'title' => 'Auto Created By',
                'organization' => '/api/organizations/' . $org->getId(),
                'startDate' => '2026-01-01T00:00:00+00:00',
                'endDate' => '2026-12-31T23:59:59+00:00',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertRelationHasId($data['createdBy'], $admin->getId(), '/api/users/');
    }

    public function testCannotVoteBeforeStartDate(): void
    {
        $admin = $this->createUser('vbefore_admin@test.com');
        $voter = $this->createUser('vbefore_voter@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');
        $this->createResident($apartment, 'Before', 'Voter', '30.00', $voter);

        $em = $this->getEntityManager();
        $survey = new Survey();
        $survey->setTitle('Future Survey');
        $survey->setOrganization($org);
        $survey->setCreatedBy($admin);
        $survey->setStartDate(new \DateTimeImmutable('+10 days'));
        $survey->setEndDate(new \DateTimeImmutable('+40 days'));
        $em->persist($survey);

        $question = new SurveyQuestion();
        $question->setSurvey($survey);
        $question->setQuestionText('Too early?');
        $em->persist($question);
        $em->flush();

        $client = $this->createOrgClient('vbefore_voter@test.com', $org->getId());
        $client->request('POST', '/api/survey_votes', [
            'json' => [
                'question' => '/api/survey_questions/' . $question->getId(),
                'vote' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCannotVoteAfterEndDate(): void
    {
        $admin = $this->createUser('vafter_admin@test.com');
        $voter = $this->createUser('vafter_voter@test.com');
        [$org, $building, $apartment] = $this->createOrgStructure();
        $this->createMembership($admin, $org, 'ROLE_ADMIN');
        $this->createResident($apartment, 'After', 'Voter', '30.00', $voter);

        $em = $this->getEntityManager();
        $survey = new Survey();
        $survey->setTitle('Expired Survey');
        $survey->setOrganization($org);
        $survey->setCreatedBy($admin);
        $survey->setStartDate(new \DateTimeImmutable('-40 days'));
        $survey->setEndDate(new \DateTimeImmutable('-1 day'));
        $em->persist($survey);

        $question = new SurveyQuestion();
        $question->setSurvey($survey);
        $question->setQuestionText('Too late?');
        $em->persist($question);
        $em->flush();

        $client = $this->createOrgClient('vafter_voter@test.com', $org->getId());
        $client->request('POST', '/api/survey_votes', [
            'json' => [
                'question' => '/api/survey_questions/' . $question->getId(),
                'vote' => true,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
