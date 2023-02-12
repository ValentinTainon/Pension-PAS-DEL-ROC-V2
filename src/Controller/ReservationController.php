<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\UserRepository;
use App\Repository\AnimalRepository;
use App\Repository\ReservationRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Services\FileUploader;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'app_reservation_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(ReservationRepository $reservationRepository): Response
    {
        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, UserRepository $userRepository, AnimalRepository $animalRepository, ReservationRepository $reservationRepository, FileUploader $fileUploader): Response
    {
        $user = $this->getUser(); // Récupère et stocke l'utilisateur connecté.
        if (!$user) {
            $this->addFlash('Erreur', 'Vous devez avoir un compte et vous connecter pour réserver !');
            return $this->redirectToRoute('app_main');
        }

        $animal = new Animal();
        $reservation = new Reservation();

        $form = $this->createForm(ReservationType::class, ['user' => $user, 'animal' => $animal, 'reservation' => $reservation]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ordonnanceFile = $form['animal']->get('ordonnance')->getData();
            $traitement = $form['animal']->get('traitement')->getData();
            $dateCreation = (new \DateTime('now'))->format('d-m-Y H:i:s');
            $datedebut = $form->get('dateDebut')->getData();
            $dateFin = $form->get('dateFin')->getData();
            $prix = $form->get('prix')->getData();
            $status = "Demande en cours de traitement";

            if ($ordonnanceFile) {
                $ordonnanceFilename = $fileUploader->upload($ordonnanceFile);
                $animal->setOrdonnanceFile($ordonnanceFilename);
            }

            $userRepository->save($user, true);
            $animal->setUser($user)
                ->setTraitement($traitement);
            $animalRepository->save($animal, true);
            $reservation->setUser($user)
                ->setAnimal($animal)
                ->setDateCreation($dateCreation)
                ->setDateDebut($datedebut)
                ->setDateFin($dateFin)
                ->setPrix($prix)
                ->setStatus($status);
            $reservationRepository->save($reservation, true);

            $this->addFlash('Succès', 'Votre demande de réservation à bien été envoyé !');

            return $this->redirectToRoute('app_main', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(Reservation $reservation): Response
    {
        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(AnimalRepository $animalRepository, Request $request, Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ReservationType::class, $reservation);
        if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
            $form->remove('valider')->remove('refuser');
        }
        $form->handleRequest($request);

        if ($this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN') or $reservation->getUser() == $user) {
            if ($form->isSubmitted() && $form->isValid()) {
                // $animal->setOrdonnanceFile(new File($this->getParameter('ordonnances_directory').'/'.$animal->setOrdonnanceFile()));

                if ($form->get('submit')->isClicked()) {
                    $status = "Demande en cours de traitement";
                    $this->addFlash('Succès', 'Réservation modifiée !');
                } elseif ($form->get('annuler')->isClicked()) {
                    $status = "Réservation annulée";
                    $this->addFlash('Succès', 'Réservation Annulée !');
                } elseif ($form->get('valider')->isClicked()) {
                    $status = "Réservation validée";
                    $this->addFlash('Succès', 'Réservation Validée !');
                } elseif ($form->get('refuser')->isClicked()) {
                    $status = "Réservation refusée";
                    $this->addFlash('Succès', 'Réservation Refusée !');
                }

                $reservation->setStatus($status);
                $reservationRepository->save($reservation, true);

                return $this->redirectToRoute('app_mesReservations', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->renderForm('reservation/edit.html.twig', [
            'animals' => $animalRepository->findBy([
                'user' => $user,
            ]),
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reservation->getId(), $request->request->get('_token'))) {
            $reservationRepository->remove($reservation, true);
        }

        return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
    }
}
