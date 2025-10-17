<?php

namespace App\Repository;

use App\Entity\Emprunt;
use App\Entity\Livre;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Emprunt>
 */
class EmpruntRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Emprunt::class);
    }

    public function hasActiveEmprunt(Livre $livre): bool
    {
        $result = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.livre = :livre')
            ->andWhere('e.dateRetour IS NULL')
            ->setParameter('livre', $livre)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }
    public function countActiveEmpruntsByUtilisateur(Utilisateur $utilisateur): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.utilisateur = :utilisateur')
            ->andWhere('e.dateRetour IS NULL')
            ->setParameter('utilisateur', $utilisateur)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findActiveEmpruntsByUtilisateur(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.utilisateur = :utilisateur')
            ->andWhere('e.dateRetour IS NULL')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('e.dateEmprunt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveEmpruntByLivre(Livre $livre): ?Emprunt
    {
        return $this->createQueryBuilder('e')
            ->where('e.livre = :livre')
            ->andWhere('e.dateRetour IS NULL')
            ->setParameter('livre', $livre)
            ->getQuery()
            ->getOneOrNullResult();
    }
}