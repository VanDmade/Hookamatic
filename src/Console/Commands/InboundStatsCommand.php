<?php

namespace VanDmade\Hookamatic\Console\Commands;

use VanDmade\Hookamatic\Reports\InboundReportBuilder;
use InvalidArgumentException;
use Throwable;

class InboundStatsCommand extends HookamaticCommand
{

    protected $signature = 'hookamatic:inbound-stats
        {report? : The type of report to generate}
        {--provider*= : Filter by a specific provider}
        {--event-type*= : Filter by a specific provider event type}
        {--pending : Include metrics on pending inbound events}
        {--processed : Include metrics on processed inbound events}
        {--failed : Include metrics on failed inbound events}
        {--date=* : Filter by a specific date in YYYY-MM-DD format; repeat for multiple dates}
        {--start-date= : Include metrics on or after this date in YYYY-MM-DD format}
        {--end-date= : Include metrics on or before this date in YYYY-MM-DD format}';
    protected $description = 'Display statistics for inbound webhook events.';

    public function __construct(
        private InboundReportBuilder $reportBuilder
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $rawOptions = $this->options();
            $options = $this->reportBuilder->normalizeOptions([
                'provider' => $rawOptions['provider'] ?? [],
                'event_type' => $rawOptions['event-type'] ?? [],
                'pending' => $rawOptions['pending'] ?? false,
                'processed' => $rawOptions['processed'] ?? false,
                'failed' => $rawOptions['failed'] ?? false,
                'date' => $rawOptions['date'] ?? [],
                'start_date' => $rawOptions['start-date'] ?? null,
                'end_date' => $rawOptions['end-date'] ?? null,
            ]);
            // Prevents the use of --date with --start-date or --end-date
            if (!empty($options['dates']) && (!is_null($options['start_date']) || !is_null($options['end_date']))) {
                $this->error('You cannot use --date with --start-date or --end-date. Please choose one method of date filtering.');
                return self::FAILURE;
            }
            // Prevents the use of --end-date without --start-date
            if (empty($options['start_date']) && !empty($options['end_date'])) {
                $this->error('You cannot use --end-date without --start-date. Please provide a start date.');
                return self::FAILURE;
            }
            // Prevents --start-date from being after --end-date
            if (!empty($options['end_date']) && $options['start_date'] > $options['end_date']) {
                $this->error('The start date cannot be later than the end date.');
                return self::FAILURE;
            }
            $query = $this->reportBuilder->build($options, $this->argument('report'));
            $firstEvent = (clone $query)->first();
            if (!$firstEvent) {
                $this->info('No inbound events found for the specified filters.');
                return self::SUCCESS;
            }
            $headers = $firstEvent->getAttributes();
            // Drops noisy columns not worth showing in a table
            unset($headers['headers'], $headers['payload'], $headers['updated_at']);
            $query->chunk(100, function($events) use ($headers) {
                $this->table(
                    array_keys($headers),
                    $events->map(function($event) use ($headers) {
                        return array_intersect_key($event->getAttributes(), $headers);
                    })->all()
                );
            });
            return self::SUCCESS;
        } catch (InvalidArgumentException $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        } catch (Throwable $error) {
            $this->error('An unexpected error occurred: '.$error->getMessage());
            return self::FAILURE;
        }
    }

}
