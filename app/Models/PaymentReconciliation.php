<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReconciliation extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'reconciliation_no',
        'batch_no',
        'vendor_id',
        'channel',
        'pos_terminal_name',
        'settlement_date',
        'gross_amount',
        'fee_amount',
        'net_amount',
        'fee_rate',
        'total_transactions',
        'ledger_id',
        'notes',
        'reconciled_by',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'gross_amount'    => 'float',
        'fee_amount'      => 'float',
        'net_amount'      => 'float',
        'fee_rate'        => 'float',
        'total_transactions' => 'integer',
    ];

    public const CHANNEL_CARD   = 'card';
    public const CHANNEL_BKASH  = 'bkash';
    public const CHANNEL_NAGAD  = 'nagad';
    public const CHANNEL_ROCKET = 'rocket';
    public const CHANNEL_BANK   = 'bank_transfer';
    public const CHANNEL_OTHER  = 'other';

    public static function channelOptions(): array
    {
        return [
            self::CHANNEL_CARD   => 'POS Card (Visa / Master / Amex)',
            self::CHANNEL_BKASH  => 'bKash Merchant',
            self::CHANNEL_NAGAD  => 'Nagad',
            self::CHANNEL_ROCKET => 'Rocket',
            self::CHANNEL_BANK   => 'Bank Deposit / Cheque',
            self::CHANNEL_OTHER  => 'Online Transfer (Other)',
        ];
    }

    public function getChannelLabelAttribute(): string
    {
        return self::channelOptions()[$this->channel] ?? ucfirst(str_replace('_', ' ', $this->channel));
    }

    public function getChannelBadgeClassAttribute(): string
    {
        return match ($this->channel) {
            self::CHANNEL_CARD   => 'badge-primary',
            self::CHANNEL_BKASH  => 'badge-danger',
            self::CHANNEL_NAGAD  => 'badge-warning',
            self::CHANNEL_ROCKET => 'badge-info',
            self::CHANNEL_BANK   => 'badge-secondary',
            default              => 'badge-dark',
        };
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function reconciledBy()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    public function sales()
    {
        return $this->hasMany(SaleMaster::class, 'gateway_settlement_id');
    }

    public static function nextReconciliationNo(): string
    {
        $prefix = 'REC-' . date('ymd') . '-';
        $latest = self::withTrashed()
            ->where('reconciliation_no', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->value('reconciliation_no');

        if ($latest) {
            $num = (int) substr($latest, strlen($prefix)) + 1;
        } else {
            $num = 1;
        }

        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
