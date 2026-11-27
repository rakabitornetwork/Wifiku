<?php

namespace App\Http\Controllers;

use App\Services\Updater\AppUpdater;
use App\Services\Updater\UpdateException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman pembaruan aplikasi satu klik (hanya Owner): periksa commit baru di git lalu pasang di latar belakang.
 */
class AppUpdateController extends Controller
{
    public function index(AppUpdater $updater): Response
    {
        return Inertia::render('Settings/AppUpdate', [
            'info' => $updater->check(fetch: false),
            'state' => $updater->state(),
        ]);
    }

    public function check(AppUpdater $updater): JsonResponse
    {
        return response()->json(['info' => $updater->check(), 'state' => $updater->state()]);
    }

    public function status(AppUpdater $updater): JsonResponse
    {
        return response()->json(['state' => $updater->state(), 'info' => $updater->check(fetch: false)]);
    }

    public function start(Request $request, AppUpdater $updater): JsonResponse
    {
        try {
            $updater->startInBackground($request->user()?->name);
        } catch (UpdateException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        activity('Sistem')->causedBy($request->user())->log('Memulai pembaruan aplikasi dari git');

        return response()->json(['state' => $updater->state()], 202);
    }
}
