<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\MessageEvent;
use Illuminate\Http\Request;

/**
 * Riwayat pesan lamaran — dikelompokkan per bulan (terbaru di atas).
 */
class HistoryController extends Controller
{
    private const MAX_EVENTS = 500;

    public function index(Request $request)
    {
        $events = MessageEvent::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_EVENTS)
            ->get();

        // Sudah terurut terbaru → groupBy mempertahankan urutan itu
        $months = $events
            ->groupBy(fn (MessageEvent $e) => $e->created_at->format('Y-m'))
            ->map(fn ($items, $key) => [
                'key' => $key,
                'count' => $items->count(),
                'events' => $items->map(fn (MessageEvent $e) => $this->present($e))->values()->all(),
            ])
            ->values()
            ->all();

        return response()->json([
            'total' => $events->count(),
            'months' => $months,
        ]);
    }

    /**
     * Dipanggil FE saat user menekan "Copy" — tanda pesan akan/sudah dikirim.
     * Teks diambil dari draft di server (bukan dari FE) supaya yang tersimpan benar-benar isinya.
     */
    public function store(Request $request, Job $job)
    {
        if ($job->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $data = $request->validate([
            'kind' => ['nullable', 'in:utama,varian'],
        ]);
        $kind = $data['kind'] ?? 'utama';

        $draft = $job->drafts()->latest()->first();
        $event = MessageEvent::record($job, $draft, MessageEvent::COPIED, $kind);

        if (!$event) {
            return response()->json(['message' => 'Belum ada pesan untuk dicatat.'], 422);
        }

        return response()->json($this->present($event), 201);
    }

    public function destroy(Request $request, int $id)
    {
        $event = MessageEvent::where('user_id', $request->user()->id)->find($id);

        if (!$event) {
            return response()->json(['message' => 'Riwayat tidak ditemukan.'], 404);
        }

        $event->delete();

        return response()->json(['ok' => true]);
    }

    private function present(MessageEvent $e): array
    {
        return [
            'id' => $e->id,
            'job_id' => $e->job_id,
            'event' => $e->event,
            'kind' => $e->kind,
            'channel' => $e->channel,
            'position' => $e->position,
            'company' => $e->company,
            'message_text' => $e->message_text,
            'instruction' => $e->instruction,
            'word_count' => $e->word_count,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
