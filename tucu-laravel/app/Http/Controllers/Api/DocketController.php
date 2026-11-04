<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Docket;
use App\Models\SpiritualYear;
use Illuminate\Http\Request;

class DocketController extends Controller
{
    /**
     * GET /api/dockets
     * Returns all active dockets with their constituent ministries.
     */
    public function index()
    {
        $dockets = Docket::with(['ministries' => function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }])
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();

        return response()->json([
            'success' => true,
            'dockets' => $dockets,
        ]);
    }

    /**
     * GET /api/calendars/current
     * Returns current academic year and spiritual year with annual theme.
     */
    public function calendars()
    {
        $academic = AcademicYear::current();
        $spiritual = SpiritualYear::current();

        return response()->json([
            'success'   => true,
            'academic'  => $academic,
            'spiritual' => $spiritual,
        ]);
    }
}
