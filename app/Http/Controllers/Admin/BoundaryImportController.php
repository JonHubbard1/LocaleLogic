<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBoundaryImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BoundaryImportController extends Controller
{
    public function upload(Request $request)
    {
        try {
            $validated = $request->validate([
                'boundaryType' => 'required|in:ward_hierarchy_lookup,parish_lookup,wards,lad,parishes,ced,region,counties,constituencies,police_force_areas,lpa,combined_authorities,scottish_constituencies,scottish_regions,senedd_constituencies,senedd_regions',
                'file' => 'required|file|mimes:csv,json,geojson,zip|max:5242880',
            ], [
                'boundaryType.required' => 'Please select a boundary type.',
                'boundaryType.in' => 'The selected boundary type is not valid.',
                'file.required' => 'Please select a file to upload.',
                'file.mimes' => 'The file must be a CSV, JSON, GeoJSON, or ZIP file.',
                'file.max' => 'The file size exceeds the maximum allowed (5GB).',
            ]);
        } catch (ValidationException $e) {
            return redirect()->route('admin.boundaries', ['boundaryType' => $request->input('boundaryType')])
                ->withErrors($e->validator)
                ->withInput();
        }

        $boundaryType = $validated['boundaryType'];

        try {
            $uploadedFile = $request->file('file');
            $path = $uploadedFile->store('boundaries/' . $boundaryType, 'local');

            Log::info('Boundary file uploaded successfully', [
                'path' => $path,
                'boundary_type' => $boundaryType,
                'original_name' => $uploadedFile->getClientOriginalName(),
            ]);

            ProcessBoundaryImport::dispatch(
                $path,
                $boundaryType,
                'manual_upload',
                ['original_filename' => $uploadedFile->getClientOriginalName()]
            );

            Log::info('ProcessBoundaryImport job dispatched successfully', [
                'path' => $path,
                'boundary_type' => $boundaryType,
                'original_name' => $uploadedFile->getClientOriginalName(),
            ]);

            return redirect()->route('admin.boundaries', ['boundaryType' => $boundaryType])
                ->with('success', 'File uploaded successfully. Import processing has been queued and will begin shortly.');
        } catch (\Throwable $e) {
            Log::error('Boundary upload failed', [
                'error' => $e->getMessage(),
                'boundary_type' => $boundaryType,
            ]);

            return redirect()->route('admin.boundaries', ['boundaryType' => $boundaryType])
                ->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }
}
