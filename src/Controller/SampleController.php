<?php

namespace App\Controller;

use App\Contact\QrCodeRenderer;
use App\Contact\SampleContact;
use App\Contact\VCardBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Temporary until the contact form exists (M3): a QR code to scan with a real phone.
 */
final class SampleController
{
    public function __construct(
        private readonly VCardBuilder $vCardBuilder,
        private readonly QrCodeRenderer $qrCodeRenderer,
    ) {
    }

    #[Route('/sample/vcard', name: 'sample_vcard', methods: ['GET'])]
    public function vcard(): Response
    {
        return new Response(
            $this->vCardBuilder->build(SampleContact::create()),
            Response::HTTP_OK,
            ['Content-Type' => 'text/vcard; charset=utf-8'],
        );
    }

    #[Route('/sample/qr.png', name: 'sample_qr', methods: ['GET'])]
    public function qr(): Response
    {
        $vcard = $this->vCardBuilder->build(SampleContact::create());

        return new Response(
            $this->qrCodeRenderer->renderPng($vcard),
            Response::HTTP_OK,
            ['Content-Type' => 'image/png'],
        );
    }
}
