<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\BrandPalette;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $organizationName,
        public readonly string $administratorName,
        public readonly string $roleLabel,
        public readonly string $invitationUrl,
        public readonly string $expiresAt,
        public readonly string $accessDuration,
        public readonly string $primaryColor,
        public readonly string $secondaryColor,
        public readonly string $accentColor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Invitación a {$this->organizationName} · LoraTrack");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.organization-invitation',
            text: 'emails.organization-invitation-text',
            with: [
                'primaryForeground' => BrandPalette::contrastingForeground($this->primaryColor),
                'secondaryForeground' => BrandPalette::contrastingForeground($this->secondaryColor),
                'accentForeground' => BrandPalette::contrastingForeground($this->accentColor),
            ],
        );
    }
}
