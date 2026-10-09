<?php
declare(strict_types=1);

/** Booking servis dengan kuota harian mekanik (anti over-booking via row lock). */
final class ServiceBookingService
{
    public static function remaining(PDO $pdo, string $date): int
    {
        $st = $pdo->prepare('SELECT capacity FROM service_capacity WHERE service_date = ?');
        $st->execute([$date]);
        $cap = $st->fetchColumn();
        $cap = $cap === false ? (int)cfg('app', 'service_default_capacity') : (int)$cap;
        $st = $pdo->prepare("SELECT COUNT(*) FROM service_bookings WHERE service_date = ? AND status <> 'cancelled'");
        $st->execute([$date]);
        return max(0, $cap - (int)$st->fetchColumn());
    }

    /**
     * Booking slot. Baris kuota dipastikan ada DI LUAR transaksi (autocommit) agar tidak ada S-lock
     * yang tertinggal (penyebab deadlock saat banyak request bersamaan). Jika tetap terjadi
     * deadlock/lock-timeout, transaksi diulang otomatis (maks 3x).
     */
    public static function book(PDO $pdo, array $d): string
    {
        $pdo->prepare('INSERT IGNORE INTO service_capacity(service_date, capacity) VALUES (?, ?)')
            ->execute([$d['date'], cfg('app', 'service_default_capacity')]);

        for ($try = 1; ; $try++) {
            try {
                return self::attempt($pdo, $d);
            } catch (PDOException $e) {
                if ($try < 3 && in_array((int)($e->errorInfo[1] ?? 0), [1213, 1205], true)) {
                    usleep(random_int(20_000, 80_000));
                    continue;
                }
                throw $e;
            }
        }
    }

    private static function attempt(PDO $pdo, array $d): string
    {
        $pdo->beginTransaction();
        try {
            // KUNCI baris kuota tanggal tersebut: booking bersamaan diserialisasi di sini.
            $st = $pdo->prepare('SELECT capacity FROM service_capacity WHERE service_date = ? FOR UPDATE');
            $st->execute([$d['date']]);
            $cap = (int)$st->fetchColumn();

            $st = $pdo->prepare("SELECT COUNT(*) FROM service_bookings WHERE service_date = ? AND status <> 'cancelled'");
            $st->execute([$d['date']]);
            if ((int)$st->fetchColumn() >= $cap) throw new DomainException('Slot servis pada tanggal tersebut sudah penuh. Pilih tanggal lain.');

            $code = 'SVC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $pdo->prepare(
                'INSERT INTO service_bookings(booking_code, vin, customer_name, phone, service_date, service_type, complaint)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$code, $d['vin'], $d['name'], $d['phone'], $d['date'], $d['type'], $d['complaint'] ?: null]);

            $pdo->commit();
            return $code;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
