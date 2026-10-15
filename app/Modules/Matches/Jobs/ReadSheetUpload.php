<?php

namespace App\Modules\Matches\Jobs;

use App\Models\User;
use App\Modules\Matches\Actions\StoreGameSheetUpload;
use App\Modules\Matches\Enums\SheetUploadStatus;
use App\Modules\Matches\Mail\SheetReadNotice;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Modules\Matches\Reading\SheetReaders;
use App\Modules\Matches\Reading\SheetReadFailed;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\CommunicationSettings;
use App\Modules\Tenancy\Support\OrganizationUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Lê a súmula enviada e guarda o que entendeu em `extracted` (aguarda revisão humana). Uma tentativa só: a leitura por IA custa. */
class ReadSheetUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public string $uploadId, public string $organizationId, public ?int $requestedBy = null) {}

    public function handle(): void
    {
        $this->inOrganization(function (): void {
            $upload = GameSheetUpload::query()->find($this->uploadId);

            if ($upload === null || $upload->status !== SheetUploadStatus::Reading) {
                return;
            }

            try {
                $contents = Storage::disk(StoreGameSheetUpload::DISK)->get($upload->path);

                if ($contents === null) {
                    throw new SheetReadFailed('O arquivo da súmula não foi encontrado.');
                }

                $upload->loadMissing(['game.homeEntry.team', 'game.awayEntry.team', 'game.division.season.competition']);
                $game = $upload->game;

                $sheet = SheetReaders::for($upload)->read($contents, $upload->mime, [
                    'sport' => $game->sport()->label(),
                    'home' => $game->homeEntry?->team->name,
                    'away' => $game->awayEntry?->team->name,
                ]);

                $upload->update(['status' => SheetUploadStatus::Read, 'extracted' => $sheet->toArray(), 'error' => null]);
                $this->notify($upload, true);
            } catch (SheetReadFailed $exception) {
                $upload->update(['status' => SheetUploadStatus::Failed, 'error' => $exception->getMessage()]);
                $this->notify($upload, false);
            } catch (Throwable $exception) {
                report($exception);
                $upload->update(['status' => SheetUploadStatus::Failed, 'error' => 'Não foi possível ler esta súmula. Tente novamente.']);
                $this->notify($upload, false);
            }
        });
    }

    /**
     * Avisa quem pediu a leitura (se a organização não desligou o aviso). Falha de e-mail nunca derruba nem desfaz a leitura.
     * O endereço da revisão é relativo e vira absoluto pela organização (a fila não tem requisição).
     */
    private function notify(GameSheetUpload $upload, bool $read): void
    {
        try {
            $organization = CurrentOrganization::get();
            $user = $this->requestedBy !== null ? User::query()->find($this->requestedBy) : null;

            if ($organization === null || $user === null || ! CommunicationSettings::enabled('sheet_read_finished', $organization)) {
                return;
            }

            $game = $upload->game;
            $label = trim(($game->homeEntry?->team->name ?? 'Mandante').' x '.($game->awayEntry?->team->name ?? 'Visitante'));

            Mail::to($user->email)->queue(new SheetReadNotice(
                $user->name,
                $read ? 'A leitura da súmula terminou' : 'A leitura da súmula não funcionou',
                $read ? "A súmula de {$label} foi lida e espera a sua revisão." : "Não foi possível ler a súmula de {$label}.",
                $read ? 'Confira o que foi lido antes de usar: nada altera o jogo sem a sua revisão.' : 'Abra a súmula para ver o motivo e tentar de novo ou enviar uma planilha.',
                OrganizationUrl::to($organization, route($read ? 'panel.games.uploads.review' : 'panel.games.uploads.show', $upload, false)),
                $read ? 'Revisar a leitura' : 'Abrir a súmula',
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** Estourou o tempo ou o processo caiu: não deixa a súmula "lendo" para sempre. */
    public function failed(Throwable $exception): void
    {
        $this->inOrganization(function (): void {
            GameSheetUpload::query()->whereKey($this->uploadId)->where('status', SheetUploadStatus::Reading->value)
                ->update(['status' => SheetUploadStatus::Failed->value, 'error' => 'A leitura demorou demais ou foi interrompida. Tente novamente.']);
        });
    }

    private function inOrganization(callable $callback): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization !== null) {
            CurrentOrganization::using($organization, $callback);
        }
    }
}
