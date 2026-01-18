<?php

use App\Http\Controllers\Api\V1\CouncilController;
use App\Http\Controllers\Api\V1\PostcodeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API v1 Routes - Require Sanctum Authentication
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Postcode Lookup
    Route::get('postcodes/{postcode}', [PostcodeController::class, 'show']);

    // Council Endpoints
    Route::get('councils', [CouncilController::class, 'index']);
    Route::get('councils/{countyCode}/districts', [CouncilController::class, 'districts']);
    Route::get('councils/{countyCode}/divisions', [CouncilController::class, 'divisions']);
    Route::get('councils/{councilCode}/wards', [CouncilController::class, 'wards']);
    Route::get('councils/{councilCode}/parishes', [CouncilController::class, 'parishes']);

    // Ward/Division/Parish Postcodes Endpoints (ONS-based assignment)
    Route::get('wards/{wardCode}/postcodes', [CouncilController::class, 'wardPostcodes']);
    Route::get('divisions/{divisionCode}/postcodes', [CouncilController::class, 'divisionPostcodes']);
    Route::get('parishes/{parishCode}/postcodes', [CouncilController::class, 'parishPostcodes']);

    // Spatial Postcodes Endpoints (Point-in-Polygon using actual boundary geometry)
    Route::get('wards/{wardCode}/postcodes/spatial', [CouncilController::class, 'wardPostcodesSpatial']);
    Route::get('divisions/{divisionCode}/postcodes/spatial', [CouncilController::class, 'divisionPostcodesSpatial']);
    Route::get('parishes/{parishCode}/postcodes/spatial', [CouncilController::class, 'parishPostcodesSpatial']);

    // Generic boundary postcodes endpoint (supports any boundary type)
    Route::get('boundaries/{boundaryType}/{gssCode}/postcodes', [CouncilController::class, 'boundaryPostcodesSpatial']);
});
