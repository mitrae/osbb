<?php

namespace App\Command;

use App\Entity\OrganizationMembership;
use App\Entity\Resident;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:backfill-resident-memberships', description: 'Create ROLE_RESIDENT memberships for users linked to residents who lack org membership')]
class BackfillResidentMembershipsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $residents = $this->em->getRepository(Resident::class)->createQueryBuilder('r')
            ->join('r.apartment', 'a')
            ->join('a.building', 'b')
            ->join('b.organization', 'o')
            ->where('r.user IS NOT NULL')
            ->getQuery()
            ->getResult();

        $created = 0;
        foreach ($residents as $resident) {
            $user = $resident->getUser();
            $organization = $resident->getApartment()->getBuilding()->getOrganization();

            $existing = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
                'user' => $user,
                'organization' => $organization,
            ]);

            if ($existing) {
                continue;
            }

            $membership = new OrganizationMembership();
            $membership->setUser($user);
            $membership->setOrganization($organization);
            $membership->setRole(OrganizationMembership::ROLE_RESIDENT);
            $this->em->persist($membership);
            $created++;

            $io->writeln(sprintf('  Created membership: user %d (%s) → org %d (%s)',
                $user->getId(), $user->getEmail(),
                $organization->getId(), $organization->getName()
            ));
        }

        $this->em->flush();

        $io->success(sprintf('Created %d new ROLE_RESIDENT memberships.', $created));

        return Command::SUCCESS;
    }
}
