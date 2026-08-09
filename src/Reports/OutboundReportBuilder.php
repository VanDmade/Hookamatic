<?php

namespace VanDmade\Hookamatic\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use VanDmade\Hookamatic\Enums\DeliveryStatus;
use VanDmade\Hookamatic\Enums\Priority;
use VanDmade\Hookamatic\Enums\ReportTypes\Outbound;
use VanDmade\Hookamatic\Events\HookamaticLog;
use VanDmade\Hookamatic\Models\Delivery;
use InvalidArgumentException;
use Exception;

class OutboundReportBuilder
{

    public function build(array $options, ?string $report): Builder
    {
        $query = $this->generateWhereClause(Delivery::query(), $options);
        switch ($report) {
            case 'totals':
                $query = $query->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->orderBy('total', 'desc');
                break;
            case 'totals_by_subscriber':
                $query = $query->selectRaw('subscriber_id, COUNT(*) as total')
                    ->groupBy('subscriber_id')
                    ->orderBy('total', 'desc');
                break;
            case 'totals_by_event_type':
                $query = $query
                    ->join('hookamatic_outbound_events', 'hookamatic_deliveries.outbound_event_id', '=', 'hookamatic_outbound_events.id')
                    ->join('hookamatic_event_types', 'hookamatic_outbound_events.event_type_id', '=', 'hookamatic_event_types.id')
                    ->selectRaw('hookamatic_event_types.name as event_type, COUNT(*) as total')
                    ->groupBy('hookamatic_event_types.name')
                    ->orderBy('total', 'desc');
                break;
            // Default case for summary or detailed reports
            case 'summary':
            case null:
                // Just outputs all of the data and orders it by the most recent deliveries first
                $query = $query->orderBy('created_at', 'desc');
                break;
            default:
                $reportTypes = array_column(Outbound::cases(), 'value');
                $message = 'Invalid report type specified. Please use one of the following: '.implode(', ', $reportTypes).'.';
                HookamaticLog::dispatch('error', $message, [
                    'report_type' => $report,
                    'valid_report_types' => $reportTypes,
                    'options' => $options,
                ]);
                throw new InvalidArgumentException($message);
        }
        return $query;
    }

    public function normalizeOptions(array $options): array
    {
        return [
            'event_types' => $options['event_type'] ?? [],
            'subscriber_ids' => $options['subscribers'] ?? [],
            'organization_ids' => $options['organization'] ?? [],
            'priorities' => collect($options['priority'] ?? [])
                ->map(fn($name) => $this->resolvePriority($name))
                ->all(),
            'pending' => $options['pending'] ?? false,
            'sent' => $options['sent'] ?? false,
            'failed' => $options['failed'] ?? false,
            'exhausted' => $options['exhausted'] ?? false,
            'paused' => $options['paused'] ?? false,
            'dates' => collect($options['date'] ?? [])
                ->map(fn($d) => $this->parseDate($d, 'date'))
                ->all(),
            'start_date' => $this->parseDate($options['start_date'] ?? null, 'start-date'),
            'end_date' => $this->parseDate($options['end_date'] ?? null, 'end-date'),
        ];
    }

    public function generateWhereClause(Builder $query, array $options): Builder
    {
        return $query
            ->when(!empty($options['event_types']), function($query) use ($options) {
                $query->whereHas('eventType', fn($query) => $query->whereIn('id', $options['event_types']));
            })
            ->when(!empty($options['subscriber_ids']), function($query) use ($options) {
                $query->whereIn('subscriber_id', $options['subscriber_ids']);
            })
            ->when(!empty($options['organization_ids']), function($query) use ($options) {
                $query->whereHas('subscriber', fn($query) => $query->whereIn('organization_id', $options['organization_ids']));
            })
            ->when(!empty($options['priorities']), function($query) use ($options) {
                $query->whereIn('priority', array_map(fn($priority) => $priority->value, $options['priorities']));
            })
            ->when(($options['pending'] ?? false) || ($options['sent'] ?? false) ||
                    ($options['failed'] ?? false) || ($options['exhausted'] ?? false) ||
                    ($options['paused'] ?? false), function($query) use ($options) {
                $statuses = [];
                if ($options['pending'] ?? false) {
                    $statuses[] = DeliveryStatus::PENDING;
                }
                if ($options['sent'] ?? false) {
                    $statuses[] = DeliveryStatus::SENT;
                }
                if ($options['failed'] ?? false) {
                    $statuses[] = DeliveryStatus::FAILED;
                }
                if ($options['exhausted'] ?? false) {
                    $statuses[] = DeliveryStatus::EXHAUSTED;
                }
                if ($options['paused'] ?? false) {
                    $statuses[] = DeliveryStatus::PAUSED;
                }
                $query->when(!empty($statuses), fn($query) => $query->whereIn('status', $statuses));
            })
            ->when(!empty($options['dates']), function($query) use ($options) {
                $query->where(function($query) use ($options) {
                    foreach ($options['dates'] as $date) {
                        $query->orWhereDate('created_at', $date);
                    }
                });
            })
            ->when(!empty($options['start_date']), function($query) use ($options) {
                $query->whereDate('created_at', '>=', $options['start_date']);
            })
            ->when(!empty($options['end_date']), function($query) use ($options) {
                $query->whereDate('created_at', '<=', $options['end_date']);
            });
    }

    private function resolvePriority(string $name): Priority
    {
        foreach (Priority::cases() as $case) {
            if (strtolower($case->name) === strtolower($name)) {
                return $case;
            }
        }
        $message = 'Unknown priority level: '.$name;
        HookamaticLog::dispatch('error', $message, ['priority' => $name]);
        throw new InvalidArgumentException($message);
    }

    private function parseDate(?string $input, string $option): ?string
    {
        if (blank($input)) {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('Y-m-d', $input);
        } catch (Exception $error) {
            $message = 'Invalid date format for --'.$option.'. Please use YYYY-MM-DD.';
            HookamaticLog::dispatch('error', $message, ['option' => $option, 'value' => $input]);
            throw new InvalidArgumentException($message, 0, $error);
        }
        if ($date->format('Y-m-d') !== $input || $date->year < 1) {
            $message = 'Invalid date format for --'.$option.'. Please use YYYY-MM-DD.';
            HookamaticLog::dispatch('error', $message, ['option' => $option, 'value' => $input]);
            throw new InvalidArgumentException($message);
        }
        return $date->toDateString();
    }

}
