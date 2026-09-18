<?php

namespace Tests\Feature;

use App\Sessionize\SessionizeNormalizer;
use RuntimeException;
use Tests\TestCase;

class SessionizeNormalizerTest extends TestCase
{
    public function test_merges_rich_and_schedule_data_with_grid_smart_planning_precedence(): void
    {
        config(['app.timezone' => 'Europe/Rome']);

        $data = app(SessionizeNormalizer::class)->normalize($this->payload());

        $talk = collect($data->sessions)->firstWhere('sessionize_id', 'talk-1');
        $service = collect($data->sessions)->firstWhere('sessionize_id', 'service-1');

        $this->assertSame('A practical discussion about resilient software.', $talk['description']);
        $this->assertSame('2027-06-01T07:15:00+00:00', $talk['starts_at']?->toIso8601String());
        $this->assertSame(['speaker-a', 'speaker-b'], $talk['speaker_ids']);
        $this->assertSame(['Engineering'], $talk['categories']);
        $this->assertTrue($service['is_service_session']);
        $this->assertTrue($service['is_plenum_session']);
        $this->assertSame([], $service['speaker_ids']);
    }

    public function test_normalizes_external_identifiers_and_respects_timestamp_offsets(): void
    {
        $payload = $this->payload();
        $payload['all']['sessions'][0]['id'] = 101;
        $payload['grid'][0]['rooms'][0]['sessions'][0]['id'] = 101;
        $payload['grid'][0]['rooms'][0]['sessions'][0]['startsAt'] = '2027-06-01T09:15:00+02:00';

        $data = app(SessionizeNormalizer::class)->normalize($payload);
        $talk = collect($data->sessions)->firstWhere('sessionize_id', '101');

        $this->assertSame('101', $talk['sessionize_id']);
        $this->assertSame('2027-06-01T07:15:00+00:00', $talk['starts_at']?->toIso8601String());
        $this->assertSame('10', $data->rooms[0]['sessionize_id']);
    }

    public function test_rejects_sessions_without_a_usable_external_identifier(): void
    {
        $payload = $this->payload();
        $payload['all']['sessions'][0]['id'] = '';

        $this->expectException(RuntimeException::class);

        app(SessionizeNormalizer::class)->normalize($payload);
    }

    public function test_normalizes_category_labels_in_their_original_order(): void
    {
        $payload = $this->payload();
        $payload['all']['sessions'][0]['categoryItems'] = [
            ['name' => 'Engineering'],
            ['name' => ' Advanced '],
            ['name' => 'Engineering'],
            ['label' => 'Tech Talk: 40 min'],
            ['name' => '   '],
            [],
            'Malformed',
            42,
        ];

        $data = app(SessionizeNormalizer::class)->normalize($payload);
        $talk = collect($data->sessions)->firstWhere('sessionize_id', 'talk-1');

        $this->assertSame(['Engineering', 'Advanced', 'Tech Talk: 40 min'], $talk['categories']);
    }

    /** @return array{all: array<string, mixed>, grid: list<array<string, mixed>>} */
    private function payload(): array
    {
        return [
            'all' => $this->fixture('all.json'),
            'grid' => $this->fixture('grid-smart.json'),
        ];
    }

    /** @return array<mixed> */
    private function fixture(string $name): array
    {
        $contents = file_get_contents(base_path("tests/Fixtures/sessionize/{$name}"));

        $this->assertIsString($contents);

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
