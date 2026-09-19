<?php

namespace Tests\Feature;

use App\Models\SessionBackup;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\Providers\WwebjsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

    public function test_wwebjs_provider_mengabaikan_backup_lama_berformat_zip_dan_mulai_bersih(): void
    {
        // Simulasi berkas biner zip era Chromium
        $zipBinary = "PK\x03\x04".random_bytes(64);
        Storage::disk('session-backups')->put("{$this->session->id}/session.zip", $zipBinary);

        SessionBackup::create([
            'wa_session_id' => $this->session->id,
            'disk' => 'session-backups',
            'path' => "{$this->session->id}/session.zip",
            'size' => strlen($zipBinary),
            'checksum' => hash('sha256', $zipBinary),
            'backed_up_at' => now(),
        ]);

        Http::fake([
            '*/sessions/*/start' => function (Request $request) {
                $data = $request->data();
                $this->assertFalse($data['has_backup']);
                $this->assertArrayNotHasKey('backup_data', $data);

                return Http::response(['status' => 'connecting'], 200);
            },
        ]);

        app(WwebjsProvider::class)->startSession($this->session);

        Http::assertSent(fn (Request $req) => $req->data()['has_backup'] === false && ! isset($req->data()['backup_data'])
        );
    }

    public function test_wwebjs_provider_mengabaikan_backup_yang_bukan_json_atau_bukan_utf8(): void
    {
        // Berkas path session.json tetapi isinya bukan JSON valid
        $invalidJson = 'bukan-json-valid-{'.random_bytes(10);
        Storage::disk('session-backups')->put("{$this->session->id}/session.json", $invalidJson);

        SessionBackup::create([
            'wa_session_id' => $this->session->id,
            'disk' => 'session-backups',
            'path' => "{$this->session->id}/session.json",
            'size' => strlen($invalidJson),
            'checksum' => hash('sha256', $invalidJson),
            'backed_up_at' => now(),
        ]);

        Http::fake([
            '*/sessions/*/start' => function (Request $request) {
                $data = $request->data();
                $this->assertFalse($data['has_backup']);
                $this->assertArrayNotHasKey('backup_data', $data);

                return Http::response(['status' => 'connecting'], 200);
            },
        ]);

        app(WwebjsProvider::class)->startSession($this->session);

        Http::assertSent(fn (Request $req) => $req->data()['has_backup'] === false && ! isset($req->data()['backup_data'])
        );
    }

    public function test_wwebjs_provider_menyertakan_backup_yang_formatnya_valid(): void
    {
        $validJson = json_encode(['version' => 1, 'files' => ['creds.json' => '{}']]);
        Storage::disk('session-backups')->put("{$this->session->id}/session.json", $validJson);

        SessionBackup::create([
            'wa_session_id' => $this->session->id,
            'disk' => 'session-backups',
            'path' => "{$this->session->id}/session.json",
            'size' => strlen($validJson),
            'checksum' => hash('sha256', $validJson),
            'backed_up_at' => now(),
        ]);

        Http::fake([
            '*/sessions/*/start' => function (Request $request) use ($validJson) {
                $data = $request->data();
                $this->assertTrue($data['has_backup']);
                $this->assertSame($validJson, $data['backup_data']);

                return Http::response(['status' => 'connecting'], 200);
            },
        ]);

        app(WwebjsProvider::class)->startSession($this->session);

        Http::assertSent(fn (Request $req) => $req->data()['has_backup'] === true && $req->data()['backup_data'] === $validJson
        );
    }

    public function test_perintah_bersihkan_backup_lama_mode_kering_tidak_menghapus_apa_pun(): void
    {
        // Backup lama .zip
        Storage::disk('session-backups')->put("{$this->session->id}/session.zip", 'dummy-zip');
        $oldBackup = SessionBackup::create([
            'wa_session_id' => $this->session->id,
            'disk' => 'session-backups',
            'path' => "{$this->session->id}/session.zip",
            'size' => 9,
            'checksum' => hash('sha256', 'dummy-zip'),
            'backed_up_at' => now()->subDays(10),
        ]);

        // Backup baru .json
        $newSession = $this->workspace->sessions()->create(['name' => 'CS2', 'status' => 'connected']);
        Storage::disk('session-backups')->put("{$newSession->id}/session.json", '{}');
        $validBackup = SessionBackup::create([
            'wa_session_id' => $newSession->id,
            'disk' => 'session-backups',
            'path' => "{$newSession->id}/session.json",
            'size' => 2,
            'checksum' => hash('sha256', '{}'),
            'backed_up_at' => now(),
        ]);

        $this->artisan('vexahost:bersihkan-backup-lama', ['--dry-run' => true])
            ->expectsOutputToContain('Mode kering')
            ->assertSuccessful();

        $this->assertDatabaseHas('session_backups', ['id' => $oldBackup->id]);
        $this->assertDatabaseHas('session_backups', ['id' => $validBackup->id]);
        Storage::disk('session-backups')->assertExists("{$this->session->id}/session.zip");
        Storage::disk('session-backups')->assertExists("{$newSession->id}/session.json");
    }

    public function test_perintah_bersihkan_backup_lama_menghapus_zip_dan_mempertahankan_json(): void
    {
        // Backup lama .zip
        Storage::disk('session-backups')->put("{$this->session->id}/session.zip", 'dummy-zip');
        $oldBackup = SessionBackup::create([
            'wa_session_id' => $this->session->id,
            'disk' => 'session-backups',
            'path' => "{$this->session->id}/session.zip",
            'size' => 9,
            'checksum' => hash('sha256', 'dummy-zip'),
            'backed_up_at' => now()->subDays(10),
        ]);

        // Backup baru .json
        $newSession = $this->workspace->sessions()->create(['name' => 'CS2', 'status' => 'connected']);
        Storage::disk('session-backups')->put("{$newSession->id}/session.json", '{}');
        $validBackup = SessionBackup::create([
            'wa_session_id' => $newSession->id,
            'disk' => 'session-backups',
            'path' => "{$newSession->id}/session.json",
            'size' => 2,
            'checksum' => hash('sha256', '{}'),
            'backed_up_at' => now(),
        ]);

        $this->artisan('vexahost:bersihkan-backup-lama')
            ->expectsOutputToContain('berhasil dihapus')
            ->assertSuccessful();

        $this->assertDatabaseMissing('session_backups', ['id' => $oldBackup->id]);
        $this->assertDatabaseHas('session_backups', ['id' => $validBackup->id]);
        Storage::disk('session-backups')->assertMissing("{$this->session->id}/session.zip");
        Storage::disk('session-backups')->assertExists("{$newSession->id}/session.json");
    }
}
