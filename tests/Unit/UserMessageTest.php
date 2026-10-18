<?php

namespace Tests\Unit;

use App\Classes\Helper;
use App\Exceptions\StockGuardException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Friendly error messages: our own messages are shown, system errors are hidden (audit Batch 6). */
class UserMessageTest extends TestCase
{
    public function test_messages_written_for_users_are_shown(): void
    {
        $this->assertSame('Amount exceeds balance.', Helper::userMessage(new \RuntimeException('Amount exceeds balance.')));
        $this->assertSame('Serial SN-1 is already sold.', Helper::userMessage(new StockGuardException(['Serial SN-1 is already sold.'])));
        $this->assertSame('Not allowed.', Helper::userMessage(new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'Not allowed.')));
    }

    public function test_database_and_internal_errors_are_hidden_and_logged(): void
    {
        Log::spy();
        $sqlError = new QueryException('mysql', 'insert into `customers` ...', [], new \Exception("Duplicate entry 'C-1' for key 'customers_customer_uid_unique'"));
        $message = Helper::userMessage($sqlError);

        $this->assertStringNotContainsString('customers', $message);
        $this->assertStringContainsString('has been logged', $message);
        Log::shouldHaveReceived('error')->once();

        $this->assertStringNotContainsString('App\\Models', Helper::userMessage(
            (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(\App\Models\Customer::class, [5])
        ));
    }
}
