<?php

namespace App\Modules\Marketing\Actions;

use App\Modules\Marketing\Mail\LeadReceived;
use App\Modules\Marketing\Models\Lead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Guarda o contato e avisa a equipe (`platform.contact_email`). Falha de e-mail nunca perde o contato nem derruba o envio. */
class StoreLead
{
    /** @param  array<string, mixed>  $data */
    public function handle(array $data): Lead
    {
        $lead = Lead::query()->create([
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => mb_strtolower(trim((string) ($data['email'] ?? ''))),
            'organization_name' => trim((string) ($data['organization_name'] ?? '')) ?: null,
            'interest' => is_string($data['interest'] ?? null) ? $data['interest'] : null,
            'message' => trim((string) ($data['message'] ?? '')),
            'status' => 'new',
            'consent_at' => now(),
        ]);

        $to = config('platform.contact_email');

        if (is_string($to) && $to !== '') {
            try {
                Mail::to($to)->send(new LeadReceived($lead));
            } catch (Throwable $e) {
                Log::warning('Landing: não foi possível avisar a equipe do novo contato.', ['erro' => class_basename($e)]);
            }
        }

        return $lead;
    }
}
