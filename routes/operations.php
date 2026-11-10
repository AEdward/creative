<?php

use App\Http\Controllers\AcademyController;
use App\Models\Category;
use App\Models\Course;
use App\Support\Admin;
use App\Support\FinanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/locale', function (Request $request) {
    $data = $request->validate(['locale' => ['required', 'in:en,am']]);
    $request->session()->put('locale', $data['locale']);

    return back();
})->middleware('throttle:30,1,locale')->name('locale');
Route::get('/sitemap.xml', function () {
    return response()->view('sitemap', ['courses' => Course::published()->orderBy('id')->get(['slug', 'updated_at']), 'categories' => Category::where('active', true)->get(['slug'])])->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /academy-admin\nDisallow: /instructor\nDisallow: /learn/\nDisallow: /profile\nDisallow: /dashboard\nDisallow: /my-learning\nDisallow: /payments\nDisallow: /certificates\nDisallow: /support\nDisallow: /checkout/\nDisallow: /messages\nDisallow: /notifications\nDisallow: /chat/\nDisallow: /two-factor\nSitemap: ".route('sitemap')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']));
Route::middleware(['auth', 'verified', 'can:administer-academy'])->prefix('/academy-admin')->group(function (): void {
    // The operations page moved into the admin panel (Enrollments, Coupons, Analytics, Site settings).
    Route::get('/', fn () => redirect(Admin::url('enrollments')))->name('academy-admin');
    Route::get('/export', [AcademyController::class, 'export'])->middleware('throttle:5,1,exports')->name('academy-admin.export');
});

// Finance staff: one CSV row per order in a date range, for the accountant.
Route::middleware(['auth', 'verified', 'throttle:10,1,finance.export'])->get('/admin-exports/finance', function (Request $request) {
    abort_unless($request->user()->can('staff', 'finance'), 403);
    $data = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
    $report = FinanceReport::forDays($data['from'], $data['to']);

    return response()->streamDownload(function () use ($report): void {
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Amharic names correctly
        foreach ($report->csvRows() as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
    }, 'creative-lab-finance-'.$data['from'].'-to-'.$data['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
})->name('finance.export');
