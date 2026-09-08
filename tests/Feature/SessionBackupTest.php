<?php

namespace Tests\Feature;

use App\Models\SessionBackup;
use App\Models\WaSession;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SessionBackupTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private WaSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('session-backups');

        $this->workspace = Workspace::create(['name' => 'Toko Uji', 'slug' => 'toko-uji']);
        $this->session = $this->workspace->sessions()->create(['name' => 'CS', 'status' => 'connected']);
    }

    private function signedHeaders(string $path, string $content, ?string $digest = null): array
    {
        $timestamp = (string) now()->getTimestamp();
        $secret = config('gateway.engine.hmac_secret');

        $signed = $digest !== null
            ? $timestamp.'.'.$path.'.'.$digest
            : $timestamp.'.'.$content;

        $signature = hash_hmac('sha256', $signed, $secret);

        $headers = [
            'X-Engine-Timestamp' => $timestamp,
            'X-Engine-Signature' => $signature,
        ];

        if ($digest !== null) {
            $headers['X-Engine-Body-Sha256'] = $digest;
        }

        return $headers;
    }

    public function test_store_menyimpan_state_baileys_json_dan_checksum(): void
    {
        $jsonPayload = json_encode([
            'version' => 1,
            'sessionId' => $this->session->id,
            'files' => [
                'creds.json' => json_encode(['me' => '628123456789']),
                'app-state-sync-key-1.json' => json_encode(['keyData' => 'abc']),
            ],
        ]);

        $digest = hash('sha256', $jsonPayload);
        $path = "internal/engine/session-backup/{$this->session->id}";
        $headers = $this->signedHeaders($path, $jsonPayload, $digest);

        $response = $this->call(
            'POST',
            '/'.$path,
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers + [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
            $jsonPayload
        );

        $response->assertOk();
        $response->assertJson(['success' => true, 'data' => ['checksum' => $digest]]);

        // Verifikasi di storage
        Storage::disk('session-backups')->assertExists("{$this->session->id}/session.json");
        $saved = Storage::disk('session-backups')->get("{$this->session->id}/session.json");
        $this->assertSame($jsonPayload, $saved);

        // Verifikasi di database
        $backup = SessionBackup::where('wa_session_id', $this->session->id)->first();
        $this->assertNotNull($backup);
        $this->assertSame("{$this->session->id}/session.json", $backup->path);
        $this->assertSame($digest, $backup->checksum);
        $this->assertSame(strlen($jsonPayload), $backup->size);
    }

    public function test_store_menolak_payload_bukan_json(): void
    {
        $rawPayload = 'ini bukan json valid';
        $digest = hash('sha256', $rawPayload);
        $path = "internal/engine/session-backup/{$this->session->id}";
        $headers = $this->signedHeaders($path, $rawPayload, $digest);

        $response = $this->call(
            'POST',
            '/'.$path,
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers + [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
            $rawPayload
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error.message', 'Isi backup bukan JSON yang valid.');
    }

    public function test_store_menolak_checksum_yang_tidak_cocok(): void
    {
        $jsonPayload = json_encode(['test' => true]);
        $digest = str_repeat('a', 64);
        $path = "internal/engine/session-backup/{$this->session->id}";
        $headers = $this->signedHeaders($path, $jsonPayload, $digest);

        $response = $this->call(
            'POST',
            '/'.$path,
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers + [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
            $jsonPayload
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error.message', 'Isi backup tidak cocok dengan checksum yang ditandatangani.');
    }

    public function test_exists_menjawab_keberadaan_backup(): void
    {
        $path = "internal/engine/session-backup/{$this->session->id}/exists";
        $headers = $this->signedHeaders($path, '');

        $res1 = $this->call(
            'GET',
            '/'.$path,
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers + ['Accept' => 'application/json'])
        );
        $res1->assertOk()->assertJsonPath('data.exists', false);

        // Buat backup
        Storage::disk('session-backups')->put("{$this->session->id}/session.json", '{}');
        SessionBackup::create([
            'wa_session_id' => $this->session->id,
            'disk' => 'session-backups',
            'path' => "{$this->session->id}/session.json",
            'size' => 2,
            'checksum' => hash('sha256', '{}'),
            'backed_up_at' => now(),
        ]);

        $headers2 = $this->signedHeaders($path, '');
        $res2 = $this->call(
            'GET',
            '/'.$path,
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers2 + ['Accept' => 'application/json'])
        );
        $res2->assertOk()->assertJsonPath('data.exists', true);
    }

    public function test_show_mengunduh_session_json(): void
    {
        $content = json_encode(['auth' => 'valid']);
        Storage::disk('session-backups')->put("{$this->session->id}/session.json", $content);
        SessionBackup::create([
            'wa_session_id' => $this->session->id,
            'disk' => 'session-backups',
            'path' => "{$this->session->id}/session.json",
            'size' => strlen($content),
            'checksum' => hash('sha256', $content),
            'backed_up_at' => now(),
        ]);

        $path = "internal/engine/session-backup/{$this->session->id}";
        $headers = $this->signedHeaders($path, '');

        $response = $this->call(
            'GET',
            '/'.$path,
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers)
        );

        $response->assertOk();
        $response->assertHeader('X-Backup-Sha256', hash('sha256', $content));
    }

    public function test_destroy_menghapus_backup(): void
    {
        Storage::disk('session-backups')->put("{$this->session->id}/session.json", '{}');
        SessionBackup::create([
            'wa_session_id' => $this->session->id,
            'disk' => 'session-backups',
            'path' => "{$this->session->id}/session.json",
            'size' => 2,
            'checksum' => hash('sha256', '{}'),
            'backed_up_at' => now(),
        ]);

        $path = "internal/engine/session-backup/{$this->session->id}";
        $headers = $this->signedHeaders($path, '');

        $response = $this->call(
            'DELETE',
            '/'.$path,
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers)
        );

        $response->assertOk();
        Storage::disk('session-backups')->assertMissing("{$this->session->id}/session.json");
        $this->assertNull(SessionBackup::where('wa_session_id', $this->session->id)->first());
    }
}
