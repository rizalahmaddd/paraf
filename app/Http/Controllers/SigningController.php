<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
use App\Models\DocumentField;
use App\Models\DocumentPage;
use App\Models\Signer;
use App\Services\DocumentStorage;
use App\Services\SigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SigningController extends Controller
{
    public function __construct(private SigningService $signing, private DocumentStorage $storage) {}

    public function show(Request $request, string $token): View
    {
        $signer = $this->resolve($token);
        $document = $signer->document;

        if ($state = $this->closedState($signer)) {
            return view('signing.status', ['signer' => $signer, 'document' => $document, 'state' => $state, 'token' => $token]);
        }

        if (! $this->signing->hasVerifiedPasscode($signer, $request)) {
            return view('signing.passcode', [
                'signer' => $signer,
                'document' => $document,
                'token' => $token,
                'lockSeconds' => $this->signing->passcodeLockSeconds($signer),
            ]);
        }

        if (! $this->signing->isTurnOf($signer)) {
            return view('signing.waiting', [
                'signer' => $signer,
                'document' => $document,
                'token' => $token,
                'currentSigners' => $document->activeSigners(),
            ]);
        }

        $this->signing->markViewed($signer);

        $document->load(['pages', 'fields', 'signers']);
        $signersById = $document->signers->keyBy('id');

        // Never match by signer email alone: the owner types that email, so they could pull
        // another account's saved specimen through a link they control.
        $matchedUser = match (true) {
            $signer->is_owner => $document->user,
            auth()->check() && strcasecmp((string) auth()->user()->email, (string) $signer->email) === 0 => auth()->user(),
            default => null,
        };

        $savedSignature = $matchedUser?->savedSignatureBase64();
        $savedInitial = $matchedUser?->savedInitialBase64();

        return view('signing.sign', [
            'signer' => $signer,
            'document' => $document,
            'token' => $token,
            'config' => [
                'token' => $token,
                'pdfUrl' => route('sign.pdf', $token),
                'submitUrl' => route('sign.submit', $token),
                'statusUrl' => route('sign.status', $token),
                'declineUrl' => route('sign.decline', $token),
                'returnUrl' => route('sign.show', $token),
                'signerId' => $signer->id,
                'signerName' => $signer->name,
                'today' => now()->timezone('Asia/Jakarta')->locale('id')->isoFormat('D MMMM YYYY'),
                'savedSignature' => $savedSignature,
                'savedInitial' => $savedInitial,
                'pages' => $document->pages->map(fn (DocumentPage $page) => ['number' => $page->page_number] + $page->displaySize())->values(),
                'fields' => $document->fields->map(fn (DocumentField $field) => $field->toCanvasArray() + [
                    'mine' => $field->signer_id === $signer->id,
                    'color' => $signersById[$field->signer_id]?->color_tag,
                    'owner_name' => $signersById[$field->signer_id]?->name,
                    'done' => $signersById[$field->signer_id]?->status === SignerStatus::Signed,
                ])->values(),
            ],
        ]);
    }

    public function passcode(Request $request, string $token): RedirectResponse
    {
        $signer = $this->resolve($token);

        $data = $request->validate(['passcode' => ['required', 'digits:6']], [
            'passcode.required' => 'Masukkan passcode 6 digit.',
            'passcode.digits' => 'Passcode terdiri dari 6 angka.',
        ]);

        if (! $this->closedState($signer)) {
            $this->signing->verifyPasscode($signer, $data['passcode'], $request);
        }

        return redirect()->route('sign.show', $token);
    }

    public function pdf(Request $request, string $token): Response
    {
        $signer = $this->resolve($token);

        abort_if($this->closedState($signer) !== null, 410);
        abort_unless($this->signing->hasVerifiedPasscode($signer, $request), 403);
        abort_unless($this->signing->isTurnOf($signer), 403);

        return response($this->storage->get($signer->document->original_pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function status(Request $request, string $token): JsonResponse
    {
        $signer = $this->resolve($token);

        return response()->json([
            'document_status' => $signer->document->status->value,
            'signer_status' => $signer->status->value,
            'is_turn' => $this->signing->isTurnOf($signer),
            'closed' => $this->closedState($signer) !== null,
        ]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $signer = $this->resolve($token);
        abort_unless($this->signing->hasVerifiedPasscode($signer, $request), 403, 'Verifikasi passcode terlebih dahulu.');

        // Retried submit after a dropped connection: the first one already went through.
        if ($signer->status === SignerStatus::Signed) {
            return response()->json(['ok' => true, 'redirect' => route('sign.show', $token)]);
        }

        $data = $request->validate([
            'fields' => ['present', 'array'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Centang pernyataan persetujuan sebelum mengirim.',
        ]);

        $this->signing->sign($signer, $data['fields'], $request);

        return response()->json(['ok' => true, 'redirect' => route('sign.show', $token)]);
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $signer = $this->resolve($token);
        abort_unless($this->signing->hasVerifiedPasscode($signer, $request), 403, 'Verifikasi passcode terlebih dahulu.');

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], [
            'reason.required' => 'Tuliskan alasan penolakan.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $this->signing->decline($signer, $data['reason']);

        return response()->json(['ok' => true, 'redirect' => route('sign.show', $token)]);
    }

    public function download(Request $request, string $token): RedirectResponse
    {
        $signer = $this->resolve($token);
        $document = $signer->document;

        abort_unless($document->status === DocumentStatus::Completed, 404);
        abort_unless($this->signing->hasVerifiedPasscode($signer, $request), 403);

        return redirect()->away(DocumentFileController::temporaryDownloadUrl($document));
    }

    private function resolve(string $token): Signer
    {
        $signer = Signer::findByToken($token);

        if ($signer === null) {
            abort(response()->view('signing.invalid', [], 404));
        }

        return $signer->load('document.user');
    }

    /**
     * Why this link cannot be used to sign right now, or null when it can.
     */
    private function closedState(Signer $signer): ?string
    {
        $document = $signer->document;

        return match (true) {
            $document->status === DocumentStatus::Completed => 'completed',
            $document->status === DocumentStatus::Voided => 'voided',
            $document->status === DocumentStatus::Declined => 'declined',
            $document->status === DocumentStatus::Expired => 'expired',
            $signer->status === SignerStatus::Signed => 'signed',
            $document->status === DocumentStatus::Draft => 'invalid',
            (bool) $document->expires_at?->isPast() => 'expired',
            default => null,
        };
    }
}
