<?php

namespace App\Controller;

use App\Entity\Emprunt;
use App\Repository\EmpruntRepository;
use App\Repository\LivreRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/emprunt')]
final class EmpruntController extends AbstractController
{
    #[Route('/emprunter', name: 'emprunt', methods: ['POST'])]
    public function emprunt(
        Request $request,
        EntityManagerInterface $em,
        UtilisateurRepository $utilisateurRepo,
        LivreRepository $livreRepo,
        EmpruntRepository $empruntRepo,
        LoggerInterface $logger
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['utilisateur_id']) || !isset($data['livre_id'])) {
                return $this->json([
                    'error' => 'Les champs utilisateur_id et livre_id sont requis'
                ], Response::HTTP_BAD_REQUEST);
            }

            $utilisateur = $utilisateurRepo->find((int)$data['utilisateur_id']);
            if (!$utilisateur) {
                return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
            }

            $livre = $livreRepo->find((int)$data['livre_id']);
            if (!$livre) {
                return $this->json(['error' => 'Livre introuvable'], Response::HTTP_NOT_FOUND);
            }

            if (!$livre->isDisponible()) {
                return $this->json([
                    'error' => 'Ce livre n\'est pas disponible'
                ], Response::HTTP_CONFLICT);
            }

            if ($empruntRepo->hasActiveEmprunt($livre)) {
                return $this->json([
                    'error' => 'Ce livre est déjà emprunté par un autre utilisateur'
                ], Response::HTTP_CONFLICT);
            }

            $empruntsEnCours = $empruntRepo->countActiveEmpruntsByUtilisateur($utilisateur);
            if ($empruntsEnCours >= 4) {
                return $this->json([
                    'error' => 'Vous avez atteint la limite de 4 emprunts simultanés'
                ], Response::HTTP_CONFLICT);
            }

            $emprunt = new Emprunt();
            $emprunt->setUtilisateur($utilisateur);
            $emprunt->setLivre($livre);
            $emprunt->setDateEmprunt(new \DateTime());

            $livre->setDisponible(false);

            $em->persist($emprunt);
            $em->flush();

            $logger->info('Emprunt créé', [
                'emprunt_id' => $emprunt->getId(),
                'utilisateur_id' => $utilisateur->getId(),
                'livre_id' => $livre->getId()
            ]);

            return $this->json([
                'message' => 'Emprunt créé avec succès',
                'emprunt' => [
                    'id' => $emprunt->getId(),
                    'dateEmprunt' => $emprunt->getDateEmprunt()->format('Y-m-d'),
                    'utilisateur' => [
                        'id' => $utilisateur->getId(),
                        'nom' => $utilisateur->getNom(),
                        'prenom' => $utilisateur->getPrenom()
                    ],
                    'livre' => [
                        'id' => $livre->getId(),
                        'titre' => $livre->getTitre()
                    ]
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $logger->error('Erreur création emprunt', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Une erreur est survenue'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/rendre/{id}', name: 'emprunt_retour', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function rendre(
        int $id,
        EntityManagerInterface $em,
        EmpruntRepository $empruntRepo,
        LoggerInterface $logger
    ): JsonResponse {
        try {
            $emprunt = $empruntRepo->find($id);

            if (!$emprunt) {
                return $this->json(['error' => 'Emprunt introuvable'], Response::HTTP_NOT_FOUND);
            }

            if ($emprunt->getDateRetour() !== null) {
                $logger->info('Tentative de retour d\'un livre déjà rendu', [
                    'emprunt_id' => $id,
                    'date_retour' => $emprunt->getDateRetour()->format('Y-m-d')
                ]);
                return $this->json([
                    'error' => 'Ce livre a déjà été retourné',
                    'date_retour' => $emprunt->getDateRetour()->format('Y-m-d')
                ], Response::HTTP_CONFLICT);
            }

            $emprunt->setDateRetour(new \DateTime());

            $livre = $emprunt->getLivre();
            $livre->setDisponible(true);

            $em->flush();

            $logger->info('Livre retourné avec succès', [
                'emprunt_id' => $emprunt->getId(),
                'livre_id' => $livre->getId(),
                'utilisateur_id' => $emprunt->getUtilisateur()->getId(),
                'date_retour' => $emprunt->getDateRetour()->format('Y-m-d')
            ]);

            return $this->json([
                'message' => 'Livre retourné avec succès',
                'emprunt' => [
                    'id' => $emprunt->getId(),
                    'dateEmprunt' => $emprunt->getDateEmprunt()->format('Y-m-d'),
                    'dateRetour' => $emprunt->getDateRetour()->format('Y-m-d'),
                    'utilisateur' => [
                        'id' => $emprunt->getUtilisateur()->getId(),
                        'nom' => $emprunt->getUtilisateur()->getNom(),
                        'prenom' => $emprunt->getUtilisateur()->getPrenom()
                    ],
                    'livre' => [
                        'id' => $livre->getId(),
                        'titre' => $livre->getTitre(),
                        'disponible' => $livre->isDisponible()
                    ]
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            $logger->error('Erreur retour emprunt', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Une erreur est survenue lors du retour du livre'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}