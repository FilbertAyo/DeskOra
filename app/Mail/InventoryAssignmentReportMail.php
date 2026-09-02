<?php

namespace App\Mail;

use App\Exports\InventoryAssetsExport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class InventoryAssignmentReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $assets,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your assigned inventory assets ('.$this->assets->count().')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.inventory.assignment-report',
            with: [
                'recipientName' => $this->user->name,
                'assetCount' => $this->assets->count(),
                'viewUrl' => route('inventory.my-items.index', ['tab' => 'assets']),
            ],
        );
    }

    public function attachments(): array
    {
        $csv = Excel::raw(new InventoryAssetsExport($this->assets), ExcelFormat::CSV);

        return [
            Attachment::fromData(fn () => $csv, 'my-assigned-assets.csv')
                ->withMime('text/csv'),
        ];
    }
}
