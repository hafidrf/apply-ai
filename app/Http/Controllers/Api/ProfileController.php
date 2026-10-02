<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LlmKey;
use App\Services\Apply\ApplyPipeline;
use App\Services\Apply\InputTextBuilder;
use App\Services\Llm\LlmClientFactory;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private ApplyPipeline $pipeline)
    {
    }

    public function index(Request $request)
    {
        $profiles = $request->user()->profiles()
            ->orderByDesc('created_at')
            ->get();

        return response()->json($profiles->map(fn ($p) => $this->present($p)));
    }

    /**
     * FR1 — parse profil dari input bebas gaya chat:
     * teks, link, banyak PDF, dan/atau banyak gambar (sertifikat, screenshot profil).
     */
    public function store(Request $request)
    {
        $request->validate([
            'source' => ['nullable', 'in:text,pdf,link,mixed'],
            'raw_text' => ['nullable', 'string'],
            'link' => ['nullable', 'string', 'max:2000'],
            'pdfs' => ['nullable', 'array'],
            'pdfs.*' => ['file', 'mimes:pdf', 'max:40960'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'mimes:png,jpg,jpeg,webp,gif,bmp', 'max:20480'],
            'ocr_text' => ['nullable', 'string'],
            'auto_links' => ['nullable', 'boolean'],
            'llm_key_id' => ['nullable', 'integer'],
            'lang' => ['nullable', 'in:id,en'],
        ]);

        $key = $this->keyForRequest($request, $request->input('llm_key_id'));

        $rawText = (string) $request->input('raw_text', '');
        // Link dari field terpisah digabung ke teks supaya ikut dibaca otomatis
        if ($link = trim((string) $request->input('link', ''))) {
            $rawText = trim($rawText . "\n" . $link);
        }
        if ($ocrText = (string) $request->input('ocr_text', '')) {
            $rawText = trim($rawText . "\n\n" . $ocrText);
        }

        $pdfFiles = collect($request->file('pdfs') ?? [])
            ->map(fn ($f) => ['name' => $f->getClientOriginalName(), 'path' => $f->getRealPath()])
            ->all();

        $imageInputs = collect($request->file('images') ?? [])->all();

        if (trim($rawText) === '' && $pdfFiles === [] && $imageInputs === []) {
            return response()->json([
                'message' => 'Tidak ada isi yang bisa diproses. Tempel teks, lampirkan gambar/PDF, atau masukkan link profil.',
            ], 422);
        }

        // Gabungkan teks + PDF + isi link (kalau ada)
        $built = ['text' => $rawText, 'meta' => ['sources' => [], 'notes' => []]];

        if (trim($rawText) !== '' || $pdfFiles !== []) {
            try {
                $built = (new InputTextBuilder())->build(
                    $rawText,
                    $pdfFiles,
                    (bool) $request->input('auto_links', true),
                );
            } catch (\RuntimeException $e) {
                if ($imageInputs === []) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }
            }
        }

        // Gambar → kirim ke model vision (kalau didukung)
        $imageDataUrls = [];
        if ($imageInputs !== []) {
            $client = LlmClientFactory::make($key);

            if (!$client->supportsVision()) {
                return response()->json([
                    'message' => 'Model yang dipakai belum mendukung gambar. '
                        . 'Pilih model vision di halaman Pengaturan (mis. deepseek-flash, 9routeragent), '
                        . 'atau ubah gambarnya jadi teks dulu.',
                    'code' => 'vision_not_supported',
                ], 422);
            }

            foreach ($imageInputs as $img) {
                $mime = $img->getMimeType() ?: 'image/png';
                $imageDataUrls[] = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($img->getRealPath()));
            }
        }

        $meta = $built['meta'];
        if ($imageDataUrls !== []) {
            $meta['sources'][] = [
                'type' => 'image',
                'name' => count($imageDataUrls) . ' gambar',
                'chars' => 0,
                'error' => null,
            ];
            $meta['used_vision'] = true;
        }

        // Tentukan label sumber untuk tampilan
        $source = 'text';
        if ($imageDataUrls !== [] && ($built['text'] !== '' || $pdfFiles !== [])) {
            $source = 'mixed';
        } elseif ($imageDataUrls !== []) {
            $source = 'image';
        } elseif ($pdfFiles !== []) {
            $source = 'pdf';
        } elseif (InputTextBuilder::detectUrls($rawText) !== [] && trim(preg_replace('#https?://\S+#i', '', $rawText)) === '') {
            $source = 'link';
        }

        $profile = $this->pipeline->parseProfile(
            $key,
            $built['text'],
            $source,
            $request->input('lang', 'id'),
            $imageDataUrls,
            $meta,
        );

        return response()->json($this->present($profile) + ['input_meta' => $meta], 201);
    }

    /** FR2 — konfirmasi (dengan koreksi opsional). */
    public function confirm(Request $request, \App\Models\Profile $profile)
    {
        if ($profile->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $data = $request->validate([
            'data' => ['nullable', 'array'],
        ]);

        $profile = $this->pipeline->confirmProfile($profile, $data['data'] ?? null);

        return response()->json($this->present($profile));
    }

    public function show(Request $request, \App\Models\Profile $profile)
    {
        if ($profile->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        return response()->json($this->present($profile));
    }

    public function destroy(Request $request, \App\Models\Profile $profile)
    {
        if ($profile->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $profile->delete();

        return response()->json(['ok' => true]);
    }

    private function present($p): array
    {
        return [
            'id' => $p->id,
            'session_id' => $p->session_id,
            'source' => $p->source,
            'data' => $p->data,
            'input_meta' => $p->input_meta,
            'confirmed' => (bool) $p->confirmed_at,
            'is_active' => $p->is_active,
            'created_at' => $p->created_at?->toIso8601String(),
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
     * (berguna saat provider default kena rate limit / tidak dukung gambar).
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
