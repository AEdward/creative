<?php

use App\Http\Controllers\AcademyController;
use App\Models\Category;
use App\Models\Course;
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
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /admin\nDisallow: /academy-admin\nDisallow: /instructor\nDisallow: /learn/\nDisallow: /profile\nDisallow: /dashboard\nDisallow: /my-learning\nDisallow: /payments\nDisallow: /certificates\nDisallow: /support\nSitemap: ".route('sitemap')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']));
Route::middleware(['auth', 'verified', 'can:administer-academy'])->prefix('/academy-admin')->group(function (): void {
    // The operations page moved into the admin panel (Enrollments, Coupons, Analytics, Site settings).
    Route::get('/', fn () => redirect('/admin/enrollments'))->name('academy-admin');
    Route::get('/export', [AcademyController::class, 'export'])->middleware('throttle:5,1,exports')->name('academy-admin.export');
});
