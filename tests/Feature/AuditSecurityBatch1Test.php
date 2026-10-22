<?php

namespace Tests\Feature;

use App\Classes\Helper;
use App\Support\SafeHtml;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Audit 22 Oct 2026, batch 1: uploads (SEC-01), sales list permissions (SEC-02), quotation HTML and branch (SEC-03/04), routes (SEC-06). */
class AuditSecurityBatch1Test extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    /** A real 1×1 PNG. */
    private const PNG = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0\x1f\x15\xc4\x89\0\0\0\rIDATx\x9cc\xf8\xff\xff?\0\x05\xfe\x02\xfe\xa7\x35\x81\x84\0\0\0\0IEND\xaeB`\x82";

    private array $made = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    protected function tearDown(): void
    {
        foreach ($this->made as $path) {
            @unlink(public_path($path));
        }
        parent::tearDown();
    }

    // ── SEC-01 uploads ──

    /** A real upload (type read from the content, like a browser upload); fakes report the type from the name. */
    private function upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_a_script_is_never_saved_into_a_public_folder(): void
    {
        $script = $this->upload('shell.php', '<?php echo "owned"; ?>');

        $this->expectException(ValidationException::class);
        Helper::fileUpload($script, 'file/setting/');
    }

    public function test_an_image_named_php_is_saved_as_the_image_it_is(): void
    {
        $polyglot = $this->upload('logo.php', self::PNG . '<?php echo 1; ?>');

        $path = $this->made[] = Helper::fileUpload($polyglot, 'file/setting/');

        $this->assertStringEndsWith('.png', $path);
        $this->assertFileExists(public_path($path));
    }

    public function test_settings_logo_must_be_an_image(): void
    {
        $setting = DB::table('settings')->first() ?? (object) ['id' => DB::table('settings')->insertGetId(['company_name' => 'Clinic', 'created_at' => now()]), 'company_name' => 'Clinic'];
        $before = DB::table('settings')->where('id', $setting->id)->value('logo');

        $this->actingAs($this->admin)->put('/admin/setting/' . $setting->id, [
            'company_name' => $setting->company_name ?: 'Clinic', 'mobile' => '01700000000',
            'logo' => UploadedFile::fake()->createWithContent('logo.php', '<?php echo 1; ?>'),
        ])->assertSessionHasErrors('logo');

        $this->assertSame($before, DB::table('settings')->where('id', $setting->id)->value('logo'));
    }

    public function test_repair_attachments_take_the_extension_from_the_content(): void
    {
        $this->assertSame('png', Helper::safeUploadExtension($this->upload('scan.phtml', self::PNG)));
        $this->assertSame('pdf', Helper::safeUploadExtension($this->upload('report.pdf', "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")));
    }

    // ── SEC-02 sales lists ──

    public function test_sales_lists_need_a_sales_permission(): void
    {
        $receptionist = $this->userWithPermissions(['appointment_list.index']);
        $this->actingAs($receptionist)->getJson('/admin/sale-list')->assertForbidden();
        $this->actingAs($receptionist)->get('/admin/sales/due-list')->assertForbidden();

        $cashier = $this->userWithPermissions(['patient_due_list.index']);
        $this->actingAs($cashier)->get('/admin/sales/due-list')->assertSuccessful();

    }

    // ── SEC-03 / SEC-04 quotations ──

    public function test_quotation_html_keeps_formatting_and_loses_scripts(): void
    {
        $clean = SafeHtml::clean('<p onclick="steal()">Hello <b>there</b></p><script>alert(1)</script><img src=x onerror=alert(2)>'
            . '<a href="javascript:alert(3)">link</a><table><tr><td colspan="2" style="background:url(x)">cell</td></tr></table>');

        $this->assertStringContainsString('<p>Hello <b>there</b></p>', $clean);
        $this->assertStringContainsString('<td colspan="2">cell</td>', $clean);
        $this->assertStringContainsString('link', $clean);
        foreach (['script', 'onclick', 'onerror', '<img', 'javascript:', 'style=', '<a '] as $bad) {
            $this->assertStringNotContainsString($bad, $clean);
        }
        $this->assertSame('', SafeHtml::clean(null));
        $this->assertSame('বাংলা লেখা', SafeHtml::clean('বাংলা লেখা'), 'Bangla text survives');
    }

    public function test_a_branch_user_files_quotations_under_their_own_branch_and_scripts_are_stripped(): void
    {
        $other = \App\Models\User::forceCreate(['name' => 'Other Branch', 'email' => 'other-b1@test.test', 'password' => bcrypt('x-secret-1'), 'type' => 'vendor', 'status' => 1]);
        $staff = $this->userWithPermissions(['proposals.create', 'proposals.view', 'proposals.index']);
        $staff->forceFill(['vendor_id' => $this->branch->id])->save();

        $this->actingAs($staff)->post('/admin/proposal', [
            'recipient_type' => 'direct', 'recipient_name' => 'Walk-in', 'title' => 'Quote', 'proposal_date' => date('Y-m-d'),
            'vendor_id' => $other->id, 'content' => '<p>Offer</p><script>alert(1)</script>',
        ])->assertSessionHasNoErrors();

        $row = DB::table('proposals')->latest('id')->first();
        $this->assertSame((int) $this->branch->id, (int) $row->vendor_id, 'not the branch named in the form');
        $this->assertSame('<p>Offer</p>', $row->content);
    }

    public function test_the_quotation_shows_the_clinic_logo_from_its_saved_path(): void
    {
        $settingId = DB::table('settings')->value('id') ?? DB::table('settings')->insertGetId(['company_name' => 'Clinic', 'created_at' => now()]);
        DB::table('settings')->where('id', $settingId)->update(['logo' => 'file/setting/logo-test.png']);
        $this->actingAs($this->admin)->post('/admin/proposal', [
            'recipient_type' => 'direct', 'recipient_name' => 'Walk-in', 'title' => 'Quote', 'proposal_date' => date('Y-m-d'),
        ])->assertSessionHasNoErrors();
        $id = DB::table('proposals')->max('id');

        $this->actingAs($this->admin)->get('/admin/proposal/' . $id)->assertSuccessful()
            ->assertSee(asset('file/setting/logo-test.png'), false)
            ->assertDontSee('file/setting/file/setting', false);
    }

    // ── SEC-06 routes ──

    public function test_routes_without_a_controller_method_are_gone(): void
    {
        $names = collect(app('router')->getRoutes()->getRoutesByName())->keys();
        foreach (['setting.index', 'setting.destroy', 'income.edit', 'income.update', 'customer-return.edit', 'manufacturer-return.update', 'sale-returns.edit'] as $name) {
            $this->assertFalse($names->contains($name), $name);
        }
        foreach (['setting.update', 'income.store', 'customer-return.show', 'sale-returns.create'] as $name) {
            $this->assertTrue($names->contains($name), $name);
        }
    }
}
