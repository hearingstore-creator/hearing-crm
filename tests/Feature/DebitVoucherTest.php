<?php

namespace Tests\Feature;

use App\Models\IncomeExpenseHead;
use App\Models\Ledger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Debit / Credit Vouchers: typed voucher number, several lines, approval, and the cash & bank book. */
class DebitVoucherTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    private function makeHead(string $type = 'Expense'): IncomeExpenseHead
    {
        return IncomeExpenseHead::create(['head_type' => $type, 'head_name' => 'Voucher Test ' . uniqid(), 'status' => 1]);
    }

    private function no(): string
    {
        return 'BK-' . uniqid();
    }

    public function test_the_voucher_form_and_list_render(): void
    {
        $this->actingAs($this->admin)->get('/admin/expense/create')->assertSuccessful()
            ->assertSee('name="voucher_no"', false)
            ->assertSee('name="payee_name"', false)
            ->assertSee('name="payment_method"', false)
            ->assertSee('name="attachment"', false)
            ->assertSee('name="lines[0][amount]"', false)
            ->assertDontSee('Auto (DV');
        $this->actingAs($this->admin)->get('/admin/expense')->assertSuccessful()->assertSee('Debit Vouchers');
    }

    public function test_the_voucher_number_is_typed_required_and_unique(): void
    {
        $head = $this->makeHead();
        $base = ['income_expense_head_id' => $head->id, 'date' => '2026-10-22', 'paid' => '10', 'payment_method' => 'Cash'];

        $this->actingAs($this->admin)->post('/admin/expense', $base)->assertSessionHasErrors('voucher_no');

        $no = $this->no();
        $this->actingAs($this->admin)->post('/admin/expense', $base + ['voucher_no' => $no])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ledgers', ['voucher_no' => $no, 'amount_type' => 'Expense']);
        $this->assertDatabaseMissing('ledgers', ['voucher_no' => 'DV-20261022-0001']);

        $this->actingAs($this->admin)->post('/admin/expense', $base + ['voucher_no' => $no])->assertSessionHasErrors('voucher_no');
        $this->assertSame(1, Ledger::where('voucher_no', $no)->count());
    }

    public function test_saving_creates_a_voucher_with_the_typed_number(): void
    {
        Storage::fake();
        $head = $this->makeHead();
        $no = $this->no();

        $this->actingAs($this->admin)->post('/admin/expense', [
            'voucher_no' => $no, 'income_expense_head_id' => $head->id, 'date' => '2026-10-22', 'paid' => '1250.50',
            'payee_name' => 'City Electric', 'payment_method' => 'bKash', 'transaction_no' => 'TX123',
            'details' => 'October bill', 'attachment' => UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $v = Ledger::where('voucher_no', $no)->firstOrFail();
        $this->assertSame($no, $v->invoice_no);
        $this->assertSame('Expense', $v->amount_type);
        $this->assertNull($v->type);
        $this->assertEquals(1250.50, (float) $v->expense_amount);
        $this->assertSame('bKash', $v->payment_method);
        $this->assertSame('Pending', $v->status);
        Storage::assertExists($v->attachment_path);

        $this->actingAs($this->admin)->get('/admin/expense/' . $v->id)->assertSuccessful()
            ->assertSee($no)->assertSee('City Electric')->assertSee('One Thousand Two Hundred Fifty Taka');
        $this->actingAs($this->admin)->get('/admin/expense/' . $v->id . '/attachment')->assertSuccessful();
        $this->actingAs($this->admin)->get('/admin/expense?q=City+Electric&method=bKash')->assertSuccessful()->assertSee($no);
    }

    public function test_a_payment_mode_must_be_known(): void
    {
        $this->actingAs($this->admin)->post('/admin/expense', [
            'voucher_no' => $this->no(), 'income_expense_head_id' => $this->makeHead()->id,
            'date' => '2026-10-22', 'paid' => '10', 'payment_method' => 'Gold',
        ])->assertSessionHasErrors('payment_method');
    }

    private function multiVoucher(string $no, string $payee = 'Multi Payee', string $mode = 'Cash'): array
    {
        $a = $this->makeHead();
        $b = $this->makeHead();
        $this->actingAs($this->admin)->post('/admin/expense', [
            'voucher_no' => $no, 'date' => '2026-10-22', 'payee_name' => $payee, 'payment_method' => $mode, 'details' => 'Shared note',
            'lines' => [
                ['income_expense_head_id' => $a->id, 'amount' => '100', 'details' => 'Tea'],
                ['income_expense_head_id' => $b->id, 'amount' => '250.50'],
                ['income_expense_head_id' => '', 'amount' => ''],
            ],
        ])->assertSessionHasNoErrors();

        return [$a, $b];
    }

    public function test_one_voucher_can_have_several_lines(): void
    {
        $no = $this->no();
        [$a, $b] = $this->multiVoucher($no);

        $rows = Ledger::where('voucher_no', $no)->orderBy('id')->get();
        $this->assertCount(2, $rows, 'the empty third row is dropped');
        $this->assertEquals(350.50, (float) $rows->sum('expense_amount'));
        $this->assertSame('Tea', $rows[0]->details);
        $this->assertSame('Shared note', $rows[1]->details);
        $this->assertSame($no, $rows[0]->invoice_no);
        $this->assertNotSame($rows[0]->invoice_no, $rows[1]->invoice_no);

        $this->actingAs($this->admin)->get('/admin/expense/' . $rows[0]->id)->assertSuccessful()
            ->assertSee('Three Hundred Fifty Taka')->assertSee($a->head_name)->assertSee($b->head_name);
    }

    public function test_lines_need_a_head_and_an_amount(): void
    {
        $this->actingAs($this->admin)->post('/admin/expense', [
            'voucher_no' => $this->no(), 'date' => '2026-10-22',
            'lines' => [['income_expense_head_id' => $this->makeHead()->id, 'amount' => '']],
        ])->assertSessionHasErrors('lines.0.amount');
        $this->actingAs($this->admin)->post('/admin/expense', ['voucher_no' => $this->no(), 'date' => '2026-10-22'])
            ->assertSessionHasErrors();
    }

    public function test_editing_syncs_lines_and_cancelling_reverses_them_all(): void
    {
        $no = $this->no();
        [$a, $b] = $this->multiVoucher($no);
        $rows = Ledger::where('voucher_no', $no)->orderBy('id')->get();
        $c = $this->makeHead();

        $this->actingAs($this->admin)->get('/admin/expense/' . $rows[0]->id . '/edit')->assertSuccessful()->assertSee($no);
        $this->actingAs($this->admin)->put('/admin/expense/' . $rows[0]->id, [
            'date' => '2026-10-22', 'payee_name' => 'Changed', 'payment_method' => 'Cash',
            'lines' => [
                ['id' => $rows[0]->id, 'income_expense_head_id' => $a->id, 'amount' => '120', 'details' => 'Tea'],
                ['income_expense_head_id' => $c->id, 'amount' => '30', 'details' => 'New line'],
            ],
        ])->assertSessionHasNoErrors();

        $now = Ledger::where('voucher_no', $no)->orderBy('id')->get();
        $this->assertCount(2, $now, 'second line removed, new line added');
        $this->assertFalse($now->contains('id', $rows[1]->id));
        $this->assertEquals(150, (float) $now->sum('expense_amount'));
        $this->assertSame('Changed', $now[0]->payee_name);
        $this->assertSame('Changed', $now[1]->payee_name);

        $this->actingAs($this->admin)->from('/admin/expense')
            ->delete('/admin/expense/' . $now[0]->id, ['reason' => 'Entered twice'])->assertSessionHas('success');
        $this->assertSame(0, Ledger::whereIn('income_expense_head_id', [$a->id, $c->id])->whereNull('type')->count());
        $net = (float) Ledger::whereIn('income_expense_head_id', [$a->id, $c->id])->sum('expense_amount');
        $this->assertEqualsWithDelta(0, $net, 0.01, 'both lines are reversed');
    }

    private function voucherFor(string $payee = 'Edit Me'): Ledger
    {
        $this->actingAs($this->admin)->post('/admin/expense', [
            'voucher_no' => $this->no(), 'income_expense_head_id' => $this->makeHead()->id, 'date' => '2026-10-22', 'paid' => '100',
            'payee_name' => $payee, 'payment_method' => 'Cash',
        ])->assertRedirect();

        return Ledger::where('payee_name', $payee)->latest('id')->firstOrFail();
    }

    public function test_a_pending_voucher_can_be_edited_and_the_change_is_logged(): void
    {
        $v = $this->voucherFor();
        $this->assertSame('Pending', $v->status);
        $newHead = $this->makeHead();

        $this->actingAs($this->admin)->put("/admin/expense/{$v->id}", [
            'income_expense_head_id' => $newHead->id, 'date' => '2026-10-22', 'paid' => '250',
            'payee_name' => 'Edited Payee', 'payment_method' => 'Bank', 'bank_name' => 'City Bank',
        ])->assertRedirect("admin/expense/{$v->id}");

        $v->refresh();
        $this->assertEquals(250, (float) $v->expense_amount);
        $this->assertSame('Edited Payee', $v->payee_name);
        $this->assertSame($newHead->id, (int) $v->income_expense_head_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expense_edited', 'subject_id' => $v->id]);
    }

    public function test_an_approved_voucher_is_locked_and_the_maker_cannot_approve_it(): void
    {
        $maker = $this->userWithPermissions(['expense.index', 'expense.create', 'expense.edit', 'expense.approve']);
        $no = $this->no();
        $this->actingAs($maker)->post('/admin/expense', [
            'voucher_no' => $no, 'income_expense_head_id' => $this->makeHead()->id, 'date' => '2026-10-22', 'paid' => '75',
            'payee_name' => 'Maker Test', 'payment_method' => 'Cash',
        ])->assertRedirect();
        $v = Ledger::where('voucher_no', $no)->firstOrFail();

        $this->actingAs($maker)->post("/admin/expense/{$v->id}/approve")->assertSessionHas('warning');
        $this->assertSame('Pending', $v->fresh()->status);

        $this->actingAs($this->admin)->post("/admin/expense/{$v->id}/approve")->assertSessionHas('success');
        $v->refresh();
        $this->assertSame('Approved', $v->status);
        $this->assertSame($this->admin->id, (int) $v->approved_by);

        $this->actingAs($this->admin)->get("/admin/expense/{$v->id}/edit")->assertRedirect('admin/expense');
        $this->actingAs($this->admin)->put("/admin/expense/{$v->id}", [
            'income_expense_head_id' => $v->income_expense_head_id, 'date' => '2026-10-22', 'paid' => '1', 'payment_method' => 'Cash',
        ])->assertRedirect('admin/expense');
        $this->assertEquals(75, (float) $v->fresh()->expense_amount);
    }

    public function test_approving_a_multi_line_voucher_approves_every_line(): void
    {
        $no = $this->no();
        $this->multiVoucher($no);
        $first = Ledger::where('voucher_no', $no)->orderBy('id')->first();

        $this->actingAs($this->admin)->post("/admin/expense/{$first->id}/approve")->assertSessionHas('success');
        $this->assertSame(2, Ledger::where('voucher_no', $no)->where('status', 'Approved')->count());
    }

    public function test_edit_and_approve_need_their_own_permissions(): void
    {
        $v = $this->voucherFor('Perm Test');
        $viewer = $this->userWithPermissions(['expense.index']);
        $this->actingAs($viewer)->get("/admin/expense/{$v->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->post("/admin/expense/{$v->id}/approve")->assertForbidden();
    }

    public function test_a_credit_voucher_is_numbered_approved_and_cancelled_by_reversal(): void
    {
        $head = $this->makeHead('Income');

        $this->actingAs($this->admin)->get('/admin/income/create')->assertSuccessful()->assertSee('name="paid_amount"', false);
        $this->actingAs($this->admin)->post('/admin/income', [
            'income_expense_head_id' => $head->id, 'date' => '2026-10-22', 'paid_amount' => '900',
            'payee_name' => 'Rent Payer', 'payment_method' => 'bKash', 'transaction_no' => 'RX9',
        ])->assertRedirect();

        $v = Ledger::where('payee_name', 'Rent Payer')->firstOrFail();
        $this->assertStringStartsWith('CV-20261022-', $v->voucher_no);
        $this->assertSame('Income', $v->amount_type);
        $this->assertEquals(900, (float) $v->income_amount);
        $this->assertSame('Pending', $v->status);

        $this->actingAs($this->admin)->get("/admin/income/{$v->id}")->assertSuccessful()
            ->assertSee('Credit Voucher')->assertSee('Rent Payer')->assertSee('Nine Hundred Taka');
        $this->actingAs($this->admin)->get('/admin/income?q=Rent+Payer')->assertSuccessful()->assertSee($v->voucher_no);

        $this->actingAs($this->admin)->post("/admin/income/{$v->id}/approve")->assertSessionHas('success');
        $this->assertSame('Approved', $v->fresh()->status);

        $this->actingAs($this->admin)->from('/admin/income')->delete("/admin/income/{$v->id}")->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->from('/admin/income')->delete("/admin/income/{$v->id}", ['reason' => 'Entered twice'])->assertSessionHas('success');
        $this->assertSame('Income (Reversed)', $v->fresh()->type);
        $net = (float) Ledger::where('income_expense_head_id', $head->id)->sum('income_amount');
        $this->assertEqualsWithDelta(0, $net, 0.01);
    }

    public function test_the_cash_book_adds_credit_vouchers_and_subtracts_debit_vouchers(): void
    {
        $in = $this->makeHead('Income');
        $out = $this->makeHead();
        $date = '2026-03-10';

        $this->actingAs($this->admin)->post('/admin/income', [
            'income_expense_head_id' => $in->id, 'date' => $date, 'paid_amount' => '1000', 'payment_method' => 'Cash', 'payee_name' => 'Book Payer',
        ]);
        $no = $this->no();
        $this->actingAs($this->admin)->post('/admin/expense', [
            'voucher_no' => $no, 'income_expense_head_id' => $out->id, 'date' => $date, 'paid' => '300', 'payment_method' => 'Cash', 'payee_name' => 'Book Payee',
        ]);
        $this->actingAs($this->admin)->post('/admin/expense', [
            'voucher_no' => $this->no(), 'income_expense_head_id' => $out->id, 'date' => $date, 'paid' => '77', 'payment_method' => 'bKash', 'payee_name' => 'Wallet Payee',
        ]);

        $page = $this->actingAs($this->admin)->get("/admin/voucher-book?book=cash&from=$date&to=$date")->assertSuccessful()
            ->assertSee('Cash Book')->assertSee($no)->assertSee('Book Payer')->assertDontSee('Wallet Payee');
        $this->assertEqualsWithDelta(700.0, $page->viewData('closing') - $page->viewData('opening'), 0.01);

        $this->actingAs($this->admin)->get("/admin/voucher-book?book=wallet&from=$date&to=$date")->assertSuccessful()->assertSee('Wallet Payee');

        // cancelling a voucher nets it out of the book
        $row = Ledger::where('voucher_no', $no)->firstOrFail();
        $this->actingAs($this->admin)->from('/admin/expense')->delete("/admin/expense/{$row->id}", ['reason' => 'Entered twice']);
        $after = $this->actingAs($this->admin)->get("/admin/voucher-book?book=cash&from=$date&to=$date");
        $this->assertEqualsWithDelta(1000.0, $after->viewData('closing') - $after->viewData('opening'), 0.01);
    }

    public function test_the_book_needs_its_permission(): void
    {
        $user = $this->userWithPermissions(['expense.index']);
        $this->actingAs($user)->get('/admin/voucher-book')->assertForbidden();
        $allowed = $this->userWithPermissions(['cash_bank_book.index']);
        $this->actingAs($allowed)->get('/admin/voucher-book')->assertSuccessful();
    }

    public function test_debit_and_credit_voucher_show_views_have_print_icons_and_proper_structure(): void
    {
        $headIn = $this->makeHead('Income');
        $headOut = $this->makeHead();
        $date = '2026-10-26';

        // 1. Create a Debit Voucher
        $no = $this->no();
        $this->actingAs($this->admin)->post('/admin/expense', [
            'voucher_no' => $no,
            'income_expense_head_id' => $headOut->id,
            'date' => $date,
            'paid' => '500',
            'payment_method' => 'Cash',
            'payee_name' => 'Test Vendor Corp',
        ])->assertSessionHasNoErrors();

        $debit = Ledger::where('voucher_no', $no)->firstOrFail();

        // Check Debit Voucher printable page
        $res = $this->actingAs($this->admin)->get("/admin/expense/{$debit->id}");
        $res->assertSuccessful();
        $res->assertSee('fa-print');
        $res->assertSee('Print Debit Voucher');
        $res->assertSee('Test Vendor Corp');
        $res->assertSee('DEBIT VOUCHER');
        $res->assertSee('sheet');

        // Check Debit Voucher List page has the print icon
        $this->actingAs($this->admin)->get('/admin/expense')
            ->assertSuccessful()
            ->assertSee('fa-print')
            ->assertSee("/admin/expense/{$debit->id}");

        // 2. Create a Credit Voucher
        $this->actingAs($this->admin)->post('/admin/income', [
            'income_expense_head_id' => $headIn->id,
            'date' => $date,
            'paid_amount' => '1200',
            'payment_method' => 'Cash',
            'payee_name' => 'Donating Client',
        ])->assertSessionHasNoErrors();

        $credit = Ledger::where('amount_type', 'Income')->latest('id')->firstOrFail();

        // Check Credit Voucher printable page
        $resCredit = $this->actingAs($this->admin)->get("/admin/income/{$credit->id}");
        $resCredit->assertSuccessful();
        $resCredit->assertSee('fa-print');
        $resCredit->assertSee('Print Credit Voucher');
        $resCredit->assertSee('Donating Client');
        $resCredit->assertSee('CREDIT VOUCHER');
        $resCredit->assertSee('sheet');

        // Check Credit Voucher List page has the print icon
        $this->actingAs($this->admin)->get('/admin/income')
            ->assertSuccessful()
            ->assertSee('fa-print')
            ->assertSee("/admin/income/{$credit->id}");
    }
}

