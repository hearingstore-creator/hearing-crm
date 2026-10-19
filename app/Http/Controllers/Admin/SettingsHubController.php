<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * One page that links to every settings screen the signed-in person is allowed to open, grouped by what
 * they are for. It owns no settings itself; each tile shows only with the same permission its screen needs.
 */
class SettingsHubController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:settings_hub.index');
    }

    public function index(): View
    {
        $user = auth()->user();
        $headOffice = ! $user->branchId();

        // [label, hint, icon, url, permission, headOfficeOnly]
        $all = [
            'Company' => [
                ['General settings', 'Company name, logo, address and invoice details', 'fa-building', url('admin/setting/create'), 'setting.edit', false],
                ['Branches', 'Branch logins, their details and logos', 'fa-store', url('admin/vendors'), 'vendor_list.index', true],
            ],
            'Sales and patients' => [
                ['Pipeline', 'Deal stages, lost reasons, owners, office hours, holidays', 'fa-stream', url('admin/pipeline-settings'), 'pipeline_settings.manage', false],
                ['Membership and loyalty', 'Tiers, points, milestones and redeem rules', 'fa-gem', url('admin/membership/settings'), 'loyalty_settings.index', false],
                ['Service types', 'Services you sell and their defaults', 'fa-concierge-bell', url('admin/service-type'), 'service_type.index', false],
            ],
            'Support and messages' => [
                ['Support', 'Ticket deadlines, automatic reminders and closing', 'fa-headset', url('admin/support-settings'), 'support_settings.index', true],
                ['SMS automations', 'Messages sent on their own: reminders, thanks, follow-ups', 'fa-robot', url('admin/sms-automations'), 'sms_automation.index', false],
                ['SMS templates', 'Wording of every message', 'fa-comment-dots', url('admin/sms-templates'), 'templates.index', false],
            ],
            'People and security' => [
                ['People', 'Staff and branch logins, last sign-in, deactivate, reset password', 'fa-users-cog', url('admin/user'), 'users.index', false],
                ['Roles', 'Starter roles and what each can do', 'fa-user-tag', url('admin/roles'), 'roles.index', false],
                ['Activity log', 'Who changed what, and who signed in', 'fa-history', url('admin/activity-log'), 'activity_log.index', false],
                ['Database backup', 'Download a full copy of the data (Super Admin)', 'fa-database', url('admin/database-backup'), 'database_backup.index', true],
            ],
        ];

        $groups = [];
        foreach ($all as $group => $tiles) {
            $visible = array_values(array_filter($tiles, fn ($t) => $user->hasAccess($t[4]) && (! $t[5] || $headOffice)));
            if ($visible) {
                $groups[$group] = $visible;
            }
        }

        return view('admin.settings.hub', ['groups' => $groups]);
    }
}
