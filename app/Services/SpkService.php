<?php
declare(strict_types=1);

/**
 * Transaksi SPK dengan Automatic Stock Locking (pessimistic locking).
 *
 * Dua sales yang memilih VIN yang sama pada saat bersamaan akan diserialisasi oleh
 * SELECT ... FOR UPDATE: transaksi kedua menunggu, lalu melihat status 'booked' dan ditolak.
 */
final class SpkService
{
    public static function create(PDO $pdo, array $salesUser, array $in): int
    {
        $pdo->beginTransaction();
        try {
            // 1) KUNCI baris unit fisik. Transaksi lain pada VIN yang sama akan menunggu di sini.
            $st = $pdo->prepare('SELECT id, variant_id, status FROM vehicle_units WHERE id = ? FOR UPDATE');
            $st->execute([$in['unit_id']]);
            $unit = $st->fetch();
            if (!$unit)                       throw new DomainException('Unit tidak ditemukan.');
            if ($unit['status'] !== 'ready')  throw new DomainException('Unit sudah dibooking sales lain atau sudah terkirim. Pilih unit lain.');

            // 2) Harga OTR SELALU dari database, bukan dari input klien.
            $st = $pdo->prepare('SELECT price FROM variant_prices WHERE variant_id = ? AND region = ?');
            $st->execute([$unit['variant_id'], cfg('app', 'region')]);
            $otr = (float)$st->fetchColumn();
            if ($otr <= 0) throw new DomainException('Harga OTR varian belum diatur.');

            // 3) Hitung pembiayaan (jika kredit) dari parameter leasing di DB.
            $calc = null;
            if ($in['payment_method'] === 'kredit') {
                $calc = TdpCalculator::forPartner(
                    $pdo, (int)$in['partner_id'], (int)$in['tenor'], $otr,
                    (float)$in['dp_percent'], $in['insurance_type'], (bool)$in['addm']
                );
            }
            if ($in['booking_fee'] <= 0 || $in['booking_fee'] > $otr) throw new DomainException('Nominal booking fee tidak valid.');

            // 4) Alokasikan unit.
            $pdo->prepare("UPDATE vehicle_units SET status = 'booked' WHERE id = ?")->execute([$unit['id']]);

            // 5) Simpan SPK. spk_no sementara diganti setelah ID diketahui.
            $token = bin2hex(random_bytes(16));
            $pdo->prepare(
                'INSERT INTO spk(spk_no, lead_id, sales_id, unit_id, customer_name, nik, phone, address, payment_method,
                                 leasing_partner_id, tenor, otr_price, dp_amount, admin_fee, insurance_type, insurance_amount,
                                 interest_rate, addm, installment, tdp, booking_fee, verify_token)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                'TMP-' . $token, $in['lead_id'] ?: null, $salesUser['id'], $unit['id'],
                $in['customer_name'], $in['nik'], $in['phone'], $in['address'], $in['payment_method'],
                $calc ? (int)$in['partner_id'] : null, $calc ? $calc['tenor'] : null, $otr,
                $calc ? $calc['dp'] : 0, $calc ? $calc['admin_fee'] : 0,
                $calc ? $calc['insurance_type'] : null, $calc ? $calc['insurance'] : 0,
                $calc ? $calc['interest_rate'] : null, $calc ? (int)$in['addm'] : 1,
                $calc ? $calc['installment'] : 0, $calc ? $calc['tdp'] : 0,
                $in['booking_fee'], $token,
            ]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE spk SET spk_no = ? WHERE id = ?')
                ->execute([sprintf('SPK/%s/%05d', date('Ym'), $id), $id]);

            // 6) Lead (jika ada) otomatis menjadi WON.
            if ($in['lead_id']) {
                $pdo->prepare("UPDATE leads SET status = 'won' WHERE id = ?")->execute([$in['lead_id']]);
                $pdo->prepare("INSERT INTO lead_logs(lead_id,user_id,channel,note) VALUES (?,?,'sistem',?)")
                    ->execute([$in['lead_id'], $salesUser['id'], 'SPK diterbitkan, status lead menjadi WON.']);
            }

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Batalkan SPK -> unit kembali Ready. Hanya SPK 'active'. */
    public static function cancel(PDO $pdo, int $spkId): void
    {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT id, unit_id, status FROM spk WHERE id = ? FOR UPDATE');
            $st->execute([$spkId]);
            $spk = $st->fetch();
            if (!$spk || $spk['status'] !== 'active') throw new DomainException('Hanya SPK aktif yang dapat dibatalkan.');
            $pdo->prepare('SELECT id FROM vehicle_units WHERE id = ? FOR UPDATE')->execute([$spk['unit_id']]);
            $pdo->prepare("UPDATE vehicle_units SET status = 'ready' WHERE id = ?")->execute([$spk['unit_id']]);
            $pdo->prepare("UPDATE spk SET status = 'cancelled' WHERE id = ?")->execute([$spkId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Serah terima unit: SPK & unit menjadi Delivered (mulai masa garansi). */
    public static function deliver(PDO $pdo, int $spkId): void
    {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT id, unit_id, status FROM spk WHERE id = ? FOR UPDATE');
            $st->execute([$spkId]);
            $spk = $st->fetch();
            if (!$spk || $spk['status'] !== 'active') throw new DomainException('Hanya SPK aktif yang dapat diserahterimakan.');
            $pdo->prepare("UPDATE vehicle_units SET status = 'delivered', delivered_at = CURDATE() WHERE id = ?")->execute([$spk['unit_id']]);
            $pdo->prepare("UPDATE spk SET status = 'delivered' WHERE id = ?")->execute([$spkId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
