<?php

namespace App\Services\Apply;

use App\Models\Draft;
use App\Models\Job;
use App\Models\LlmKey;
use App\Models\MessageEvent;
use App\Models\Profile;
use App\Services\Llm\LlmClientFactory;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrator: profil → lowongan → deteksi kanal → matching → reframing → pesan.
 * Semua tahap memakai LlmClient milik user (BYOK).
 */
class ApplyPipeline
{
    public function __construct(
        private LlmClientFactory $factory,
    ) {
    }

    private function client(LlmKey $key): LlmClient
    {
        return LlmClientFactory::make($key);
    }

    /**
     * FR1/FR2 — Parse & simpan profil.
     *
     * Profil langsung AKTIF & siap dipakai (tidak ada tahap crosscheck/tanya-jawab).
     * User tetap bisa menyuntingnya kapan saja di halaman Profil.
     */
    public function parseProfile(
        LlmKey $key,
        string $rawInput,
        string $source = 'text',
        string $lang = 'id',
        array $images = [],
        array $inputMeta = [],
    ): Profile {
        $parser = new ProfileParser($this->client($key));
        $data = $parser->parse($rawInput, $source, $lang, $images);

        return Profile::create([
            'user_id' => $key->user_id,
            'session_id' => (string) Str::uuid(),
            'source' => $source,
            'raw_input' => $rawInput,   // kolom LONGTEXT — tanpa pemotongan
            'data' => $data,
            'input_meta' => $inputMeta ?: null,
            'confirmed_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * FR2 — Konfirmasi profil (user sudah review/koreksi).
     */
    public function confirmProfile(Profile $profile, ?array $correctedData = null): Profile
    {
        if ($correctedData !== null) {
            $profile->data = $correctedData;
        }
        $profile->confirmed_at = now();
        $profile->is_active = true;
        $profile->save();

        return $profile;
    }

    /**
     * FR3/FR4/FR5 — Parse lowongan + deteksi kanal.
     */
    public function parseJob(
        LlmKey $key,
        string $rawText,
        string $inputType = 'text',
        ?string $channelOverride = null,
        string $lang = 'id',
        ?int $profileId = null,
        array $images = [],
    ): Job {
        $client = $this->client($key);

        $parsed = (new JobParser($client))->parse($rawText, $inputType, $lang, $images);

        $channel = $channelOverride;
        $channelReason = 'override oleh user';
        if ($channel === null) {
            $detected = (new ChannelDetector($client))->detect($parsed, $rawText, $lang);
            $channel = $detected['kanal'];
            $channelReason = $detected['alasan'];
        }

        $job = new Job();
        $job->user_id = $key->user_id;
        $job->profile_id = $profileId;
        $job->raw_input_type = $inputType;
        $job->raw_text = $rawText;
        $job->channel = $channel;
        $job->position = $parsed['posisi'] ?? null;
        $job->company = $parsed['perusahaan'] ?? null;
        $job->parsed = $parsed + ['channel_reason' => $channelReason];
        $job->status = 'parsed';
        $job->save();

        return $job;
    }

    /**
     * FR6/FR7/FR8/FR9 — Full compose: matching → reframe → pesan + catatan + varian.
     */
    public function generate(Job $job, LlmKey $key, ?string $channelOverride = null, string $outputLang = 'id', bool $withVariant = false): Draft
    {
        $client = $this->client($key);

        /** @var Profile|null $profile */
        $profile = $job->profile_id ? Profile::find($job->profile_id) : null;

        // Lowongan belum terkait profil → pakai profil terbaru milik user (FR2 — profil dipakai ulang).
        // Profil tidak perlu dikonfirmasi lagi, jadi TIDAK menimpa profil yang sudah dipilih user.
        if (!$profile && $job->user_id) {
            $profile = Profile::where('user_id', $job->user_id)
                ->orderByDesc('created_at')
                ->first();
            if ($profile) {
                $job->profile_id = $profile->id;
                $job->save();
            }
        }

        if (!$profile) {
            throw new \RuntimeException('Belum ada profil. Buat profil dulu di halaman Profil.');
        }

        DB::transaction(function () use (&$draft, $job, $client, $profile, $key, $channelOverride, $outputLang, $withVariant) {
            $channel = $channelOverride ?: $job->channel;
            if ($channel === null) {
                throw new \RuntimeException('Kanal belum terdeteksi dan tidak dioverride.');
            }

            // FR6 + FR7 — matching & reframing digabung dalam satu panggilan LLM
            $combined = (new MatchAndReframe($client))->run($profile->data, $job->parsed, $outputLang);
            $matching = [
                'matches' => $combined['matches'],
                'kata_kunci_ats' => $combined['kata_kunci_ats'],
                'kekuatan_utama' => $combined['kekuatan_utama'],
            ];
            $reframes = $combined['reframes'];

            // FR8 — pesan utama
            $composer = new MessageComposer($client);
            $result = $composer->compose($profile->data, $job->parsed, $matching, $reframes, $channel, $outputLang);

            // FR9 — varian nada (opsional)
            $variant = $withVariant
                ? $composer->composeVariant($profile->data, $job->parsed, $matching, $reframes, $channel, $outputLang)
                : null;

            // FR9 — catatan privat: requirement mana yang direframe (untuk persiapan interview)
            $notes = [
                'kanal' => $channel,
                'jumlah_kata' => $result['jumlah_kata'],
                'kata_kunci_ats' => $matching['kata_kunci_ats'] ?? [],
                'match_ringkas' => collect($matching['matches'] ?? [])
                    ->map(fn ($m) => [
                        'requirement' => $m['requirement'] ?? '',
                        'status' => $m['status'] ?? '',
                    ])->all(),
                'reframes' => collect($reframes['reframes'] ?? [])
                    ->filter(fn ($r) => !empty($r['reframe']))
                    ->map(fn ($r) => [
                        'requirement' => $r['requirement'] ?? '',
                        'teknik' => $r['teknik'] ?? null,
                        'reframe' => $r['reframe'],
                        'dasar_fakta' => $r['dasar_fakta'] ?? null,
                        'persiapan_interview' => 'Siapkan penjelasan konkret tentang: ' . ($r['requirement'] ?? ''),
                    ])->all(),
            ];

            $job->channel = $channel;
            $job->matching = $matching;
            $job->reframes = $reframes;
            $job->status = 'composed';
            $job->save();

            $draft = new Draft();
            $draft->job_id = $job->id;
            $draft->channel = $channel;
            $draft->message_text = $result['pesan'];
            $draft->notes = $notes;
            $draft->variant_text = $variant;
            $draft->save();
        });

        // Riwayat: pesan utama dibuat
        MessageEvent::record($job, $draft, MessageEvent::GENERATED);

        return $draft;
    }

    /**
     * FR9 — Varian nada alternatif, dibuat on-demand (hemat panggilan LLM).
     */
    public function generateVariant(Job $job, LlmKey $key, string $outputLang = 'id'): Draft
    {
        $draft = $job->drafts()->latest()->first();
        if (!$draft) {
            throw new \RuntimeException('Belum ada pesan utama. Generate pesan dulu.');
        }

        $profile = $job->profile_id ? Profile::find($job->profile_id) : null;
        if (!$profile) {
            throw new \RuntimeException('Profil tidak ditemukan untuk lowongan ini.');
        }

        $client = $this->client($key);
        $composer = new MessageComposer($client);

        $variant = $composer->composeVariant(
            $profile->data,
            $job->parsed ?? [],
            $job->matching ?? [],
            $job->reframes ?? [],
            $draft->channel,
            $outputLang,
        );

        if ($variant === null) {
            throw new \RuntimeException('Gagal membuat varian. Coba lagi.');
        }

        $draft->variant_text = $variant;
        $draft->save();

        // Riwayat: varian nada dibuat
        MessageEvent::record($job, $draft, MessageEvent::VARIANT, 'varian');

        return $draft;
    }

    /** Revisi pesan utama atau varian dan simpan snapshot ke Riwayat. */
    public function reviseMessage(
        Job $job,
        LlmKey $key,
        string $instruction,
        string $kind = 'utama',
        string $outputLang = 'id',
    ): Draft {
        $draft = $job->drafts()->latest()->first();
        if (!$draft) {
            throw new \RuntimeException('Belum ada pesan. Generate pesan dulu.');
        }

        $current = $kind === 'varian' ? $draft->variant_text : $draft->message_text;
        if (!is_string($current) || trim($current) === '') {
            throw new \RuntimeException($kind === 'varian'
                ? 'Belum ada varian untuk direvisi.'
                : 'Belum ada pesan untuk direvisi.');
        }

        $profile = $job->profile_id ? Profile::find($job->profile_id) : null;
        if (!$profile) {
            throw new \RuntimeException('Profil tidak ditemukan untuk lowongan ini.');
        }

        $result = (new MessageComposer($this->client($key)))->revise(
            $current,
            $instruction,
            $profile->data,
            $job->parsed ?? [],
            $draft->channel,
            $outputLang,
        );

        if ($kind === 'varian') {
            $draft->variant_text = $result['pesan'];
        } else {
            $draft->message_text = $result['pesan'];
            $notes = $draft->notes ?? [];
            $notes['jumlah_kata'] = $result['jumlah_kata'];
            $draft->notes = $notes;
        }
        $draft->save();

        MessageEvent::record($job, $draft, MessageEvent::REVISED, $kind, $instruction);

        return $draft;
    }

    /**
     * FR10 — Batch: proses banyak lowongan berurutan, hasil terpisah.
     * @return array{ok: Job[], failed: array{job: Job, error: string}[]}
     */
    public function batch(array $jobs, LlmKey $key, string $outputLang = 'id'): array
    {
        $ok = [];
        $failed = [];

        foreach ($jobs as $job) {
            /** @var Job $job */
            try {
                $this->generate($job, $key, null, $outputLang);
                $ok[] = $job;
            } catch (\Throwable $e) {
                $job->status = 'failed';
                $job->error = $e->getMessage();
                $job->save();
                $failed[] = ['job' => $job, 'error' => $e->getMessage()];
            }
        }

        return ['ok' => $ok, 'failed' => $failed];
    }
}
