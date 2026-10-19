<?php

namespace Tests\Feature;

use App\Classes\Helper;
use App\Services\ReportHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Report plan phase 2: every report accepts the same branch filter, can export, and is listed in the hub. */
class ReportStandardsTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    public function test_reports_that_had_no_export_now_give_a_csv(): void
    {
        foreach (['stock', 'warranty-expiry', 'battery-usage-expiry', 'advances', 'services-status'] as $slug) {
            $response = $this->actingAs($this->admin)->get("/admin/report/{$slug}?export=csv");
            $response->assertSuccessful();
            $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'), $slug);
            $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent(), $slug);
        }
    }

    public function test_report_pages_show_the_standard_export_buttons(): void
    {
        foreach (['advances', 'services-status'] as $slug) {
            $this->actingAs($this->admin)->get("/admin/report/{$slug}")->assertSuccessful()->assertSee('btn-export-csv', false);
        }
        $this->actingAs($this->admin)->get('/admin/report/profit-statement')->assertSee('btn-export-xlsx', false)->assertSee('btn-export-csv', false);
    }

    public function test_vendor_id_and_branch_are_the_same_filter(): void
    {
        $this->actingAs($this->admin);

        $a = Request::create('/x', 'GET', ['vendor_id' => '7']);
        Helper::forceReportBranch($a, fn ($r) => $r);
        $this->assertSame('7', $a->input('branch'));

        $b = Request::create('/x', 'GET', ['branch' => '9']);
        Helper::forceReportBranch($b, fn ($r) => $r);
        $this->assertSame('9', $b->input('vendor_id'));
    }

    public function test_branch_staff_cannot_widen_the_filter_by_using_the_other_name(): void
    {
        $staff = $this->userWithPermissions(['advances_report.index']);
        $staff->update(['vendor_id' => $this->branch->id]);
        $this->actingAs($staff);

        $r = Request::create('/x', 'GET', ['branch' => 'all']);
        Helper::forceReportBranch($r, fn ($q) => $q);
        $this->assertSame((string) $this->branch->id, $r->input('vendor_id'));
        $this->assertSame((string) $this->branch->id, $r->input('branch'));
    }

    public function test_every_report_in_the_menu_is_in_the_hub_and_named_as_a_report(): void
    {
        $menu = collect(config('acl.menus'))->firstWhere('name', 'Report')['children'];
        $hubUrls = collect((new ReportHub())->forUser($this->admin))->flatMap(fn ($g) => array_column($g['items'], 'url'))->all();

        foreach ($menu as $child) {
            if ($child['name'] === 'Reports Hub') {
                continue;
            }
            $this->assertContains($child['route'], $hubUrls, $child['name'] . ' is missing from the hub');
            $label = $child['label'] ?? $child['name'];
            $this->assertMatchesRegularExpression('/(Report|Statement)$/', $label, "$label should end in Report or Statement");
        }
    }
}
