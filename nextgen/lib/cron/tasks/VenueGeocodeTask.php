<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/CronTaskInterface.php';
require_once dirname(__DIR__, 3) . '/events/lib/venue_request.php';
require_once dirname(__DIR__, 3) . '/events/lib/venue_geocode_runner.php';

final class VenueGeocodeTask implements CronTaskInterface
{
    public function name(): string
    {
        return 'venue_geocode';
    }

    public function label(): string
    {
        return 'Helyszín GPS geokódolás';
    }

    public function intervalSeconds(): int
    {
        return 300;
    }

    /**
     * @param array<string, mixed> $options all, batch_size, batches
     */
    public function run(array $options = []): array
    {
        $db = getDb();
        $pendingBefore = events_venues_geocode_candidates_count($db);
        if ($pendingBefore === 0) {
            return [
                'ok' => true,
                'message' => 'Nincs geokódolandó helyszín.',
                'data' => [
                    'pending_before' => 0,
                    'ok' => 0,
                    'fail' => 0,
                    'remaining' => 0,
                    'batches' => 0,
                    'done' => true,
                ],
            ];
        }

        $batchSize = EVENTS_VENUE_GEOCODE_DEFAULT_BATCH;
        if (isset($options['batch_size']) && is_numeric($options['batch_size'])) {
            $batchSize = max(1, min(25, (int) $options['batch_size']));
        }

        $maxBatches = 1;
        if (!empty($options['all'])) {
            $maxBatches = 0;
        } elseif (isset($options['batches']) && is_numeric($options['batches'])) {
            $maxBatches = max(1, (int) $options['batches']);
        }

        $result = events_venues_geocode_run_batches($db, $batchSize, $maxBatches);
        $summary = events_venues_geocode_result_summary($result);

        return [
            'ok' => true,
            'message' => $summary,
            'data' => array_merge(['pending_before' => $pendingBefore], $result),
        ];
    }
}
