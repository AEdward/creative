<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseReview;

/**
 * Structured data (schema.org JSON-LD) for search engines and AI answer engines: who the academy is,
 * what each course is (price, language, level, rating, length), breadcrumbs and FAQs.
 */
class Seo
{
    public const NAME = 'Creative Lab Academy';

    public static function organization(): array
    {
        $contact = AcademySettings::all();

        return array_filter([
            '@type' => 'EducationalOrganization',
            '@id' => url('/').'#organization',
            'name' => self::NAME,
            'url' => url('/'),
            'logo' => asset('images/brand/logo-full.png'),
            'description' => 'Online academy in Ethiopia teaching video editing, graphic design, motion design, 3D (Blender) and digital marketing through practical, project-based courses in English and Amharic.',
            'areaServed' => 'ET',
            'email' => $contact['contact_email'] ?: null,
            'telephone' => $contact['contact_phone'] ?: null,
            'address' => $contact['contact_address'] ? ['@type' => 'PostalAddress', 'streetAddress' => $contact['contact_address'], 'addressCountry' => 'ET'] : null,
        ]);
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => self::NAME,
            'url' => url('/'),
            'inLanguage' => ['en', 'am'],
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => ['@type' => 'SearchAction', 'target' => route('courses.index').'?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
        ];
    }

    public static function course(Course $course): array
    {
        $reviews = CourseReview::where('course_id', $course->id)->where('status', 'approved');
        $count = (clone $reviews)->count();
        $lessons = $course->lessons()->where('lessons.published', true);
        $minutes = (int) ceil((clone $lessons)->sum('duration_seconds') / 60);

        return array_filter([
            '@type' => 'Course',
            '@id' => route('courses.show', $course->slug).'#course',
            'name' => $course->title,
            'description' => $course->summary ?: str($course->description)->limit(300)->toString(),
            'url' => route('courses.show', $course->slug),
            'image' => route('share.course', $course->slug),
            'inLanguage' => $course->language === 'am' ? 'am' : 'en',
            'educationalLevel' => ucfirst((string) $course->level),
            'teaches' => array_values(array_slice($course->outcomes ?? [], 0, 8)) ?: null,
            'coursePrerequisites' => array_values(array_slice($course->requirements ?? [], 0, 6)) ?: null,
            'provider' => ['@id' => url('/').'#organization'],
            'instructor' => $course->instructor_display_name ? ['@type' => 'Person', 'name' => $course->instructor_display_name] : null,
            'numberOfLessons' => (clone $lessons)->count() ?: null,
            'offers' => [
                '@type' => 'Offer',
                'category' => $course->is_free ? 'Free' : 'Paid',
                'price' => $course->is_free ? '0' : number_format($course->price_minor / 100, 2, '.', ''),
                'priceCurrency' => $course->currency ?: 'ETB',
                'availability' => 'https://schema.org/InStock',
                'url' => route('courses.show', $course->slug),
            ],
            'hasCourseInstance' => array_filter([
                '@type' => 'CourseInstance',
                'courseMode' => 'Online',
                'courseWorkload' => $minutes > 0 ? 'PT'.$minutes.'M' : null,
            ]),
            'aggregateRating' => $count > 0 ? ['@type' => 'AggregateRating', 'ratingValue' => round((float) (clone $reviews)->avg('rating'), 1), 'reviewCount' => $count, 'bestRating' => 5] : null,
        ], fn ($v) => $v !== null);
    }

    /** @param  array<string, string>  $trail  name => url */
    public static function breadcrumbs(array $trail): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(fn ($name, $url, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url], array_keys($trail), $trail, array_keys(array_keys($trail)))),
        ];
    }

    /** @param  list<array{q: string, a: string}>  $items */
    public static function faq(array $items): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($i) => ['@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']]], $items),
        ];
    }

    /** One <script type="application/ld+json"> holding all the given nodes. */
    public static function script(array $nodes): string
    {
        $nodes = array_values(array_filter($nodes));
        if ($nodes === []) {
            return '';
        }
        $json = json_encode(['@context' => 'https://schema.org', '@graph' => $nodes], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return '<script type="application/ld+json">'.$json.'</script>';
    }
}
