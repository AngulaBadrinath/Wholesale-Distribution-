<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SessionManagementController extends Controller
{
    /**
     * Display the authenticated user's active sessions.
     */
    public function index(Request $request, SessionRevocationService $sessionService): Response
    {
        $sessions = $sessionService->getActiveSessions(
            $request->user(),
            $request->session()->getId()
        );

        return Inertia::render('Security/Sessions', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Revoke a single active session belonging to the user.
     */
    public function destroy(Request $request, string $id, SessionRevocationService $sessionService): RedirectResponse
    {
        $user = $request->user();
        $targetSession = $sessionService->findUserSession($user, $id);

        if (! $targetSession) {
            abort(404, 'Session not found or already expired.');
        }

        $isCurrent = ($targetSession->id === $request->session()->getId());

        $sessionService->revokeSession(
            $user,
            $id,
            actorId: $user->id,
            ip: $request->ip()
        );

        if ($isCurrent) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Your session has been signed out.');
        }

        return redirect()->back()->with('success', 'Session has been signed out.');
    }

    /**
     * Revoke all other active sessions for the user, keeping the current session active.
     */
    public function destroyOthers(Request $request, SessionRevocationService $sessionService): RedirectResponse
    {
        $user = $request->user();

        $sessionService->revokeOtherSessions(
            $user,
            $request->session()->getId(),
            actorId: $user->id,
            ip: $request->ip()
        );

        return redirect()->back()->with('success', 'All other active sessions have been signed out.');
    }

    /**
     * Revoke all active sessions for the user, including the current session.
     */
    public function destroyAll(Request $request, SessionRevocationService $sessionService): RedirectResponse
    {
        $user = $request->user();

        $sessionService->revokeAllSessions(
            $user,
            actorId: $user->id,
            ip: $request->ip()
        );

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'All sessions have been signed out. Please sign in again.');
    }
}
