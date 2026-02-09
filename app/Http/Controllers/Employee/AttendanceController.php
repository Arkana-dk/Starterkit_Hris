<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\AttendancePhoto;
use App\Models\Employee;
use App\Models\WorkLocation;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $employee = Employee::with(['company:id,name'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $today = Carbon::today();
        $schedule = WorkSchedule::with(['shift', 'workLocation'])
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $today)
            ->first();

        $workLocation = $schedule?->workLocation
            ?? WorkLocation::where('company_id', $employee->company_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

        $log = AttendanceLog::with('photos')
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $today)
            ->first();

        return Inertia::render('employee/attendance/index', [
            'employee' => [
                'name' => $request->user()->name,
                'employee_code' => $employee->employee_code,
                'company' => $employee->company?->name,
            ],
            'shift' => $schedule?->shift
                ? [
                    'name' => $schedule->shift->name,
                    'start_time' => $schedule->shift->start_time,
                    'end_time' => $schedule->shift->end_time,
                    'grace_minutes' => $schedule->shift->grace_minutes,
                    'is_overnight' => $schedule->shift->is_overnight,
                ]
                : null,
            'workLocation' => $workLocation
                ? [
                    'name' => $workLocation->name,
                    'latitude' => $workLocation->latitude,
                    'longitude' => $workLocation->longitude,
                    'radius_meters' => $workLocation->radius_meters,
                ]
                : null,
            'log' => $log
                ? [
                    'id' => $log->id,
                    'work_date' => $log->work_date?->format('Y-m-d'),
                    'check_in_at' => $log->check_in_at,
                    'check_out_at' => $log->check_out_at,
                    'approval_status' => $log->approval_status,
                    'status' => $log->status,
                    'check_in_distance_meters' => $log->check_in_distance_meters,
                    'check_out_distance_meters' => $log->check_out_distance_meters,
                    'photos' => $log->photos->map(fn ($photo) => [
                        'id' => $photo->id,
                        'type' => $photo->type,
                        'file_path' => $photo->file_path,
                    ]),
                ]
                : null,
            'canCheckIn' => !$log || !$log->check_in_at,
            'canCheckOut' => $log && $log->check_in_at && !$log->check_out_at,
            'serverTime' => Carbon::now()->format('d M Y H:i'),
        ]);
    }

    public function checkIn(Request $request)
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        $today = Carbon::today();
        $schedule = WorkSchedule::with(['shift', 'workLocation'])
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $today)
            ->first();

        $workLocation = $schedule?->workLocation
            ?? WorkLocation::where('company_id', $employee->company_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

        $shift = $schedule?->shift;
        $checkInAt = Carbon::now();

        $log = AttendanceLog::firstOrNew([
            'employee_id' => $employee->id,
            'work_date' => $today->toDateString(),
        ]);

        if ($log->check_in_at) {
            return back()->withErrors([
                'photo' => 'Anda sudah melakukan check-in hari ini.',
            ]);
        }

        $lateMinutes = 0;
        $status = 'present';

        if ($shift) {
            $shiftStart = Carbon::parse($today->toDateString().' '.$shift->start_time);
            $grace = $shift->grace_minutes ?? 0;

            if ($checkInAt->gt($shiftStart->copy()->addMinutes($grace))) {
                $status = 'late';
                $lateMinutes = $shiftStart->diffInMinutes($checkInAt);
            }
        }

        $distance = null;
        if ($workLocation && $workLocation->latitude && $workLocation->longitude) {
            $distance = $this->distanceMeters(
                $data['latitude'],
                $data['longitude'],
                $workLocation->latitude,
                $workLocation->longitude,
            );
        }

        DB::transaction(function () use (
            $log,
            $shift,
            $workLocation,
            $data,
            $checkInAt,
            $status,
            $lateMinutes,
            $distance,
            $request
        ) {
            $log->fill([
                'shift_id' => $shift?->id,
                'work_location_id' => $workLocation?->id,
                'check_in_at' => $checkInAt,
                'check_in_latitude' => $data['latitude'],
                'check_in_longitude' => $data['longitude'],
                'check_in_distance_meters' => $distance,
                'check_in_method' => 'gps_selfie',
                'check_in_ip' => $request->ip(),
                'status' => $status,
                'approval_status' => 'pending',
                'late_minutes' => $lateMinutes,
            ]);
            $log->save();

            $path = $data['photo']->store('attendance', 'public');

            AttendancePhoto::create([
                'attendance_log_id' => $log->id,
                'type' => 'check_in',
                'file_path' => $path,
                'mime' => $data['photo']->getClientMimeType(),
                'size_bytes' => $data['photo']->getSize(),
                'captured_at' => $checkInAt,
            ]);
        });

        return back();
    }

    public function checkOut(Request $request)
    {
        $employee = Employee::where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        $today = Carbon::today();
        $log = AttendanceLog::with('shift', 'workLocation')
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $today)
            ->first();

        if (!$log || !$log->check_in_at) {
            return back()->withErrors([
                'photo' => 'Belum ada check-in hari ini.',
            ]);
        }

        if ($log->check_out_at) {
            return back()->withErrors([
                'photo' => 'Anda sudah melakukan check-out hari ini.',
            ]);
        }

        $checkOutAt = Carbon::now();
        $shift = $log->shift;
        $workLocation = $log->workLocation;

        $distance = null;
        if ($workLocation && $workLocation->latitude && $workLocation->longitude) {
            $distance = $this->distanceMeters(
                $data['latitude'],
                $data['longitude'],
                $workLocation->latitude,
                $workLocation->longitude,
            );
        }

        $overtimeMinutes = 0;
        if ($shift) {
            $shiftStart = Carbon::parse($today->toDateString().' '.$shift->start_time);
            $shiftEnd = Carbon::parse($today->toDateString().' '.$shift->end_time);

            if ($shift->is_overnight && $shiftEnd->lessThanOrEqualTo($shiftStart)) {
                $shiftEnd->addDay();
            }

            if ($checkOutAt->gt($shiftEnd)) {
                $overtimeMinutes = $shiftEnd->diffInMinutes($checkOutAt);
            }
        }

        DB::transaction(function () use (
            $log,
            $data,
            $checkOutAt,
            $distance,
            $overtimeMinutes,
            $request
        ) {
            $log->update([
                'check_out_at' => $checkOutAt,
                'check_out_latitude' => $data['latitude'],
                'check_out_longitude' => $data['longitude'],
                'check_out_distance_meters' => $distance,
                'check_out_method' => 'gps_selfie',
                'check_out_ip' => $request->ip(),
                'overtime_minutes' => $overtimeMinutes,
            ]);

            $path = $data['photo']->store('attendance', 'public');

            AttendancePhoto::create([
                'attendance_log_id' => $log->id,
                'type' => 'check_out',
                'file_path' => $path,
                'mime' => $data['photo']->getClientMimeType(),
                'size_bytes' => $data['photo']->getSize(),
                'captured_at' => $checkOutAt,
            ]);
        });

        return back();
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lonDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }
}
