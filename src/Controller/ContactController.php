<?php

namespace App\Controller;

use App\Contact\ContactInput;
use App\Contact\ContactService;
use App\Contact\VCardBuilder;
use App\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly ContactService $contacts,
        private readonly VCardBuilder $vCardBuilder,
    ) {
    }

    #[Route('/contacts/new', name: 'contact_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $input = new ContactInput();
        $form = $this->createForm(ContactType::class, $input);
        // On POST: fills $input, checks CSRF and validates. On GET: nothing.
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact = $this->contacts->create($input);

            // Redirect after POST, so a page refresh does not submit the form again.
            return $this->redirectToRoute('contact_show', ['id' => $contact->getId()]);
        }

        // 200 on the first visit, 422 when the input was invalid.
        return $this->render('contact/new.html.twig', ['form' => $form], new Response(
            status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        ));
    }

    #[Route('/contacts', name: 'contact_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('contact/index.html.twig', [
            'result' => $this->contacts->list($request->query->getInt('page', 1)),
        ]);
    }

    #[Route('/contacts/{id}', name: 'contact_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $contact = $this->contacts->find($id) ?? throw new NotFoundHttpException('Contact not found.');

        return $this->render('contact/show.html.twig', [
            'contact' => $contact,
            'vcard' => $this->vCardBuilder->build($contact),
        ]);
    }


    #[Route('/contacts/{id}/qr.png', name: 'contact_qr', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function qr(int $id, Request $request): Response
    {
        $png = $this->contacts->findQrPng($id) ?? throw new NotFoundHttpException('Contact not found.');

        $response = new Response($png, Response::HTTP_OK, ['Content-Type' => 'image/png']);
        if ($request->query->getBoolean('download')) {
            $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                sprintf('contact-%d-qr.png', $id),
            ));
        }

        return $response;
    }
}
