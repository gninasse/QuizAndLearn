<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Models\Resource;
use RuntimeException;
use Throwable;

/**
 * Convertit un document bureautique (Word, PowerPoint, OpenDocument) en PDF
 * avec LibreOffice (soffice --headless), sur la file dédiée `documents`.
 */
class ConvertDocumentToPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const MISSING_BINARY = "LibreOffice n'est pas installé sur le serveur.";

    public int $tries = 2;

    public int $timeout = 300;

    /** Délai de soffice, inférieur à celui du job : le job peut nettoyer après un dépassement. */
    private const PROCESS_TIMEOUT = 270;

    public function __construct(public int $resourceId)
    {
        $this->onQueue((string) config('media.documents.queue', 'documents'));
    }

    /**
     * Une seule conversion à la fois par ressource (bouton « Réessayer » pendant
     * qu'un essai tourne encore) : elles partagent le même répertoire temporaire.
     * Un doublon est abandonné, l'essai en cours fixe le statut final.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->resourceId))
                ->dontRelease()
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(): void
    {
        $resource = Resource::find($this->resourceId);
        if (! $resource || $resource->kind !== 'document' || ! $resource->path) {
            return;
        }

        $disk = Storage::disk($resource->disk ?: 'local');
        $tmp = self::tmpDir($resource->id);
        $files = new Filesystem;

        try {
            $files->ensureDirectoryExists("{$tmp}/out");
            $extension = pathinfo($resource->path, PATHINFO_EXTENSION) ?: 'docx';
            $input = "{$tmp}/original.{$extension}";

            $in = $disk->readStream($resource->path);
            if (! is_resource($in)) {
                throw new RuntimeException("Original introuvable : {$resource->path}");
            }
            file_put_contents($input, $in);
            fclose($in);

            $result = Process::timeout(self::PROCESS_TIMEOUT)->run([
                (string) config('media.documents.soffice_binary', 'soffice'),
                // Profil LibreOffice propre au job : pas de verrou partagé entre workers.
                '-env:UserInstallation=file://'.$tmp.'/profile',
                '--headless',
                '--convert-to', 'pdf',
                '--outdir', "{$tmp}/out",
                $input,
            ]);

            // 127 : commande introuvable (binaire absent ou chemin erroné) ;
            // 126 : fichier présent mais non exécutable.
            // Inutile de réessayer : échec immédiat et explicite.
            if (in_array($result->exitCode(), [126, 127], true)) {
                Log::error('Conversion PDF impossible : LibreOffice absent', ['resource' => $resource->id]);
                $this->markFailed(self::MISSING_BINARY);

                return;
            }
            if (! $result->successful()) {
                throw new RuntimeException('La conversion du document a échoué : '
                    .Str::limit(trim($result->errorOutput() ?: $result->output()), 500));
            }

            $pdf = "{$tmp}/out/original.pdf";
            if (! is_file($pdf)) {
                throw new RuntimeException("La conversion du document n'a produit aucun PDF.");
            }

            $pdfPath = dirname($resource->path).'/'.pathinfo($resource->path, PATHINFO_FILENAME).'.pdf';
            $stream = fopen($pdf, 'r');
            $disk->writeStream($pdfPath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $resource->update([
                'pdf_path' => $pdfPath,
                'conversion_status' => 'ready',
                'conversion_error' => null,
            ]);
        } finally {
            $files->deleteDirectory($tmp);
        }
    }

    /**
     * Appelé par le worker après le dernier essai (exception, délai dépassé,
     * processus tué) : statut « failed », message conservé pour le back-office.
     */
    public function failed(?Throwable $e = null): void
    {
        Log::error('Conversion PDF échouée', ['resource' => $this->resourceId, 'error' => $e?->getMessage()]);
        (new Filesystem)->deleteDirectory(self::tmpDir($this->resourceId));
        $this->markFailed(Str::limit($e?->getMessage() ?: 'Conversion interrompue.', 1000));
    }

    public static function tmpDir(int $resourceId): string
    {
        return storage_path("app/tmp/soffice-{$resourceId}");
    }

    private function markFailed(string $error): void
    {
        Resource::whereKey($this->resourceId)->update([
            'conversion_status' => 'failed',
            'conversion_error' => $error,
        ]);
    }
}
