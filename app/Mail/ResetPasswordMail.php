<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly string $token,
        public readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Atur ulang kata sandi — ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.reset-password', with: [
            'name' => $this->user->name,
            'url' => $this->resetUrl,
            'expiresIn' => 60,
        ]);
    }
}
