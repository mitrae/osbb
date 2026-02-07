<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Resident;
use Doctrine\ORM\QueryBuilder;

final class RequestSearchFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($property !== 'search' || !$value) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $authorAlias = $queryNameGenerator->generateJoinAlias('author');
        $residentAlias = $queryNameGenerator->generateJoinAlias('resident');
        $apartmentAlias = $queryNameGenerator->generateJoinAlias('apartment');

        $queryBuilder
            ->leftJoin(sprintf('%s.author', $rootAlias), $authorAlias)
            ->leftJoin(Resident::class, $residentAlias, 'WITH', sprintf('%s.user = %s', $residentAlias, $authorAlias))
            ->leftJoin(sprintf('%s.apartment', $residentAlias), $apartmentAlias);

        // Escape LIKE wildcards
        $escaped = addcslashes($value, '%_');
        $paramName = $queryNameGenerator->generateParameterName('search');

        $queryBuilder
            ->andWhere(sprintf(
                '%s.title LIKE :%s OR %s.description LIKE :%s OR ' .
                '%s.firstName LIKE :%s OR %s.lastName LIKE :%s OR ' .
                '%s.email LIKE :%s OR %s.phone LIKE :%s OR ' .
                '%s.number LIKE :%s',
                $rootAlias, $paramName, $rootAlias, $paramName,
                $authorAlias, $paramName, $authorAlias, $paramName,
                $authorAlias, $paramName, $authorAlias, $paramName,
                $apartmentAlias, $paramName,
            ))
            ->setParameter($paramName, '%' . $escaped . '%');

        $queryBuilder->distinct();
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'search' => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => 'Search across title, description, author name/email/phone, and apartment number',
                'openapi' => [
                    'description' => 'Search across multiple fields',
                ],
            ],
        ];
    }
}
