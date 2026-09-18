<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure;

use App\Billing\Domain\DuplicateReceipt;
use App\Billing\Domain\Payment;
use App\Billing\Domain\PaymentKind;
use App\Billing\Domain\PaymentStatus;
use App\Billing\Domain\Port\PaymentRepository;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Persistence\Catalog;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class DatabasePaymentRepository implements PaymentRepository
{
    public function __construct(private Catalog $catalog) {}

    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function find(string $id): ?Payment
    {
        return $this->restore(DB::table('payments')->where('id', $id)->lockForUpdate()->first());
    }

    public function findSessionPayment(string $appointmentId): ?Payment
    {
        return $this->restore(DB::table('payments')
            ->where('appointment_id', $appointmentId)
            ->where('kind', PaymentKind::SESSION_FEE->value)
            ->lockForUpdate()
            ->first());
    }

    public function save(Payment $payment): void
    {
        $now = now();
        $status = $payment->status();

        $row = [
            'status_id' => $this->catalog->id('payment_statuses', $status->value),
            'method_id' => $payment->method() ? $this->catalog->id('payment_methods', $payment->method()) : null,
            'reviewed_by' => $payment->reviewedBy(),
            'rejection_reason' => $payment->rejectionReason(),
            'waived_by' => $payment->waivedBy(),
            'waiver_reason' => $payment->waiverReason(),
            'updated_at' => $now,
        ];

        $row += match ($status) {
            PaymentStatus::EN_REVISION => ['submitted_at' => $now],
            PaymentStatus::APROBADO, PaymentStatus::RECHAZADO => ['reviewed_at' => $now],
            PaymentStatus::EXONERADO => ['waived_at' => $now],
            default => [],
        };

        DB::transaction(function () use ($payment, $row, $now) {
            if (DB::table('payments')->where('id', $payment->id)->exists()) {
                DB::table('payments')->where('id', $payment->id)->update($row);
            } else {
                DB::table('payments')->insert([
                    'id' => $payment->id,
                    'appointment_id' => $payment->appointmentId,
                    'kind' => $payment->kind->value,
                    'amount_cents' => $payment->amount->amountInCents,
                    'currency' => $payment->amount->currency,
                    'fee_percentage' => $payment->feePercentage,
                    'created_at' => $now,
                ] + $row);
            }

            $proof = $payment->releaseProof();

            if ($proof === null) {
                return;
            }

            try {
                DB::table('payment_proofs')->insert([
                    'id' => (string) Str::uuid7(),
                    'payment_id' => $payment->id,
                    'receipt_number' => $proof->receiptNumber,
                    'origin_bank_id' => $proof->originBankId,
                    'origin_account_mask' => $proof->accountMask,
                    'deposited_on' => $proof->depositedOn->format('Y-m-d'),
                    'file_path' => $proof->filePath,
                    'file_hash_sha256' => $proof->fileHashSha256,
                    'file_size_bytes' => $proof->fileSizeBytes,
                    'uploaded_at' => $now,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new DuplicateReceipt;
            }
        });
    }

    public function issueCredit(string $appointmentId, Money $amount, DateTimeImmutable $at): string
    {
        $id = (string) Str::uuid7();

        DB::table('patient_credits')->insert([
            'id' => $id,
            'patient_id' => DB::table('appointments')->where('id', $appointmentId)->value('patient_id'),
            'origin_appointment_id' => $appointmentId,
            'amount_cents' => $amount->amountInCents,
            'currency' => $amount->currency,
            'issued_at' => $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    private function restore(?object $row): ?Payment
    {
        if ($row === null) {
            return null;
        }

        return Payment::restore(
            $row->id,
            $row->appointment_id,
            PaymentKind::from($row->kind),
            new Money((int) $row->amount_cents, $row->currency),
            $row->fee_percentage !== null ? (int) $row->fee_percentage : null,
            PaymentStatus::from($this->catalog->code('payment_statuses', (int) $row->status_id)),
            $row->method_id !== null ? $this->catalog->code('payment_methods', (int) $row->method_id) : null,
            $row->reviewed_by,
            $row->rejection_reason,
            $row->waived_by,
            $row->waiver_reason,
        );
    }
}
