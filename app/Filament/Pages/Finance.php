<?php

namespace App\Filament\Pages;

use App\Support\FinanceReport;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Validator;

/** Money in and out for a date range, with breakdowns and an accountant export. */
class Finance extends Page
{
    protected string $view = 'filament.pages.finance';

    protected static ?string $title = 'Finance';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 9;

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff', 'finance');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->from = now('Africa/Nairobi')->startOfYear()->toDateString();
        $this->to = now('Africa/Nairobi')->toDateString();
    }

    public function preset(string $range): void
    {
        $now = now('Africa/Nairobi');
        [$this->from, $this->to] = match ($range) {
            'month' => [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()],
            'last-month' => [$now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'quarter' => [$now->copy()->startOfQuarter()->toDateString(), $now->toDateString()],
            default => [$now->copy()->startOfYear()->toDateString(), $now->toDateString()],
        };
    }

    private function report(): FinanceReport
    {
        $check = Validator::make(['from' => $this->from, 'to' => $this->to], ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $this->resetErrorBag();
        if ($check->fails()) {
            foreach ($check->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return FinanceReport::forDays(now('Africa/Nairobi')->startOfYear()->toDateString(), now('Africa/Nairobi')->toDateString());
        }

        return FinanceReport::forDays($this->from, $this->to);
    }

    protected function getViewData(): array
    {
        $report = $this->report();

        return [
            'totals' => $report->totals(),
            'months' => $report->byMonth(),
            'courses' => $report->byCourse(),
            'banks' => $report->byBank(),
            'coupons' => $report->coupons(),
            'exportUrl' => route('finance.export', ['from' => $report->start->timezone('Africa/Nairobi')->toDateString(), 'to' => $report->end->timezone('Africa/Nairobi')->toDateString()]),
        ];
    }
}
