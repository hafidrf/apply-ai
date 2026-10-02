<?php

namespace Tests\Unit;

use App\Services\Apply\MessageComposer;
use App\Services\Llm\LlmClient;
use PHPUnit\Framework\TestCase;

class MessageComposerRevisionTest extends TestCase
{
    public function test_revision_uses_instruction_message_profile_and_job_context(): void
    {
        $client = new class implements LlmClient {
            public array $messages = [];

            public function chat(array $messages, array $options = []): string
            {
                $this->messages = $messages;

                return json_encode([
                    'pesan' => 'Halo, saya tertarik untuk posisi ini.',
                    'jumlah_kata' => 7,
                ], JSON_THROW_ON_ERROR);
            }

            public function ping(): string { return 'ok'; }
            public function listModels(): array { return []; }
            public function supportsVision(): bool { return false; }
        };

        $result = (new MessageComposer($client))->revise(
            'Pesan awal yang terlalu kaku.',
            'Buat lebih natural dan ringkas.',
            ['nama' => 'Hafid'],
            ['posisi' => 'Product Engineer'],
            'dm',
        );

        $this->assertSame('Halo, saya tertarik untuk posisi ini.', $result['pesan']);
        $this->assertSame(7, $result['jumlah_kata']);
        $this->assertStringContainsString('Buat lebih natural dan ringkas.', $client->messages[1]['content']);
        $this->assertStringContainsString('Pesan awal yang terlalu kaku.', $client->messages[1]['content']);
        $this->assertStringContainsString('Hafid', $client->messages[1]['content']);
        $this->assertStringContainsString('Product Engineer', $client->messages[1]['content']);
        $this->assertStringContainsString('DILARANG mengarang', $client->messages[0]['content']);
    }

    public function test_revision_rejects_empty_model_output(): void
    {
        $client = new class implements LlmClient {
            public function chat(array $messages, array $options = []): string
            {
                return '{"jumlah_kata":0}';
            }

            public function ping(): string { return 'ok'; }
            public function listModels(): array { return []; }
            public function supportsVision(): bool { return false; }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pesan revisi kosong');

        (new MessageComposer($client))->revise('Pesan awal', 'lebih ringkas', [], [], 'dm');
    }
}