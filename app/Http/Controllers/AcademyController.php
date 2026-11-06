<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Enrollment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademyController extends Controller
{
    private function dates(Request $request): array
    {
        $validator = Validator::make($request->all(), ['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route('academy-admin'));
        }
        $data = $validator->validated();
        $start = CarbonImmutable::parse($data['from'] ?? now('Africa/Nairobi')->startOfMonth()->toDateString(), 'Africa/Nairobi')->startOfDay()->utc();
        $end = CarbonImmutable::parse($data['to'] ?? now('Africa/Nairobi')->toDateString(), 'Africa/Nairobi')->endOfDay()->utc();
        abort_unless($end->greaterThanOrEqualTo($start) && $start->diffInDays($end) <= 366, 422, 'Choose a date range of up to one year.');

        return [$start, $end];
    }

    public function export(Request $request): StreamedResponse
    {
        [$start,$end] = $this->dates($request);

        return response()->streamDownload(function () use ($start, $end): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Learner', 'Course', 'Status', 'Enrolled UTC'], ',', '"', '');
            Enrollment::with('user', 'course')->whereBetween('enrolled_at', [$start, $end])->orderBy('id')->chunkById(100, function ($rows) use ($stream): void {
                foreach ($rows as $row) {
                    $safe = fn (string $value): string => preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
                    fputcsv($stream, [$safe($row->user->name), $safe($row->course->title), $row->status, $row->enrolled_at->toIso8601String()], ',', '"', '');
                }
            });
            fclose($stream);
        }, 'academy-enrollments.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    private function audit(Request $request, string $action, string $type, int $id, array $changes): void
    {
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'changes' => $changes]);
    }
}
