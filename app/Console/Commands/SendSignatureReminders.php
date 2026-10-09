<?php

namespace App\Console\Commands;

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Signer;
use App\Services\DocumentWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('paraf:send-reminders')]
#[Description('Email signers who have not signed when their document expires in 3 or 1 day(s)')]
class SendSignatureReminders extends Command
{
    public function handle(DocumentWorkflow $workflow): int
    {
        $sent = 0;
        $days = config('paraf.reminder_days_before_expiry');
        $today = now()->startOfDay();

        Document::query()
            ->whereIn('status', DocumentStatus::inProgress())
            ->where('send_via_email', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<', $today->copy()->addDays(max($days) + 1))
            ->with('signers')
            ->chunkById(100, function ($documents) use ($workflow, $days, $today, &$sent) {
                foreach ($documents as $document) {
                    $daysLeft = (int) $today->diffInDays($document->expires_at->copy()->startOfDay());

                    if (! in_array($daysLeft, $days, true)) {
                        continue;
                    }

                    foreach ($document->activeSigners() as $signer) {
                        if ($this->alreadyReminded($document, $signer, $daysLeft)) {
                            continue;
                        }

                        try {
                            $workflow->remind($signer);
                            $sent++;
                        } catch (ValidationException) {
                            // No email address or not their turn: nothing to send.
                        }
                    }
                }
            });

        $this->components->info("Pengingat terkirim: {$sent}");

        return self::SUCCESS;
    }

    private function alreadyReminded(Document $document, Signer $signer, int $daysLeft): bool
    {
        return $signer->last_reminded_at !== null && $signer->last_reminded_at->isToday()
            || $document->auditLogs()
                ->where('signer_id', $signer->id)
                ->where('event_type', AuditEvent::ReminderSent)
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();
    }
}
