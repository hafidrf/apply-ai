<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\LlmKey;
use App\Services\Apply\ApplyPipeline;
use App\Services\Apply\InputTextBuilder;
use App\Services\Llm\LlmClientFactory;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /** Kanal yang didukung (PRD §9 + "dm" untuk DM sosial media). */
    private const CHANNELS = 'in:email,linkedin,whatsapp,portal,dm';

    public function __construct(private ApplyPipeline $pipeline)
    {
    }

    public function index(Request $request)
    {
        $jobs = $request->user()->jobPostings()
            ->with('drafts')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($jobs->through(fn ($j) => $this->present($j)));
    }

    public function show(Request $request, Job $job)
    {
        if ($job->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $job->load('drafts');

        return response()->json($this->present($job));
    }

    /**
     * FR3/FR4/FR5 — parse lowongan dari input bebas gaya chat:
     * teks (termasuk hasil OCR screenshot dari browser), link, dan/atau PDF.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'raw_text' => ['nullable', 'string'],
            'pdfs' => ['nullable', 'array'],
            'pdfs.*' => ['file', 'mimes:pdf', 'max:40960'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'mimes:png,jpg,jpeg,webp,gif,bmp', 'max:20480'],
            'input_type' => ['nullable', 'in:text,screenshot,mixed'],
            'auto_links' => ['nullable', 'boolean'],
            'ocr_text' => ['nullable', 'string'],
            'channel_override' => ['nullable', self::CHANNELS],
            'profile_id' => ['nullable', 'integer', 'exists:profiles,id'],
            'llm_key_id' => ['nullable', 'integer'],
            'lang' => ['nullable', 'in:id,en'],
        ]);

        $key = $this->keyForRequest($request, $data['llm_key_id'] ?? null);

        // Validasi kepemilikan profil
        if (!empty($data['profile_id'])) {
            $profile = $request->user()->profiles()->find($data['profile_id']);
            if (!$profile) {
                return response()->json(['message' => 'Profil tidak ditemukan.'], 404);
            }
        }

        // Gabungkan semua bentuk input jadi satu teks
        $pdfFiles = collect($request->file('pdfs') ?? [])
            ->map(fn ($f) => ['name' => $f->getClientOriginalName(), 'path' => $f->getRealPath()])
            ->all();

        // Hasil OCR dari browser ikut digabung ke teks (kalau user memilih mode teks)
        $rawText = (string) $request->input('raw_text', '');
        if ($ocrText = (string) $request->input('ocr_text', '')) {
            $rawText = trim($rawText . "\n\n" . $ocrText);
        }

        $hasImages = count($request->file('images') ?? []) > 0;
        $hasTextOrPdf = trim($rawText) !== '' || $pdfFiles !== [];

        $built = ['text' => $rawText, 'meta' => ['sources' => [], 'notes' => []]];

        if ($hasTextOrPdf) {
            try {
                $built = (new InputTextBuilder())->build(
                    $rawText,
                    $pdfFiles,
                    (bool) $request->input('auto_links', true),
                );
            } catch (\RuntimeException $e) {
                // Kalau tidak ada gambar sebagai alternatif, baru gagalkan
                if (!$hasImages) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }
            }
        } elseif (!$hasImages) {
            return response()->json([
                'message' => 'Tidak ada isi yang bisa diproses. Paste teks lowongan, lampirkan gambar/PDF, atau masukkan link.',
            ], 422);
        }

        // Gambar: kirim sebagai gambar ke model vision
        $imageInputs = collect($request->file('images') ?? [])->all();
        $imageDataUrls = [];
        $client = LlmClientFactory::make($key);

        if ($imageInputs !== []) {
            if (!$client->supportsVision()) {
                return response()->json([
                    'message' => 'Model yang dipakai belum mendukung gambar. '
                        . 'Pilih model vision (mis. 9routeragent, gemini/gemini-3.6-flash, '
                        . 'cmc/Qwen/Qwen3.8-Max) di dropdown Provider AI, '
                        . 'atau kirim gambar setelah dibaca jadi teks (mode OCR).',
                    'code' => 'vision_not_supported',
                ], 422);
            }

            foreach ($imageInputs as $img) {
                $mime = $img->getMimeType() ?: 'image/png';
                $imageDataUrls[] = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($img->getRealPath()));
            }
        }

        // Kalau ada gambar DAN teks/PDF, tetap kirim keduanya
        $job = $this->pipeline->parseJob(
            $key,
            $built['text'],
            $imageDataUrls !== [] ? 'screenshot' : ($data['input_type'] ?? 'text'),
            $data['channel_override'] ?? null,
            $data['lang'] ?? 'id',
            $data['profile_id'] ?? null,
            $imageDataUrls,
        );

        // Simpan ringkasan sumber supaya bisa ditampilkan di halaman detail
        $meta = $built['meta'];
        if ($imageDataUrls !== []) {
            $meta['sources'][] = [
                'type' => 'image',
                'name' => count($imageDataUrls) . ' gambar',
                'chars' => 0,
                'error' => null,
            ];
        }
        $meta['used_vision'] = $imageDataUrls !== [];

        $job->input_meta = $meta;
        $job->save();
        $job->refresh();

        return response()->json($this->present($job) + ['input_meta' => $meta], 201);
    }

    /**
     * Tambah bagian/laman pada lowongan yang sudah ada.
     * Berguna untuk lowongan bentuk THREAD (1/2, 2/2) atau yang info-nya terpisah:
     * user paste/tempel screenshot lanjutannya, lalu lowongan diparse ulang
     * dengan menggabungkan bagian sebelumnya.
     */
    public function append(Request $request, Job $job)
    {
        if ($job->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $request->validate([
            'raw_text' => ['nullable', 'string'],
            'pdfs' => ['nullable', 'array'],
            'pdfs.*' => ['file', 'mimes:pdf', 'max:40960'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'mimes:png,jpg,jpeg,webp,gif,bmp', 'max:20480'],
            'auto_links' => ['nullable', 'boolean'],
            'llm_key_id' => ['nullable', 'integer'],
        ]);

        $key = $this->keyForRequest($request, $request->input('llm_key_id'));

        $newText = trim((string) $request->input('raw_text', ''));
        $pdfFiles = collect($request->file('pdfs') ?? [])
            ->map(fn ($f) => ['name' => $f->getClientOriginalName(), 'path' => $f->getRealPath()])
            ->all();
        $imageInputs = collect($request->file('images') ?? [])->all();

        if ($newText === '' && $pdfFiles === [] && $imageInputs === []) {
            return response()->json(['message' => 'Tidak ada bagian baru yang dikirim.'], 422);
        }

        // Gabungkan bagian baru dengan yang lama
        $buildText = $newText;
        if ($pdfFiles !== []) {
            try {
                $built = (new InputTextBuilder())->build($newText, $pdfFiles, (bool) $request->input('auto_links', true));
                $buildText = $built['text'];
            } catch (\RuntimeException $e) {
                if ($imageInputs === []) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }
            }
        }

        $combinedText = trim($job->raw_text . "\n\n" . $buildText);

        // Gambar baru butuh model vision
        $newImageDataUrls = [];
        if ($imageInputs !== []) {
            $client = LlmClientFactory::make($key);
            if (!$client->supportsVision()) {
                return response()->json([
                    'message' => 'Model yang dipakai belum mendukung gambar. Pilih model vision dulu, atau paste teksnya.',
                    'code' => 'vision_not_supported',
                ], 422);
            }
            foreach ($imageInputs as $img) {
                $mime = $img->getMimeType() ?: 'image/png';
                $newImageDataUrls[] = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($img->getRealPath()));
            }
        }

        $job = $this->pipeline->parseJob(
            $key,
            $combinedText,
            $newImageDataUrls !== [] ? 'screenshot' : $job->raw_input_type,
            null,
            'id',
            $job->profile_id,
            $newImageDataUrls,
        );

        // Segmen buatan parseJob baru — pakai yang sudah tersimpan
        $meta = $job->input_meta ?? ['sources' => [], 'notes' => []];
        if (!empty($buildText)) {
            $meta['sources'][] = ['type' => 'text', 'name' => 'bagian tambahan', 'chars' => mb_strlen($buildText), 'error' => null];
        }
        if ($newImageDataUrls !== []) {
            $meta['sources'][] = ['type' => 'image', 'name' => count($newImageDataUrls) . ' gambar tambahan', 'chars' => 0, 'error' => null];
        }
        $job->input_meta = $meta;
        $job->save();

        $job->refresh();

        return response()->json($this->present($job) + ['input_meta' => $meta]);
    }

    /** FR6–FR9 — generate draft pesan. */
    public function generate(Request $request, Job $job)
    {
        if ($job->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $data = $request->validate([
            'channel_override' => ['nullable', self::CHANNELS],
            'output_lang' => ['nullable', 'in:id,en'],
            'with_variant' => ['nullable', 'boolean'],
            'llm_key_id' => ['nullable', 'integer'],
        ]);

        $key = $this->keyForRequest($request, $data['llm_key_id'] ?? null);

        $draft = $this->pipeline->generate(
            $job,
            $key,
            $data['channel_override'] ?? null,
            $data['output_lang'] ?? 'id',
            $data['with_variant'] ?? false,
        );

        $job->refresh();
        $job->load('drafts');

        return response()->json($this->present($job));
    }

    /** FR9 — varian nada on-demand. */
    public function variant(Request $request, Job $job)
    {
        if ($job->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $key = $this->defaultKey($request);

        try {
            $this->pipeline->generateVariant($job, $key, $request->input('output_lang', 'id'));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $job->refresh();
        $job->load('drafts');

        return response()->json($this->present($job));
    }

    /** Revisi pesan dengan instruksi bahasa natural dari user. */
    public function revise(Request $request, Job $job)
    {
        if ($job->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $data = $request->validate([
            'instruction' => ['required', 'string', 'min:3', 'max:1000'],
            'kind' => ['nullable', 'in:utama,varian'],
            'output_lang' => ['nullable', 'in:id,en'],
            'llm_key_id' => ['nullable', 'integer'],
        ]);

        $key = $this->keyForRequest($request, $data['llm_key_id'] ?? null);

        try {
            $this->pipeline->reviseMessage(
                $job,
                $key,
                trim($data['instruction']),
                $data['kind'] ?? 'utama',
                $data['output_lang'] ?? 'id',
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $job->refresh();
        $job->load('drafts');

        return response()->json($this->present($job));
    }

    /** FR10 — batch: banyak lowongan sekaligus. */
    public function batchGenerate(Request $request)
    {
        $data = $request->validate([
            'job_ids' => ['required', 'array', 'min:1'],
            'job_ids.*' => ['integer'],
            'output_lang' => ['nullable', 'in:id,en'],
        ]);

        $key = $this->defaultKey($request);

        $jobs = $request->user()->jobPostings()
            ->whereIn('id', $data['job_ids'])
            ->get();

        if ($jobs->isEmpty()) {
            return response()->json(['message' => 'Tidak ada lowongan valid.'], 404);
        }

        $result = $this->pipeline->batch($jobs->all(), $key, $data['output_lang'] ?? 'id');

        return response()->json([
            'ok' => collect($result['ok'])->map(fn ($j) => $j->id)->all(),
            'failed' => collect($result['failed'])->map(fn ($f) => [
                'job_id' => $f['job']->id,
                'error' => $f['error'],
            ])->all(),
        ]);
    }

    public function destroy(Request $request, Job $job)
    {
        if ($job->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $job->delete();

        return response()->json(['ok' => true]);
    }

    private function present(Job $j): array
    {
        return [
            'id' => $j->id,
            'profile_id' => $j->profile_id,
            'raw_input_type' => $j->raw_input_type,
            'channel' => $j->channel,
            'position' => $j->position,
            'company' => $j->company,
            'parsed' => $j->parsed,
            'input_meta' => $j->input_meta,
            'status' => $j->status,
            'error' => $j->error,
            'drafts' => $j->drafts->map(fn ($d) => [
                'id' => $d->id,
                'channel' => $d->channel,
                'message_text' => $d->message_text,
                'notes' => $d->notes,
                'variant_text' => $d->variant_text,
                'created_at' => $d->created_at?->toIso8601String(),
            ])->values(),
            'created_at' => $j->created_at?->toIso8601String(),
        ];
    }

    private function defaultKey(Request $request): LlmKey
    {
        $key = $request->user()->llmKeys()
            ->where('is_default', true)
            ->first()
            ?? $request->user()->llmKeys()->first();

        if (!$key) {
            abort(422, 'Belum ada API key provider. Tambahkan dulu di halaman Pengaturan.');
        }

        return $key;
    }

    /**
     * Key untuk request ini — user bisa memilih provider tertentu
     * (berguna saat provider default kena rate limit / kuota habis).
     */
    private function keyForRequest(Request $request, ?int $llmKeyId = null): LlmKey
    {
        if ($llmKeyId) {
            $key = $request->user()->llmKeys()->find($llmKeyId);
            if (!$key) {
                abort(422, 'Provider yang dipilih tidak ditemukan.');
            }

            return $key;
        }

        return $this->defaultKey($request);
    }
}
