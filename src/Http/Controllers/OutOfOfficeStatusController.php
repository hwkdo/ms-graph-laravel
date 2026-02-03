<?php

namespace Hwkdo\MsGraphLaravel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Hwkdo\MsGraphLaravel\Models\OutOfOfficeStatus;
use Illuminate\Http\JsonResponse;

class OutOfOfficeStatusController extends Controller
{
    /**
     * Display a listing of all out of office stati.
     */
    public function index(): JsonResponse
    {
        $stati = OutOfOfficeStatus::with('user')->get();

        $formattedStati = $stati->map(function ($status) {
            return array_merge(
                ['username' => $status->user->username ?? null],
                $status->getFormattedStatus()
            );
        });

        return response()->json($formattedStati);
    }

    /**
     * Display the out of office status for a specific user.
     */
    public function show(string $username): JsonResponse
    {
        $user = User::where('username', $username)->firstOrFail();

        $status = OutOfOfficeStatus::where('user_id', $user->id)->firstOrFail();

        return response()->json(array_merge(
            ['username' => $username],
            $status->getFormattedStatus()
        ));
    }
}
