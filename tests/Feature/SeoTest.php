<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\User;
use App\Support\AcademySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'] ?? [])->keyBy('@type')->all();
    }

    public function test_home_has_social_preview_tags_and_organization_data(): void
    {
        $html = $this->get('/')->assertOk()
            ->assertSee('<meta property="og:image" content="'.route('share.site').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->getContent();
        $data = $this->jsonLd($html);
        $this->assertSame('Creative Lab Academy', $data['EducationalOrganization']['name']);
        $this->assertSame(route('courses.index').'?q={search_term_string}', $data['WebSite']['potentialAction']['target']);

        $this->get(route('share.site'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        [$w, $h] = getimagesize($this->get(route('share.site'))->baseResponse->getFile()->getPathname());
        $this->assertSame([1200, 630], [$w, $h]);
    }

    public function test_course_page_describes_the_course_for_search_engines(): void
    {
        $category = Category::factory()->create(['name' => 'Video Editing', 'slug' => 'video-editing', 'active' => true]);
        $course = Course::factory()->create(['title' => 'Premiere Pro Basics', 'summary' => 'Edit your first film.', 'category_id' => $category->id, 'is_free' => false, 'price_minor' => 150000, 'language' => 'am', 'level' => 'beginner', 'outcomes' => ['Cut a short film']]);
        $course->modules()->create(['title' => 'C'])->lessons()->create(['title' => 'L', 'type' => 'text', 'body' => 'x', 'published' => true, 'duration_seconds' => 600]);
        CourseReview::create(['course_id' => $course->id, 'user_id' => User::factory()->create()->id, 'rating' => 4, 'body' => 'Good', 'status' => 'approved']);

        $html = $this->get(route('courses.show', $course->slug))->assertOk()
            ->assertSee('<meta property="og:image" content="'.route('share.course', $course->slug).'">', false)->getContent();
        $data = $this->jsonLd($html);
        $this->assertSame('Premiere Pro Basics', $data['Course']['name']);
        $this->assertSame(['price' => '1500.00', 'priceCurrency' => 'ETB'], array_intersect_key($data['Course']['offers'], ['price' => 1, 'priceCurrency' => 1]));
        $this->assertSame('am', $data['Course']['inLanguage']);
        $this->assertSame('PT10M', $data['Course']['hasCourseInstance']['courseWorkload']);
        $this->assertSame(1, $data['Course']['aggregateRating']['reviewCount']);
        $this->assertCount(3, $data['BreadcrumbList']['itemListElement']);
        $this->get(route('share.course', $course->slug))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $draft = Course::factory()->create(['status' => 'draft', 'published_at' => null]);
        $this->get(route('share.course', $draft->slug))->assertNotFound();
    }

    public function test_faq_sitemap_robots_llms_and_private_pages(): void
    {
        $this->get('/faq')->assertOk()->assertSee('How do I pay for a course?');
        $this->assertArrayHasKey('FAQPage', $this->jsonLd($this->get('/faq')->getContent()));

        $course = Course::factory()->create(['title' => 'Blender for Beginners', 'summary' => 'Model in 3D.']);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('refund-policy'))->assertSee('<image:loc>'.route('share.course', $course->slug).'</image:loc>', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /checkout/')->assertSee('Sitemap: '.route('sitemap'));
        $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('# Creative Lab Academy')->assertSee('Blender for Beginners')->assertSee(route('courses.show', $course->slug));

        // Searched / filtered catalogue pages and signed-in pages stay out of search results.
        $this->get('/courses?q=blender')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/courses')->assertDontSee('noindex', false);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_search_console_verification_codes_come_from_site_settings(): void
    {
        AcademySettings::save(['google_site_verification' => 'abc123XYZ', 'bing_site_verification' => 'B1NG']);
        $this->get('/')->assertSee('<meta name="google-site-verification" content="abc123XYZ">', false)->assertSee('<meta name="msvalidate.01" content="B1NG">', false);
    }
}
