<?php

namespace Lalalili\EmailCampaign\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Lalalili\EmailCampaign\Data\RenderedEmail;

class CampaignMail extends Mailable
{
    public function __construct(private RenderedEmail $rendered) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rendered->subject);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->rendered->html ?? nl2br(e($this->rendered->text ?? '')),
        );
    }

    public function build(): static
    {
        if ($this->rendered->text !== null) {
            $this->text('email-campaign::mail.text', ['content' => $this->rendered->text]);
        }

        return $this;
    }
}
