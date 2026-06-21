<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
    ) {
        Log::info('OrganizationInvitationMail::__construct ejecutado', [
            'organizationName' => $this->organizationName,
            'administratorName' => $this->administratorName,
            'roleLabel' => $this->roleLabel,
            'invitationUrl' => $this->invitationUrl,
            'expiresAt' => $this->expiresAt,
            'accessDuration' => $this->accessDuration,
            'primaryColor' => $this->primaryColor,
            'secondaryColor' => $this->secondaryColor,
            'accentColor' => $this->accentColor,
        ]);
    }

    public function envelope(): Envelope
    {
        Log::info('OrganizationInvitationMail::envelope ejecutado', [
            'subject' => "Invitación a {$this->organizationName} · LoraTrack",
        ]);

        return new Envelope(
            subject: "Invitación a {$this->organizationName} · LoraTrack"
        );
    }

    public function content(): Content
    {
        Log::info('OrganizationInvitationMail::content iniciado');

        $primaryForeground = $this->contrastingForeground($this->primaryColor);
        $secondaryForeground = $this->contrastingForeground($this->secondaryColor);
        $accentForeground = $this->contrastingForeground($this->accentColor);

        Log::info('OrganizationInvitationMail::content colores calculados', [
            'primaryColor' => $this->primaryColor,
            'primaryForeground' => $primaryForeground,
            'secondaryColor' => $this->secondaryColor,
            'secondaryForeground' => $secondaryForeground,
            'accentColor' => $this->accentColor,
            'accentForeground' => $accentForeground,
        ]);

        return new Content(
            view: 'emails.organization-invitation',
            text: 'emails.organization-invitation-text',
            with: [
                'primaryForeground' => $primaryForeground,
                'secondaryForeground' => $secondaryForeground,
                'accentForeground' => $accentForeground,
            ],
        );
    }

    private function contrastingForeground(string $background): string
    {
        Log::debug('OrganizationInvitationMail::contrastingForeground iniciado', [
            'background' => $background,
        ]);

        $backgroundLuminance = $this->relativeLuminance($background);
        $lightContrast = 1.05 / ($backgroundLuminance + 0.05);

        $darkLuminance = $this->relativeLuminance('#0F172A');
        $darkContrast = ($backgroundLuminance + 0.05) / ($darkLuminance + 0.05);

        $foreground = $lightContrast >= $darkContrast ? '#FFFFFF' : '#0F172A';

        Log::debug('OrganizationInvitationMail::contrastingForeground resultado', [
            'background' => $background,
            'backgroundLuminance' => $backgroundLuminance,
            'lightContrast' => $lightContrast,
            'darkContrast' => $darkContrast,
            'foreground' => $foreground,
        ]);

        return $foreground;
    }

    private function relativeLuminance(string $hex): float
    {
        $originalHex = $hex;
        $hex = ltrim($hex, '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            Log::warning('OrganizationInvitationMail::relativeLuminance HEX inválido', [
                'originalHex' => $originalHex,
                'hexNormalizado' => $hex,
            ]);

            $hex = '000000';
        }

        $channels = array_map(
            static function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045
                    ? $value / 12.92
                    : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split($hex, 2),
        );

        $luminance = 0.2126 * $channels[0]
            + 0.7152 * $channels[1]
            + 0.0722 * $channels[2];

        Log::debug('OrganizationInvitationMail::relativeLuminance resultado', [
            'originalHex' => $originalHex,
            'hexUsado' => "#{$hex}",
            'channels' => $channels,
            'luminance' => $luminance,
        ]);

        return $luminance;
    }
}