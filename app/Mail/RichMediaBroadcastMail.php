<?php

namespace App\Mail;

use App\Models\BroadcastCampaign;
use App\Models\BroadcastRecipient;
use App\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RichMediaBroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public CompanySetting $company;

    public function __construct(
        public BroadcastCampaign $campaign,
        public BroadcastRecipient $recipient,
    ) {
        $this->company = CompanySetting::current();
    }

    public function envelope(): Envelope
    {
        $firstArticle = $this->campaign->articles->first();
        $subject = $firstArticle?->title ?: $this->campaign->name;

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.broadcast.rich-media',
            with: [
                'campaign' => $this->campaign,
                'recipient' => $this->recipient,
                'articles' => $this->campaign->articles,
                'company' => $this->company,
            ],
        );
    }
}
