<?php

namespace VanDmade\Hookamatic\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use VanDmade\Hookamatic\Enums\InboundStatus;
use VanDmade\Hookamatic\Enums\ReportTypes\Inbound;
use VanDmade\Hookamatic\Events\HookamaticLog;
use VanDmade\Hookamatic\Models\InboundEvent;
use InvalidArgumentException;
use Exception;

class InboundReportBuilder
{

    public function build(array $options, ?string $report): Builder
    {
        $query = $this->generateWhereClause(InboundEvent::query(), $options);
        switch ($report) {
            case 'totals':
                $query = $query->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->orderBy('total', 'desc');
                break;
            case 'totals_by_provider':
                $query = $query->selectRaw('provider, COUNT(*) as total')
                    ->groupBy('provider')
                    ->orderBy('total', 'desc');
                break;
            // Default case for summary or detailed reports
            case 'summary':
            case null:
                // Just outputs all of the data and orders it by the most recent inbound events first
                $query = $query->orderBy('created_at', 'desc');
                break;
            default:
                $reportTypes = array_column(Inbound::cases(), 'value');
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
            'providers' => $options['provider'] ?? [],
            'event_types' => $options['event_type'] ?? [],
            'pending' => $options['pending'] ?? false,
            'processed' => $options['processed'] ?? false,
            'failed' => $options['failed'] ?? false,
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
            ->when(!empty($options['providers']), function($query) use ($options) {
                $query->whereIn('provider', $options['providers']);
            })
            ->when(!empty($options['event_types']), function($query) use ($options) {
                $query->whereIn('event_type', $options['event_types']);
            })
            ->when(($options['pending'] ?? false) || ($options['processed'] ?? false) ||
                    ($options['failed'] ?? false), function($query) use ($options) {
                $statuses = [];
                if ($options['pending'] ?? false) {
                    $statuses[] = InboundStatus::PENDING;
                }
                if ($options['processed'] ?? false) {
                    $statuses[] = InboundStatus::PROCESSED;
                }
                if ($options['failed'] ?? false) {
                    $statuses[] = InboundStatus::FAILED;
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
