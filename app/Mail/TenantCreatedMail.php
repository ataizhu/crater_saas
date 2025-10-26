<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $loginUrl
    ) {
    }

    public function build()
    {
        return $this->subject('Your Crater Account is Ready')
            ->markdown('emails.tenant-created');
    }
}

