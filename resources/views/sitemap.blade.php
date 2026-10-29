<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach(['home','courses.index','about','faq','contact'] as $name)<url><loc>{{ route($name) }}</loc></url>@endforeach
@foreach($categories as $category)<url><loc>{{ route('categories.show',$category->slug) }}</loc></url>@endforeach
@foreach($courses as $course)<url><loc>{{ route('courses.show',$course->slug) }}</loc><lastmod>{{ $course->updated_at->toAtomString() }}</lastmod></url>@endforeach
</urlset>
