<?php

namespace VanDmade\Hookamatic\Console\Commands;

use VanDmade\Hookamatic\Enums\DeliveryStatus;
use VanDmade\Hookamatic\Events\HookamaticLog;
use VanDmade\Hookamatic\Models\Delivery;
use VanDmade\Hookamatic\Reports\OutboundReportBuilder;
use VanDmade\Hookamatic\Services\DeliveryService;
use VanDmade\Hookamatic\Services\SubscriberService;
use InvalidArgumentException;
use Throwable;

class RetryFailedDeliveriesCommand extends HookamaticCommand
{

    protected $signature = 'hookamatic:retry-failed-deliveries
        {--event-type*= : Filter by a specific event type, ID only}
        {--subscribers*= : Filter by a specific subscriber, ID only}
        {--priority*= : Filter by a specific priority level}
        {--date=* : Filter by a specific date in YYYY-MM-DD format; repeat for multiple dates}
        {--start-date= : Include metrics on or after this date in YYYY-MM-DD format}
        {--end-date= : Include metrics on or before this date in YYYY-MM-DD format}
        {--re-enable-subscriber : Re-enable subscribers that have been disabled due to failed deliveries}';
    protected $description = 'Retry failed webhook deliveries.';

    public function __construct(
        private OutboundReportBuilder $reportBuilder,
        private DeliveryService $deliveryService,
        private SubscriberService $subscriberService
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
                'priority' => $rawOptions['priority'] ?? [],
                'date' => $rawOptions['date'] ?? [],
                'start_date' => $rawOptions['start-date'] ?? null,
                'end_date' => $rawOptions['end-date'] ?? null,
            ]);
            if (!empty($options['dates']) && (!is_null($options['start_date']) || !is_null($options['end_date']))) {
                // Prevents the use of --date with --start-date or --end-date
                $this->error('You cannot use --date with --start-date or --end-date. Please choose one method of date filtering.');
                return self::FAILURE;
            }
            if (empty($options['start_date']) && !empty($options['end_date'])) {
                // Prevents the use of --end-date without --start-date
                $this->error('You cannot use --end-date without --start-date. Please provide a start date.');
                return self::FAILURE;
            }
            if (!empty($options['end_date']) && $options['start_date'] > $options['end_date']) {
                // Prevents the user of a start date that is later than the end date
                $this->error('The start date cannot be later than the end date.');
                return self::FAILURE;
            }
            $query = $this->reportBuilder->generateWhereClause(
                Delivery::where('status', '=', DeliveryStatus::EXHAUSTED),
                $options
            );
            $exhaustedDeliveries = $query->get();
            if ($exhaustedDeliveries->isEmpty()) {
                $this->info('No exhausted deliveries matched the given filters.');
                return self::SUCCESS;
            }
            // Determines if the subscribers are to be re-enabled or not.
            $enableSubscribers = $this->option('re-enable-subscriber');
            $subscribersToEnable = [];
            foreach ($exhaustedDeliveries as $delivery) {
                $this->deliveryService->reviveExhausted($delivery);
                if ($enableSubscribers && $delivery->subscriber?->disabled_by_system) {
                    $subscribersToEnable[$delivery->subscriber_id] = true;
                }
            }
            foreach (array_keys($subscribersToEnable) as $subscriberId) {
                $this->subscriberService->markAsEnabled($subscriberId);
            }
            $this->info($exhaustedDeliveries->count().' exhausted deliveries revived.');
            if (!empty($subscribersToEnable)) {
                $this->info(count($subscribersToEnable).' subscribers re-enabled.');
            }
            return self::SUCCESS;
        } catch (InvalidArgumentException $error) {
            HookamaticLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            $this->error($error->getMessage());
            return self::FAILURE;
        } catch (Throwable $error) {
            HookamaticLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            $this->error('An unexpected error occurred: '.$error->getMessage());
            return self::FAILURE;
        }
    }

}
