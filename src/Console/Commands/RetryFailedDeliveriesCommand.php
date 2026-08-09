<?php

namespace VanDmade\Hookamatic\Console\Commands;

use VanDmade\Hookamatic\Enums\DeliveryStatus;
use VanDmade\Hookamatic\Enums\Priority;
use VanDmade\Hookamatic\Models\Delivery;
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
        private DeliveryService $deliveryService,
        private SubscriberService $subscriberService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $dates = $this->option('date');
            $startDate = $this->option('start-date');
            $endDate = $this->option('end-date');
            if (!empty($dates) && (!is_null($startDate) || !is_null($endDate))) {
                // Prevents the use of --date with --start-date or --end-date
                $this->error('You cannot use --date with --start-date or --end-date. Please choose one method of date filtering.');
                return self::FAILURE;
            }
            if (empty($startDate) && !empty($endDate)) {
                // Prevents the use of --end-date without --start-date
                $this->error('You cannot use --end-date without --start-date. Please provide a start date.');
                return self::FAILURE;
            }
            if (!empty($endDate) && $startDate > $endDate) {
                // Prevents the user of a start date that is later than the end date
                $this->error('The start date cannot be later than the end date.');
                return self::FAILURE;
            }
            $query = Delivery::where('status', '=', DeliveryStatus::EXHAUSTED);
            if (!empty($eventTypes = $this->option('event-type'))) {
                $query->whereHas('eventType', fn($query) => $query->whereIn('id', $eventTypes));
            }
            if (!empty($subscriberIds = $this->option('subscribers'))) {
                $query->whereIn('subscriber_id', $subscriberIds);
            }
            if (!empty($priorities = $this->option('priority'))) {
                $priorities = array_map(fn($name) => $this->resolvePriority($name)->value, $priorities);
                $query->whereIn('priority', $priorities);
            }
            if (!empty($dates)) {
                $query->where(function($query) use ($dates) {
                    foreach ($dates as $date) {
                        $query->orWhereDate('created_at', $date);
                    }
                });
            } elseif (!empty($startDate)) {
                $query->whereDate('created_at', '>=', $startDate);
                if (!empty($endDate)) {
                    $query->whereDate('created_at', '<=', $endDate);
                }
            }
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
            $this->error($error->getMessage());
            return self::FAILURE;
        } catch (Throwable $error) {
            $this->error('An unexpected error occurred: '.$error->getMessage());
            return self::FAILURE;
        }
    }

    private function resolvePriority(string $name): Priority
    {
        foreach (Priority::cases() as $case) {
            if (strtolower($case->name) === strtolower($name)) {
                return $case;
            }
        }
        throw new InvalidArgumentException('Unknown priority level: '.$name);
    }

}
