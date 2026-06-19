<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CalibrationRun;
use App\Models\DeviceInstallation;
use App\Models\FloorPlan;
use App\Positioning\AnchorMeasurement;
use App\Positioning\RssiMultilateration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class CalibrationController extends Controller
{
    public function index(FloorPlan $floorPlan): View
    {
        return view('calibration.index', [
            'plan' => $floorPlan->load('location'),
            'installations' => $this->installations($floorPlan),
            'runs' => CalibrationRun::query()->with('user')->where('floor_plan_id', $floorPlan->id)->latest()->limit(30)->get(),
        ]);
    }

    public function preview(Request $request, FloorPlan $floorPlan, RssiMultilateration $multilateration): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'anchor_type' => ['required', 'in:beacon,scanner'],
            'expected_x' => ['required', 'numeric', 'between:0,'.$floorPlan->width_meters],
            'expected_y' => ['required', 'numeric', 'between:0,'.$floorPlan->height_meters],
            'anchors' => ['required', 'array', 'min:3'],
            'anchors.*.rssi' => ['required', 'integer', 'between:-127,-1'],
            'anchors.*.reference_rssi' => ['required', 'integer', 'between:-127,-1'],
            'anchors.*.path_loss_exponent' => ['required', 'numeric', 'between:0.5,8'],
        ]);

        $installations = $this->installations($floorPlan)
            ->filter(fn (DeviceInstallation $installation): bool => $installation->device->type === $validated['anchor_type'])
            ->whereIn('id', array_keys($validated['anchors']));
        if ($installations->count() < 3) {
            throw ValidationException::withMessages(['anchors' => 'Selecciona al menos tres anclas activas de este plano.']);
        }

        $measurements = [];
        $parameters = [];
        foreach ($installations as $installation) {
            $input = $validated['anchors'][$installation->id];
            $measurement = new AnchorMeasurement(
                identifier: $installation->device->identifier,
                x: (float) $installation->x,
                y: (float) $installation->y,
                rssi: (int) $input['rssi'],
                referenceRssi: (int) $input['reference_rssi'],
                pathLossExponent: (float) $input['path_loss_exponent'],
            );
            $measurements[] = $measurement;
            $parameters[] = [
                'installation_id' => $installation->id,
                'device' => $installation->device->name,
                'identifier' => $installation->device->identifier,
                'x_meters' => (float) $installation->x,
                'y_meters' => (float) $installation->y,
                'measured_rssi_dbm' => $measurement->rssi,
                'reference_rssi_dbm_at_1m' => $measurement->referenceRssi,
                'path_loss_exponent' => $measurement->pathLossExponent,
                'estimated_distance_meters' => round($measurement->estimatedDistance(), 4),
            ];
        }

        try {
            $result = $multilateration->calculate($measurements);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['anchors' => $exception->getMessage()]);
        }

        $positionError = hypot($result->x - (float) $validated['expected_x'], $result->y - (float) $validated['expected_y']);
        $run = CalibrationRun::query()->create([
            'floor_plan_id' => $floorPlan->id,
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'anchor_type' => $validated['anchor_type'],
            'expected_x' => $validated['expected_x'],
            'expected_y' => $validated['expected_y'],
            'calculated_x' => $result->x,
            'calculated_y' => $result->y,
            'position_error_meters' => $positionError,
            'signal_rmse_meters' => $result->accuracyMeters,
            'confidence' => $result->confidence,
            'parameters' => $parameters,
        ]);

        return redirect()->route('calibration.index', $floorPlan)->with('status', 'Prueba calculada. Revisa el error antes de aplicar.')->with('calibration_run_id', $run->id)->withInput();
    }

    public function apply(CalibrationRun $calibrationRun): RedirectResponse
    {
        abort_if($calibrationRun->status === 'applied', 422, 'Esta calibración ya fue aplicada.');

        DB::transaction(function () use ($calibrationRun): void {
            foreach ($calibrationRun->parameters as $parameter) {
                DeviceInstallation::query()
                    ->whereKey($parameter['installation_id'])
                    ->where('location_id', $calibrationRun->floorPlan->location_id)
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->update([
                        'reference_rssi' => $parameter['reference_rssi_dbm_at_1m'],
                        'path_loss_exponent' => $parameter['path_loss_exponent'],
                    ]);
            }
            $calibrationRun->update(['status' => 'applied', 'applied_at' => now()]);
        });

        return redirect()->route('calibration.index', $calibrationRun->floor_plan_id)->with('status', 'Parámetros de calibración aplicados.');
    }

    private function installations(FloorPlan $floorPlan)
    {
        return DeviceInstallation::query()
            ->with('device')
            ->where('location_id', $floorPlan->location_id)
            ->whereNull('ended_at')
            ->whereHas('device', fn ($query) => $query->whereIn('type', ['beacon', 'scanner'])->where('status', 'active'))
            ->orderBy('device_id')
            ->get();
    }
}
