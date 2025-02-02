<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class UserEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, User::class);
    }
    public function findOrCreateByUniqueField(User $user): User
    {
        $entity = $this->findOneBy(['npub' => $user->getNpub()]);

        if ($entity !== null) {
            $user->setId($entity->getId());
        } else {
            $this->entityManager->persist($user);
        }

        return $user;
    }
}
