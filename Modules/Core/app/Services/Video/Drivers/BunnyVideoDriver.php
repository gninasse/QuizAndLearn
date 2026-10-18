<?php

namespace Modules\Core\Services\Video\Drivers;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;
use Modules\Core\Services\Video\PlaybackSource;
use Modules\Core\Services\Video\VideoDriver;
use RuntimeException;
use Throwable;

/**
 * Bunny Stream : transcodage et diffusion HLS gérés par Bunny.
 *
 * API Stream : https://docs.bunny.net/reference/video_createvideo (création),
 * video_uploadvideo (PUT binaire), video_getvideo (statut), video_deletevideo.
 * Statuts : 0 créée, 1 envoyée, 2 traitement, 3 transcodage, 4 terminée,
 * 5 erreur, 6 échec d'envoi.
 *
 * Lecture : authentification par jeton du CDN (Token Authentication avancée).
 * Doc : https://bunny.net/docs/cdn/security/token-authentication/advanced
 * Implémentation de référence : https://github.com/BunnyWay/BunnyCDN.TokenAuthentication (php/url_signing.php)
 *   token = "HS256-" . base64url_sans_remplissage( HMAC-SHA256(clé, signature_path . expires . [ip] . signing_data) )
 *   signing_data = paramètres triés « k=v » joints par « & » (hors token/expires), ici « token_path=/guid/ »
 *   signature_path = token_path (signature de répertoire : couvre playlist et segments)
 *   URL (mode répertoire) : https://host/bcdn_token={token}&token_path={encodé}&expires={ts}/{chemin}
 *
 * Aucune exception ni message n'inclut la clé d'API ou la clé de jeton.
 */
class BunnyVideoDriver implements VideoDriver
{
    private const API = 'https://video.bunnycdn.com';

    /** @param array{library_id?: ?string, api_key?: ?string, cdn_host?: ?string, token_key?: ?string} $config */
    public function __construct(private array $config = []) {}

    public function name(): string
    {
        return 'bunny';
    }

    public function store(UploadedFile $file): array
    {
        $title = $file->getClientOriginalName() ?: 'video';

        $created = $this->request()->post($this->videosUrl(), ['title' => $title]);
        $this->ensureOk($created, 'création de la vidéo');
        $guid = (string) $created->json('guid');
        if ($guid === '') {
            throw new RuntimeException('Bunny Stream : réponse de création invalide.');
        }

        $stream = fopen($file->getRealPath(), 'rb');
        try {
            $uploaded = $this->request(3600)
                ->withBody(Utils::streamFor($stream), 'application/octet-stream')
                ->put($this->videosUrl($guid));
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $this->ensureOk($uploaded, 'envoi de la vidéo');

        return ['location' => $guid, 'status' => 'processing', 'ref' => $guid];
    }

    public function playback(Video $video, ?User $viewer): PlaybackSource
    {
        $expires = now()->addSeconds((int) config('media.video.signed_ttl', 900));
        $ts = $expires->getTimestamp();
        $path = '/'.$video->ref.'/';
        $key = (string) ($this->config['token_key'] ?? '');
        $host = (string) ($this->config['cdn_host'] ?? '');
        if ($key === '' || $host === '') {
            throw new RuntimeException('Bunny Stream : hôte CDN ou clé de jeton non configurés.');
        }

        $digest = hash_hmac('sha256', $path.$ts.'token_path='.$path, $key, true);
        $token = 'HS256-'.rtrim(strtr(base64_encode($digest), '+/', '-_'), '=');
        $url = "https://{$host}/bcdn_token={$token}&token_path=".rawurlencode($path)."&expires={$ts}{$path}playlist.m3u8";

        return new PlaybackSource('hls', $url, $expires);
    }

    public function delete(Video $video): void
    {
        $response = $this->request()->delete($this->videosUrl($video->ref));
        if ($response->status() !== 404) {
            $this->ensureOk($response, 'suppression de la vidéo');
        }
    }

    public function refreshStatus(Video $video): string
    {
        try {
            $response = $this->request()->get($this->videosUrl($video->ref));
        } catch (Throwable) {
            return $video->status; // panne transitoire : on garde le statut
        }

        if ($response->status() === 404) {
            $status = 'failed';
        } elseif ($response->successful()) {
            $status = match ((int) $response->json('status')) {
                4 => 'ready',
                5, 6 => 'failed',
                default => 'processing',
            };
        } else {
            return $video->status;
        }

        if ($status !== $video->status) {
            $video->update(['status' => $status]);
        }

        return $status;
    }

    public function healthCheck(): array
    {
        foreach (['library_id', 'api_key', 'cdn_host', 'token_key'] as $key) {
            if (empty($this->config[$key])) {
                return ['ok' => false, 'message' => 'Configuration Bunny Stream incomplète.'];
            }
        }

        try {
            $response = $this->request(15)->get($this->videosUrl().'?page=1&itemsPerPage=1');
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'Bunny Stream injoignable.'];
        }

        return $response->successful()
            ? ['ok' => true, 'message' => 'Bunny Stream accessible.']
            : ['ok' => false, 'message' => 'Bunny Stream a répondu HTTP '.$response->status().'.'];
    }

    private function request(int $timeout = 60): PendingRequest
    {
        return Http::withHeaders(['AccessKey' => (string) ($this->config['api_key'] ?? '')])
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout(10);
    }

    private function videosUrl(?string $guid = null): string
    {
        $url = self::API.'/library/'.rawurlencode((string) ($this->config['library_id'] ?? '')).'/videos';

        return $guid ? $url.'/'.rawurlencode($guid) : $url;
    }

    private function ensureOk(Response $response, string $action): void
    {
        if (! $response->successful()) {
            // Jamais de corps de réponse ni d'en-têtes : ils pourraient refléter la clé.
            throw new RuntimeException("Bunny Stream : échec de la {$action} (HTTP {$response->status()}).");
        }
    }
}
