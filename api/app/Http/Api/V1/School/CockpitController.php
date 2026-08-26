<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Collection\Models\CollectionForecast;
use App\Domain\Collection\Models\CollectionTask;
use App\Domain\Collection\Models\RiskAssessment;
use App\Domain\Collection\Support\CollectionPayload;
use App\Domain\Collection\Support\QuietHours;
use App\Domain\Finance\Models\Installment;
use App\Domain\Finance\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class CockpitController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $today = QuietHours::today();
        $present = AttendanceRecord::query()->whereDate('date', $today)->where('status', AttendanceStatus::Present)->count();
        $absent = AttendanceRecord::query()->whereDate('date', $today)->where('status', AttendanceStatus::Absent)->count();
        $collected = (int) Payment::query()->whereDate('received_on', $today)->sum('amount');
        $outstanding = (int) Installment::query()->get()->sum(fn (Installment $row): int => $row->remainingAmount());

        $riskCounts = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        foreach (RiskAssessment::query()->with('factors')->get() as $assessment) {
            $riskCounts[$assessment->effectiveLevel()->value]++;
        }

        $tasks = CollectionTask::query()
            ->with('enrollment.person')
            ->whereIn('status', ['open', 'in_progress'])
            ->get()
            ->sortBy(function (CollectionTask $task): string {
                $rank = ['critical' => '0', 'high' => '1', 'medium' => '2', 'low' => '3'];

                return ($rank[$task->priority] ?? '9').$task->created_at?->toIso8601String();
            })
            ->take(3)
            ->values();

        $forecast = CollectionForecast::query()
            ->orderByDesc('week_starting_on')
            ->first();

        return response()->json([
            'as_of' => $today,
            'attendance' => [
                'present' => $present,
                'absent' => $absent,
            ],
            'collected_today' => $collected,
            'outstanding_amount' => $outstanding,
            'risk_counts' => $riskCounts,
            'forecast' => CollectionPayload::forecast($forecast),
            'actions' => $tasks->map(fn (CollectionTask $task): array => CollectionPayload::task($task))->all(),
        ]);
    }
}
