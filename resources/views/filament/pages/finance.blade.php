<x-filament-panels::page>
@php
    $etb = fn ($minor) => 'ETB '.number_format(((int) $minor) / 100, 2);
    $peak = max(1, $months->max('received') ?? 1);
@endphp
<div class="cla-dash">
    <section class="cla-card cla-range">
        <div class="cla-range-fields">
            <label>{{ __('From') }} <input type="date" wire:model.live="from"></label>
            <label>{{ __('To') }} <input type="date" wire:model.live="to"></label>
            @error('to')<p class="cla-error">{{ $message }}</p>@enderror
        </div>
        <div class="cla-range-presets">
            @foreach(['month' => 'This month', 'last-month' => 'Last month', 'quarter' => 'This quarter', 'year' => 'This year'] as $key => $label)<button type="button" class="cla-outline" wire:click="preset('{{ $key }}')">{{ __($label) }}</button>@endforeach
            <a class="cla-primary" href="{{ $exportUrl }}"><x-filament::icon icon="heroicon-o-arrow-down-tray" class="cla-btn-icon" />{{ __('Download CSV for accountant') }}</a>
        </div>
    </section>

    <div class="cla-stats cla-stats-6">
        @foreach([
            ['Money received', $etb($totals['received']), trans_choice(':count order|:count orders', $totals['orders'], ['count' => $totals['orders']]), 'heroicon-o-arrow-down-circle'],
            ['Refunded', $etb($totals['refunded']), trans_choice(':count refund|:count refunds', $totals['refund_count'], ['count' => $totals['refund_count']]), 'heroicon-o-arrow-uturn-left'],
            ['Net income', $etb($totals['net']), __('Received minus refunds'), 'heroicon-o-scale'],
            ['Coupon discounts', $etb($totals['discounts']), __('Given on paid orders'), 'heroicon-o-ticket'],
            ['VAT included', $etb($totals['vat']), __('Part of the money received'), 'heroicon-o-receipt-percent'],
            ['Waiting for approval', $etb($totals['pending']), trans_choice(':count payment|:count payments', $totals['pending_count'], ['count' => $totals['pending_count']]), 'heroicon-o-clock'],
        ] as [$label, $value, $note, $icon])
            <div class="cla-card cla-stat"><x-filament::icon :icon="$icon" class="cla-stat-icon" /><div><p class="cla-muted">{{ __($label) }}</p><strong>{{ $value }}</strong><small class="cla-stat-note">{{ $note }}</small></div></div>
        @endforeach
    </div>

    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-calendar" />{{ __('By month') }}</h2></header>
        <div class="cla-table-wrap"><table class="cla-table">
            <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Orders') }}</th><th>{{ __('Received') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($months as $month => $row)
                <tr><td>{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y') }}</td><td>{{ $row['orders'] }}</td><td>{{ $etb($row['received']) }}</td>
                    <td class="cla-bar-cell"><div class="cla-meter cla-meter-sm"><span style="width: {{ round(100 * $row['received'] / $peak) }}%"></span></div></td></tr>
            @empty
                <tr><td colspan="4" class="cla-empty">{{ __('No payments in this period.') }}</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <div class="cla-row cla-row-2">
        <section class="cla-card">
            <header><h2><x-filament::icon icon="heroicon-o-book-open" />{{ __('By course') }}</h2></header>
            <div class="cla-table-wrap"><table class="cla-table">
                <thead><tr><th>{{ __('Course') }}</th><th>{{ __('Orders') }}</th><th>{{ __('Refunds') }}</th><th>{{ __('Received') }}</th></tr></thead>
                <tbody>@forelse($courses as $row)<tr><td>{{ $row->course }}</td><td>{{ $row->orders }}</td><td>{{ $row->refunds }}</td><td>{{ $etb($row->received) }}</td></tr>@empty<tr><td colspan="4" class="cla-empty">{{ __('No sales yet.') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>
        <section class="cla-card">
            <header><h2><x-filament::icon icon="heroicon-o-building-library" />{{ __('By bank') }}</h2></header>
            <div class="cla-table-wrap"><table class="cla-table">
                <thead><tr><th>{{ __('Bank') }}</th><th>{{ __('Orders') }}</th><th>{{ __('Received') }}</th></tr></thead>
                <tbody>@forelse($banks as $row)<tr><td>{{ $row->bank }}</td><td>{{ $row->orders }}</td><td>{{ $etb($row->received) }}</td></tr>@empty<tr><td colspan="3" class="cla-empty">{{ __('No bank payments yet.') }}</td></tr>@endforelse</tbody>
            </table></div>
            <header class="cla-subhead"><h2><x-filament::icon icon="heroicon-o-ticket" />{{ __('Coupons used') }}</h2></header>
            <div class="cla-table-wrap"><table class="cla-table">
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Uses') }}</th><th>{{ __('Discount given') }}</th></tr></thead>
                <tbody>@forelse($coupons as $row)<tr><td><b>{{ $row->code }}</b></td><td>{{ $row->uses }}</td><td>{{ $etb($row->discount) }}</td></tr>@empty<tr><td colspan="3" class="cla-empty">{{ __('No coupons used.') }}</td></tr>@endforelse</tbody>
            </table></div>
        </section>
    </div>
    <p class="cla-muted">{{ __('“Received” counts payments verified in the period; refunds count refunds approved in the period. Amounts are what learners paid (VAT-inclusive). This is a management report, not a tax statement.') }}</p>
</div>
</x-filament-panels::page>
