<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInvitationMail extends Mailable implements ShouldQueue
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
                'primaryForeground' => $this->contrastingForeground($this->primaryColor),
                'secondaryForeground' => $this->contrastingForeground($this->secondaryColor),
                'accentForeground' => $this->contrastingForeground($this->accentColor),
            ],
        );
    }

    private function contrastingForeground(string $background): string
    {
        $backgroundLuminance = $this->relativeLuminance($background);
        $lightContrast = 1.05 / ($backgroundLuminance + 0.05);
        $darkLuminance = $this->relativeLuminance('#0F172A');
        $darkContrast = ($backgroundLuminance + 0.05) / ($darkLuminance + 0.05);

        return $lightContrast >= $darkContrast ? '#FFFFFF' : '#0F172A';
    }

    private function relativeLuminance(string $hex): float
    {
        $channels = array_map(
            static function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($hex, '#'), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
