<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\View\View;

class VerifyDocumentController extends Controller
{
    public function __invoke(string $document): View
    {
        $record = Document::query()
            ->whereKey($document)
            ->whereNot('status', DocumentStatus::Draft->value)
            ->with([
                'signers',
                'user',
                'auditLogs' => fn ($query) => $query->whereIn('event_type', [
                    AuditEvent::Created->value,
                    AuditEvent::Sent->value,
                    AuditEvent::Signed->value,
                    AuditEvent::Completed->value,
                    AuditEvent::Declined->value,
                    AuditEvent::Voided->value,
                ])->with('signer')->orderBy('created_at'),
            ])
            ->first();

        if ($record === null) {
            abort(response()->view('verify.not-found', [], 404));
        }

        return view('verify.show', ['document' => $record]);
    }
}
