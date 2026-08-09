<?php

namespace VanDmade\Hookamatic\Console\Commands;

use VanDmade\Hookamatic\Reports\OutboundReportBuilder;
use InvalidArgumentException;
use Throwable;

class OutboundStatsCommand extends HookamaticCommand
{

    protected $signature = 'hookamatic:outbound-stats
        {report? : The type of report to generate}
        {--event-type*= : Filter by a specific event type, ID only}
        {--subscribers*= : Filter by a specific subscriber, ID only}
        {--organization*= : Filter by a specific organization, ID only}
        {--priority*= : Filter by a specific priority level}
        {--pending : Include metrics on pending deliveries}
        {--sent : Include metrics on sent deliveries}
        {--failed : Include metrics on failed deliveries}
        {--exhausted : Include metrics on exhausted deliveries}
        {--paused : Include metrics on paused deliveries}
        {--date=* : Filter by a specific date in YYYY-MM-DD format; repeat for multiple dates}
        {--start-date= : Include metrics on or after this date in YYYY-MM-DD format}
        {--end-date= : Include metrics on or before this date in YYYY-MM-DD format}';
    protected $description = 'Display statistics for outbound webhook deliveries.';

    public function __construct(
        private OutboundReportBuilder $reportBuilder
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $rawOptions = $this->options();
            $options = $this->reportBuilder->normalizeOptions([
                'event_type' => $rawOptions['event-type'] ?? [],
                'subscribers' => $rawOptions['subscribers'] ?? [],
                'organization' => $rawOptions['organization'] ?? [],
                'priority' => $rawOptions['priority'] ?? [],
                'pending' => $rawOptions['pending'] ?? false,
                'sent' => $rawOptions['sent'] ?? false,
                'failed' => $rawOptions['failed'] ?? false,
                'exhausted' => $rawOptions['exhausted'] ?? false,
                'paused' => $rawOptions['paused'] ?? false,
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
            $firstDelivery = (clone $query)->first();
            if (!$firstDelivery) {
                $this->info('No deliveries found for the specified filters.');
                return self::SUCCESS;
            }
            $headers = $firstDelivery->getAttributes();
            // Drops noisy columns not worth showing in a table
            unset($headers['request_payload'], $headers['response_body'], $headers['updated_at']);
            $query->chunk(100, function($deliveries) use ($headers) {
                $this->table(
                    array_keys($headers),
                    $deliveries->map(function($delivery) use ($headers) {
                        return array_intersect_key($delivery->getAttributes(), $headers);
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
