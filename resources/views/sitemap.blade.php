<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach(['home' => 'daily', 'courses.index' => 'daily', 'about' => 'monthly', 'faq' => 'monthly', 'contact' => 'monthly', 'privacy' => 'yearly', 'terms' => 'yearly', 'refund-policy' => 'yearly', 'cookies' => 'yearly'] as $name => $freq)<url><loc>{{ route($name) }}</loc><changefreq>{{ $freq }}</changefreq></url>
@endforeach
@foreach($categories as $category)<url><loc>{{ route('categories.show', $category->slug) }}</loc><changefreq>weekly</changefreq></url>
@endforeach
@foreach($courses as $course)<url><loc>{{ route('courses.show', $course->slug) }}</loc><lastmod>{{ $course->updated_at->toAtomString() }}</lastmod><image:image><image:loc>{{ route('share.course', $course->slug) }}</image:loc></image:image></url>
@endforeach
</urlset>
