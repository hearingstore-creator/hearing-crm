<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\CrmAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Header bell: open one (mark read + go to its link), mark all read, and per-user alert settings. */
class NotificationController extends Controller
{
    public function open(string $id): RedirectResponse
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return redirect($n->data['url'] ?? url()->previous());
    }

    public function readAll(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function settings(): View
    {
        $user = auth()->user();

        return view('admin.notifications.settings', [
            'categories' => $this->categoriesFor($user),
            'muted' => (array) ($user->notification_mutes ?? []),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $allowed = array_keys($this->categoriesFor($user));
        $on = array_intersect((array) $request->input('on', []), $allowed);

        // Keep mutes for categories this user can't see (e.g. owner-only), replace the rest.
        $hidden = array_diff((array) ($user->notification_mutes ?? []), $allowed);
        $user->notification_mutes = array_values(array_merge($hidden, array_diff($allowed, $on)));
        $user->save();

        return back()->with('success', 'Notification settings saved.');
    }

    private function categoriesFor($user): array
    {
        $cats = CrmAlert::CATEGORIES;
        if (! $user->isSuperAdmin()) {
            unset($cats['company_daily']);
        }

        return $cats;
    }
}
