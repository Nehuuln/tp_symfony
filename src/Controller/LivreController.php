<?php

namespace App\Controller;

use App\Entity\Livre;
use App\Entity\Auteur;
use App\Entity\Categorie;
use App\Repository\LivreRepository;
use Psr\Log\LoggerInterface;
use App\Repository\AuteurRepository;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/livre')]
final class LivreController extends AbstractController
{
    #[Route('/create', name: 'livre_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        AuteurRepository $auteurRepo,
        CategorieRepository $categorieRepo,
        LoggerInterface $logger
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            $requiredFields = ['titre', 'datePublication', 'auteur_id', 'categorie_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->json(['error' => "Le champ '$field' est requis"], Response::HTTP_BAD_REQUEST);
                }
            }

            $date = \DateTime::createFromFormat('Y-m-d', $data['datePublication']);
            if (!$date) {
                return $this->json(['error' => "Le format de 'datePublication' est invalide, attendu YYYY-MM-DD"], Response::HTTP_BAD_REQUEST);
            }

            $auteur = $auteurRepo->find((int) $data['auteur_id']);
            if (!$auteur) {
                return $this->json(['error' => 'Auteur non trouvé'], Response::HTTP_BAD_REQUEST);
            }

            $categorie = $categorieRepo->find((int) $data['categorie_id']);
            if (!$categorie) {
                return $this->json(['error' => 'Catégorie non trouvée'], Response::HTTP_BAD_REQUEST);
            }

            $livre = new Livre();
            $livre->setTitre($data['titre']);
            $livre->setDatePublication($date);
            $livre->setDisponible($data['disponible'] ?? true);
            $livre->setAuteur($auteur);
            $livre->setCategorie($categorie);

            $em->persist($livre);
            $em->flush();

            $logger->info('Livre crée', ['livre_id' => $livre->getId()]);

            return $this->json([
                'message' => 'Livre created successfully',
                'livre' => [
                    'id' => $livre->getId(),
                    'titre' => $livre->getTitre(),
                    'datePublication' => $livre->getDatePublication()->format('Y-m-d'),
                    'disponible' => $livre->isDisponible(),
                    'auteur_id' => $auteur->getId(),
                    'categorie_id' => $categorie->getId()
                ]
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $logger->error('Erreur création livre', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Une erreur est survenue'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/list', name: 'livre_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        try {
            $livres = $em->getRepository(Livre::class)->findAll();
            $data = [];
            foreach ($livres as $livre) {
                $data = [
                    'id' => $livre->getId(),
                    'titre' => $livre->getTitre(),
                    'datePublication' => $livre->getDatePublication()->format('Y-m-d'),
                    'disponible' => $livre->isDisponible(),
                    'auteur' => [
                        'id' => $livre->getAuteur()->getId(),
                        'nom' => $livre->getAuteur()->getNom(),
                        'prenom' => $livre->getAuteur()->getPrenom()
                    ],
                    'categorie' => [
                        'id' => $livre->getCategorie()->getId(),
                        'nom' => $livre->getCategorie()->getNom()
                    ],
                ];
            }
            return new JsonResponse($data, Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/update/{id}', name: 'livre_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        LivreRepository $livreRepo,
        AuteurRepository $auteurRepo,
        CategorieRepository $categorieRepo,
        LoggerInterface $logger
    ): JsonResponse {
        try {
            $livre = $livreRepo->find($id);

            if (!$livre) {
                return $this->json(['error' => 'Livre introuvable'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            if (isset($data['titre'])) {
                $livre->setTitre($data['titre']);
            }

            if (isset($data['date_publication'])) {
                $date = \DateTime::createFromFormat('Y-m-d', $data['date_publication']);
                if (!$date) {
                    return $this->json(['error' => 'Format de date invalide (attendu: YYYY-MM-DD)'], Response::HTTP_BAD_REQUEST);
                }
                $livre->setDatePublication($date);
            }

            if (isset($data['disponible'])) {
                $livre->setDisponible((bool)$data['disponible']);
            }

            if (isset($data['auteur_id'])) {
                $auteur = $auteurRepo->find((int)$data['auteur_id']);
                if (!$auteur) {
                    return $this->json(['error' => 'Auteur introuvable'], Response::HTTP_NOT_FOUND);
                }
                $livre->setAuteur($auteur);
            }

            if (isset($data['categorie_id'])) {
                $categorie = $categorieRepo->find((int)$data['categorie_id']);
                if (!$categorie) {
                    return $this->json(['error' => 'Catégorie introuvable'], Response::HTTP_NOT_FOUND);
                }
                $livre->setCategorie($categorie);
            }

            $em->flush();

            $logger->info('Livre mis à jour', ['livre_id' => $livre->getId()]);

            return $this->json([
                'message' => 'Livre updated successfully',
                'livre' => [
                    'id' => $livre->getId(),
                    'titre' => $livre->getTitre(),
                    'date_publication' => $livre->getDatePublication()?->format('Y-m-d'),
                    'disponible' => $livre->isDisponible(),
                    'auteur_id' => $livre->getAuteur()?->getId(),
                    'categorie_id' => $livre->getCategorie()?->getId()
                ]
            ]);

        } catch (\Exception $e) {
            $logger->error('Erreur mise à jour livre', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Une erreur est survenue'], Response::HTTP_BAD_REQUEST);
        }
    }

        #[Route('/delete/{id}', name: 'livre_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(
        int $id,
        EntityManagerInterface $em,
        LivreRepository $livreRepo,
        LoggerInterface $logger
    ): JsonResponse {
        try {
            $livre = $livreRepo->find($id);

            if (!$livre) {
                return $this->json(['error' => 'Livre introuvable'], Response::HTTP_NOT_FOUND);
            }

            $hasActiveLoans = false;
            foreach ($livre->getEmprunts() as $emprunt) {
                if ($emprunt->getDateRetour() === null) {
                    $hasActiveLoans = true;
                    break;
                }
            }

            if ($hasActiveLoans) {
                return $this->json([
                    'error' => 'Impossible de supprimer un livre actuellement emprunté'
                ], Response::HTTP_CONFLICT);
            }

            $em->remove($livre);
            $em->flush();

            $logger->info('Livre supprimé', ['livre_id' => $id]);

            return $this->json(['message' => 'Livre deleted successfully'], Response::HTTP_OK);

        } catch (\Exception $e) {
            $logger->error('Erreur suppression livre', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Une erreur est survenue'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}