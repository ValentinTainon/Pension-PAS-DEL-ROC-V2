<?php

namespace App\Controller;

use App\Form\ContactType;
use App\Services\MailerService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    /**
     * @Route("/contact", name="contact")
     */
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerService $mailer)
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $contactFormData = $form->getData();
            $from = $contactFormData['email'];
            $subject = 'Demande de contact sur votre site de ' . $contactFormData['email'];
            $content = $contactFormData['name'] . ' vous a envoyé le message suivant: ' . $contactFormData['message'];
            // dd($contactFormData);
            $mailer->sendEmail(from: $from, subject: $subject, content: $content);
            $this->addFlash('Succès', 'Votre message à été envoyé !');
            return $this->redirectToRoute('app_contact');
        }
        return $this->render('contact/contact.html.twig', [
            'form' => $form->createView()
        ]);
    }
}