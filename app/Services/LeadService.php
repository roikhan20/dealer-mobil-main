<?php
declare(strict_types=1);

/** CRM: pembuatan lead + distribusi otomatis ke Sales Executive aktif. */
final class LeadService
{
    /**
     * Buat lead dan tugaskan ke sales dengan beban lead aktif (cold/warm/hot) paling sedikit;
     * seri dibagi rata berdasarkan lead terakhir yang diterima (round-robin adil).
     * Dijalankan dalam transaksi + FOR UPDATE agar dua lead simultan tidak jatuh ke sales yang sama.
     * Jika $pdo sudah dalam transaksi (dipanggil dari service lain), tidak membuka transaksi baru.
     */
    public static function create(PDO $pdo, array $d): int
    {
        $own = !$pdo->inTransaction();
        if ($own) $pdo->beginTransaction();
        try {
            $salesId = $pdo->query(
                "SELECT u.id FROM users u
                  WHERE u.role = 'sales' AND u.is_active = 1
                  ORDER BY (SELECT COUNT(*) FROM leads l WHERE l.sales_id = u.id AND l.status IN ('cold','warm','hot')) ASC,
                           COALESCE((SELECT MAX(l2.created_at) FROM leads l2 WHERE l2.sales_id = u.id), '1970-01-01') ASC,
                           u.id ASC
                  LIMIT 1 FOR UPDATE"
            )->fetchColumn();

            $st = $pdo->prepare(
                'INSERT INTO leads(name, phone, email, source, variant_id, sales_id, note) VALUES (?,?,?,?,?,?,?)'
            );
            $st->execute([
                $d['name'], $d['phone'], $d['email'] ?: null, $d['source'],
                $d['variant_id'] ?: null, $salesId ?: null, $d['note'] ?? null,
            ]);
            $leadId = (int)$pdo->lastInsertId();

            $pdo->prepare("INSERT INTO lead_logs(lead_id, user_id, channel, note) VALUES (?, NULL, 'sistem', ?)")
                ->execute([$leadId, 'Lead masuk dari ' . ($d['source'] === 'test_drive' ? 'form Test Drive' : 'form Penawaran') . ' dan ditugaskan otomatis.']);

            if ($own) $pdo->commit();
            return $leadId;
        } catch (Throwable $e) {
            if ($own && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
