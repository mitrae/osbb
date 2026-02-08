<?php

namespace App\Controller;

use App\Entity\Resident;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OrganizationTotalAreaController extends AbstractController
{
    #[Route('/api/organizations/{id}/total-area', name: 'api_org_total_area', methods: ['GET'])]
    public function __invoke(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $propertyType = $request->query->get('propertyType');

        $qb = $em->createQueryBuilder()
            ->select('SUM(r.ownedArea)')
            ->from(Resident::class, 'r')
            ->join('r.apartment', 'a')
            ->join('a.building', 'b')
            ->where('b.organization = :org')
            ->setParameter('org', $id);

        if ($propertyType) {
            $qb->andWhere('a.type = :type')
                ->setParameter('type', $propertyType);
        }

        $totalArea = $qb->getQuery()->getSingleScalarResult() ?? '0.00';

        return new JsonResponse(['totalArea' => $totalArea]);
    }
}
