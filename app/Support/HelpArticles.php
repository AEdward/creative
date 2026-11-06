<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Str;

/** Short help answers the chat bubble searches before a learner starts a chat. */
class HelpArticles
{
    /** @return list<array{q: string, a: string, url: ?string}> */
    public static function all(): array
    {
        $articles = [
            ['How do I pay for a course?', 'Open the course and click “Buy this course”. Choose a bank, transfer the exact amount, then enter the transaction ID or upload a screenshot of the receipt. The course unlocks when our team confirms the payment.', null],
            ['How long does payment confirmation take?', 'We check bank transfers by hand, usually within a few hours during working days. You get a notification and an email as soon as it is confirmed.', null],
            ['My payment was rejected', 'Open Orders & payments to see the reason. If the money did leave your account, message support — we can reopen the payment without you sending it again.', null],
            ['Why is the next lesson locked?', 'Courses go step by step. Finish the current step to unlock the next: read every page, watch the whole video, pass the quiz, or submit your assignment.', null],
            ['I can’t skip ahead in a video', 'Videos must be watched in full to unlock the next step. You can always go back and rewatch; you just can’t jump past the part you haven’t watched yet.', null],
            ['I failed a quiz', 'You can retry a quiz as many times as you need. Review the lesson, then try again — passing unlocks the next step straight away.', null],
            ['How do I submit my assignment?', 'Open the assignment step, choose your file and click “Submit work”. Depending on the course, the next step unlocks right away or after your work is reviewed.', null],
            ['How do I get my certificate?', 'Finish every step of a course that offers a certificate, then open Certificates to view and download it.', route('certificates.index', absolute: false)],
            ['Can I get a refund?', 'Open Orders & payments and click “Request a refund” on the order, with a short reason. Our team reviews each request.', route('refund-policy', absolute: false)],
            ['How do I change my password or email?', 'Go to Profile. If you forgot your password, use “Forgot password” on the sign-in page.', route('profile', absolute: false)],
            ['Can I learn in Amharic?', 'Yes — switch the site language with EN / አማ at the top. Each course page also says which language the course is taught in.', null],
            ['Video won’t play', 'Click “Refresh playback” under the video. If it still won’t play, check your connection and try another browser, then message support.', null],
        ];
        $extra = trim((string) (SiteSetting::content()['faq'] ?? ''));
        if ($extra !== '') {
            $articles[] = ['More information', $extra, route('faq', absolute: false)];
        }

        return array_map(fn ($a) => ['q' => __($a[0]), 'a' => __($a[1]), 'url' => $a[2]], $articles);
    }

    /** Best matches for a question (word overlap; question words count double). @return list<array> */
    public static function search(string $query, int $limit = 4): array
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower($query)))->filter(fn ($w) => mb_strlen($w) > 2)->unique();
        if ($words->isEmpty()) {
            return array_slice(self::all(), 0, $limit);
        }

        return collect(self::all())
            ->map(fn ($a) => $a + ['score' => $words->sum(fn ($w) => 2 * substr_count(Str::lower($a['q']), $w) + substr_count(Str::lower($a['a']), $w))])
            ->filter(fn ($a) => $a['score'] > 0)->sortByDesc('score')->take($limit)
            ->map(fn ($a) => array_diff_key($a, ['score' => 1]))->values()->all();
    }
}
