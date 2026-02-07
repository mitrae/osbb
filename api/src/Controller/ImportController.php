<?php

namespace App\Controller;

use App\Entity\Apartment;
use App\Entity\Building;
use App\Entity\Organization;
use App\Entity\Resident;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ImportController extends AbstractController
{
    #[Route('/api/organizations/{orgId}/buildings/{buildingId}/import', name: 'api_import', methods: ['POST'])]
    #[IsGranted('ROLE_PLATFORM_ADMIN')]
    public function import(
        int $orgId,
        int $buildingId,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $organization = $em->find(Organization::class, $orgId);
        if (!$organization) {
            return new JsonResponse(['error' => 'Organization not found.'], Response::HTTP_NOT_FOUND);
        }

        $building = $em->find(Building::class, $buildingId);
        if (!$building) {
            return new JsonResponse(['error' => 'Building not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($building->getOrganization()->getId() !== $organization->getId()) {
            return new JsonResponse(['error' => 'Building does not belong to this organization.'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $csvContent = $data['csv'] ?? '';
        if (empty($csvContent)) {
            return new JsonResponse(['error' => 'CSV content is required.'], Response::HTTP_BAD_REQUEST);
        }

        $lines = explode("\n", $csvContent);
        $header = str_getcsv(array_shift($lines));
        $header = array_map('trim', $header);

        $expectedHeaders = ['Owner Name', 'Number', 'Type', 'Space in m2'];
        if ($header !== $expectedHeaders) {
            return new JsonResponse([
                'error' => 'Invalid CSV header. Expected: ' . implode(', ', $expectedHeaders),
            ], Response::HTTP_BAD_REQUEST);
        }

        $rows = [];
        $errors = [];
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = str_getcsv($line);
            if (count($fields) < 4) {
                $errors[] = "Line " . ($lineNum + 2) . ": insufficient columns";
                continue;
            }

            $ownerName = trim($fields[0]);
            $number = trim($fields[1]);
            $type = strtolower(trim($fields[2]));
            $area = trim($fields[3]);

            // Normalize type
            if ($type === 'appartment') {
                $type = 'apartment';
            }
            if (!in_array($type, ['apartment', 'parking'], true)) {
                $errors[] = "Line " . ($lineNum + 2) . ": invalid type '$type'";
                continue;
            }

            if (!is_numeric($area) || (float) $area <= 0) {
                $errors[] = "Line " . ($lineNum + 2) . ": invalid area '$area'";
                continue;
            }

            // Split name: first word → lastName, rest → firstName
            $parts = preg_split('/\s+/', $ownerName, 2);
            $lastName = $parts[0];
            $firstName = isset($parts[1]) ? $parts[1] : '-';

            $rows[] = [
                'lastName' => $lastName,
                'firstName' => $firstName,
                'number' => $number,
                'type' => $type,
                'area' => $area,
            ];
        }

        // Group by (number, type) to compute totalArea
        $unitGroups = [];
        foreach ($rows as $row) {
            $key = $row['number'] . '|' . $row['type'];
            if (!isset($unitGroups[$key])) {
                $unitGroups[$key] = [
                    'number' => $row['number'],
                    'type' => $row['type'],
                    'totalArea' => '0',
                    'residents' => [],
                ];
            }
            $unitGroups[$key]['totalArea'] = number_format((float) $unitGroups[$key]['totalArea'] + (float) $row['area'], 2, '.', '');
            $unitGroups[$key]['residents'][] = $row;
        }

        $apartmentRepo = $em->getRepository(Apartment::class);
        $residentRepo = $em->getRepository(Resident::class);

        $stats = [
            'apartments_created' => 0,
            'apartments_updated' => 0,
            'residents_created' => 0,
            'residents_updated' => 0,
        ];

        foreach ($unitGroups as $group) {
            // Find or create apartment
            $apartment = $apartmentRepo->findOneBy([
                'building' => $building,
                'number' => $group['number'],
                'type' => $group['type'],
            ]);

            if ($apartment) {
                if ($apartment->getTotalArea() !== $group['totalArea']) {
                    $apartment->setTotalArea($group['totalArea']);
                    $stats['apartments_updated']++;
                }
            } else {
                $apartment = new Apartment();
                $apartment->setBuilding($building);
                $apartment->setNumber($group['number']);
                $apartment->setType($group['type']);
                $apartment->setTotalArea($group['totalArea']);
                $em->persist($apartment);
                $stats['apartments_created']++;
            }

            // Create/update residents
            foreach ($group['residents'] as $residentData) {
                $resident = $residentRepo->findOneBy([
                    'apartment' => $apartment,
                    'firstName' => $residentData['firstName'],
                    'lastName' => $residentData['lastName'],
                ]);

                if ($resident) {
                    if ($resident->getOwnedArea() !== $residentData['area']) {
                        $resident->setOwnedArea($residentData['area']);
                        $stats['residents_updated']++;
                    }
                } else {
                    $resident = new Resident();
                    $resident->setApartment($apartment);
                    $resident->setFirstName($residentData['firstName']);
                    $resident->setLastName($residentData['lastName']);
                    $resident->setOwnedArea($residentData['area']);
                    $em->persist($resident);
                    $stats['residents_created']++;
                }
            }
        }

        $em->flush();

        return new JsonResponse(array_merge($stats, ['errors' => $errors]));
    }
}
