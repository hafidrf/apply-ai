<?php

/*
|--------------------------------------------------------------------------
| LLM Providers untuk Asisten Apply Kerja (BYOK)
|--------------------------------------------------------------------------
|
| Setiap entry: id, label, tipe klien, default base URL, daftar model populer.
| User membawa API key sendiri (disimpan encrypted di tabel llm_keys).
| Semua provider tipe "openai-compat" memakai satu adapter yang sama.
|
*/

return [

    'default_timeout' => 120,

    'providers' => [

        '9router' => [
            'label' => '9Router (gateway lokal)',
            'client' => 'openai-compat',
            'base_url' => 'http://127.0.0.1:20128/v1',
            'models' => [
                // Kombinasi otomatis 9Router
                '9routeragent',
                '9routerplan',
                '9router-v4cheap',
                'justdeepseek',
                // DeepSeek langsung
                'ds/deepseek-v4-pro',
                'ds/deepseek-v4.1-flash',
                'ds/deepseek-v4-flash',
                // Model kuat untuk penulisan & instruksi JSON
                'cmc/zai-org/GLM-5.3',
                'cmc/moonshotai/Kimi-K3',
                'cmc/Qwen/Qwen3.7-Max',
                'gemini/gemini-3.8-flash',
                'cerebras/zai-glm-4.7',
                // Cepat & hemat
                'cf/@cf/zai-org/glm-4.7-flash',
                'oc/laguna-s-2.1-free',
                'groq/openai/gpt-oss-120b',
            ],
            'key_hint' => 'sk-... (dari 9Router → API Keys)',

            // Model yang sudah TERVERIFIASI bisa membaca gambar (probe 2026-09-28).
            // Model di luar daftar ini dianggap teks saja.
            'vision_models' => [
                '9routeragent',
                // DeepSeek via 9Router — v4-pro & v4-flash dukung gambar
                'ds/deepseek-v4-pro',
                'ds/deepseek-v4-pro-max',
                'ds/deepseek-v4-pro-none',
                'ds/deepseek-v4-flash',
                'cmc/deepseek/deepseek-v4-pro',
                'cmc/deepseek/deepseek-v4-flash',
                'nvidia/deepseek-ai/deepseek-v4-pro',
                'kr/deepseek-3.2',
                // Lain-lain
                'cmc/Qwen/Qwen3.8-Max',
                'gemini/gemini-3.6-flash',
                'oc/nemotron-3-ultra-free',
            ],
        ],

        'felidaeai' => [
            'label' => 'FelidaeAI (automatedpros)',
            'client' => 'openai-compat',
            'base_url' => 'https://ai.automatedpros.link/v1',
            'models' => [
                'FelidaeAI-Omni-3.6',
                'glm-5.3',
                'glm-5.3-flash',
                'glm-4.7-flash',
                'glm-4.7-flashx',
                'glm-4.5-flash',
            ],
            'key_hint' => 'sk-...',
        ],

        'deepseek' => [
            'label' => 'DeepSeek',
            'client' => 'openai-compat',
            'base_url' => 'https://api.deepseek.com/v1',
            // Nama model resmi per dokumentasi DeepSeek (2026):
            // deepseek-chat / deepseek-reasoner sudah TIDAK dipakai lagi.
            'models' => [
                'deepseek-flash',                  // V4.1-Flash — dukung gambar
                'deepseek-v4-pro',                 // V4-Pro-0813 — teks saja
                'deepseek-v4-flash-vision-exp',    // nama lama, tetap diterima (diarahkan ke Flash)
            ],
            'key_hint' => 'sk-...',
            // Terverifikasi dari docs DeepSeek: deepseek-flash menerima gambar
            // (screenshot, chart, OCR). deepseek-v4-pro TIDAK.
            'vision_models' => [
                'deepseek-flash',
                'deepseek-v4-flash-vision-exp',
            ],
            // DeepSeek-flash adalah model THINKING secara default: token reasoning
            // ikut memakan max_tokens dan bisa membuat jawaban JSON kosong.
            // Tugas kita butuh output JSON langsung → matikan thinking.
            'extra_body' => [
                'thinking' => ['type' => 'disabled'],
            ],
        ],

        'openrouter' => [
            'label' => 'OpenRouter',
            'client' => 'openai-compat',
            'base_url' => 'https://openrouter.ai/api/v1',
            'models' => [
                // OpenRouter punya ribuan model; biarkan user isi manual,
                // tapi beri contoh populer:
                'deepseek/deepseek-chat',
                'google/gemini-2.0-flash-001',
                'anthropic/claude-3.5-sonnet',
                'meta-llama/llama-3.3-70b-instruct',
            ],
            'key_hint' => 'sk-or-...',
            'vision_models' => [],
        ],

        'gemini' => [
            'label' => 'Google Gemini',
            'client' => 'gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'models' => [
                'gemini-2.0-flash',
                'gemini-1.5-flash',
                'gemini-1.5-pro',
            ],
            'key_hint' => 'AIza...',
            'vision_models' => [
                'gemini-2.0-flash',
                'gemini-1.5-flash',
                'gemini-1.5-pro',
            ],
        ],

        'groq' => [
            'label' => 'Groq',
            'client' => 'openai-compat',
            'base_url' => 'https://api.groq.com/openai/v1',
            'models' => [
                'llama-3.3-70b-versatile',
                'llama-3.1-8b-instant',
            ],
            'key_hint' => 'gsk_...',
            'vision_models' => [],
        ],

        'ollama' => [
            'label' => 'Ollama (lokal)',
            'client' => 'openai-compat',
            'base_url' => 'http://127.0.0.1:11434/v1',
            'models' => [
                'llama3.2',
                'qwen2.5',
            ],
            'key_hint' => 'tidak perlu key (opsional)',
            'vision_models' => [],
        ],

        'custom' => [
            'label' => 'Custom (OpenAI-compatible)',
            'client' => 'openai-compat',
            'base_url' => null, // wajib diisi user
            'models' => [],
            'key_hint' => 'sk-...',
            'vision_models' => [],
        ],
    ],
];
