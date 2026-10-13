<?php

namespace App\Modules\Matches\Jobs;

use App\Modules\Matches\Actions\StoreGameSheetUpload;
use App\Modules\Matches\Enums\SheetUploadStatus;
use App\Modules\Matches\Models\GameSheetUpload;
use App\Modules\Matches\Reading\SheetReaders;
use App\Modules\Matches\Reading\SheetReadFailed;
use App\Modules\Tenancy\CurrentOrganization;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Lê a súmula enviada e guarda o que entendeu em `extracted` (aguarda revisão humana). Uma tentativa só: a leitura por IA custa. */
class ReadSheetUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public string $uploadId, public string $organizationId) {}

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
            } catch (SheetReadFailed $exception) {
                $upload->update(['status' => SheetUploadStatus::Failed, 'error' => $exception->getMessage()]);
            } catch (Throwable $exception) {
                report($exception);
                $upload->update(['status' => SheetUploadStatus::Failed, 'error' => 'Não foi possível ler esta súmula. Tente novamente.']);
            }
        });
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
