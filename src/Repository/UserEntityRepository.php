<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }
    public function findOrCreateByUniqueField(User $user): User
    {
        $entity = $this->findOneBy(['npub' => $user->getNpub()]);

        if (!$entity) {
            $this->_em->persist($user);
        }

        return $entity;
    }
}
