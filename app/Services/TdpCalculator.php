<?php
declare(strict_types=1);

/**
 * Kalkulator kredit & TDP (Total Down Payment).
 *
 * Rumus (bunga FLAT, standar pembiayaan kendaraan di Indonesia):
 *   DP murni        = OTR x DP%
 *   Pokok hutang    = OTR - DP murni
 *   Total bunga     = Pokok x bunga/tahun x (tenor/12)
 *   Angsuran/bulan  = (Pokok + Total bunga) / tenor
 *   Asuransi        = OTR x rate asuransi/tahun (premi tahun pertama dibayar di muka)
 *   TDP             = DP murni + Biaya Admin + Asuransi + Angsuran pertama (jika ADDM)
 *   ADDM = Angsuran Dibayar Di Muka | ADDB = Angsuran Dibayar Di Belakang (tidak masuk TDP)
 *
 * Logika identik dengan public/assets/js/credit-calc.js (preview di browser);
 * server SELALU menghitung ulang dari tabel rate di database saat SPK dibuat.
 */
final class TdpCalculator
{
    public const TENORS = [12, 24, 36, 48, 60];

    /** @return array<string,float|int|string> */
    public static function calculate(
        float $otr, float $dpPercent, int $tenor, float $interestRate,
        float $adminFee, float $insuranceRate, bool $addm = true
    ): array {
        if ($otr <= 0) throw new InvalidArgumentException('Harga OTR tidak valid.');
        if (!in_array($tenor, self::TENORS, true)) throw new InvalidArgumentException('Tenor tidak valid.');
        if ($dpPercent <= 0 || $dpPercent >= 100) throw new InvalidArgumentException('Persentase DP tidak valid.');

        $dp        = round($otr * $dpPercent / 100);
        $principal = $otr - $dp;
        $interest  = $principal * ($interestRate / 100) * ($tenor / 12);
        $install   = (int)round(($principal + $interest) / $tenor);
        $insurance = round($otr * $insuranceRate / 100);
        $first     = $addm ? $install : 0;

        return [
            'otr' => $otr, 'dp_percent' => $dpPercent, 'dp' => $dp, 'principal' => $principal,
            'total_interest' => round($interest), 'installment' => $install, 'tenor' => $tenor,
            'admin_fee' => $adminFee, 'insurance' => $insurance, 'first_installment' => $first,
            'tdp' => $dp + $adminFee + $insurance + $first,
            'timing' => $addm ? 'ADDM' : 'ADDB',
        ];
    }

    /**
     * Hitung memakai parameter mitra leasing dari DB.
     * @param 'TLO'|'AR' $insuranceType
     */
    public static function forPartner(
        PDO $pdo, int $partnerId, int $tenor, float $otr, float $dpPercent,
        string $insuranceType = 'TLO', bool $addm = true
    ): array {
        $st = $pdo->prepare(
            'SELECT p.min_dp_percent, p.admin_fee, r.interest_rate, r.insurance_tlo_rate, r.insurance_ar_rate
               FROM leasing_partners p JOIN leasing_rates r ON r.partner_id = p.id
              WHERE p.id = ? AND p.is_active = 1 AND r.tenor = ?'
        );
        $st->execute([$partnerId, $tenor]);
        $r = $st->fetch();
        if (!$r) throw new InvalidArgumentException('Paket leasing / tenor tidak tersedia.');
        if ($dpPercent < (float)$r['min_dp_percent']) {
            throw new InvalidArgumentException('DP minimal mitra ini adalah ' . $r['min_dp_percent'] . '%.');
        }
        $insRate = $insuranceType === 'AR' ? (float)$r['insurance_ar_rate'] : (float)$r['insurance_tlo_rate'];
        $res = self::calculate($otr, $dpPercent, $tenor, (float)$r['interest_rate'], (float)$r['admin_fee'], $insRate, $addm);
        $res['interest_rate'] = (float)$r['interest_rate'];
        $res['insurance_type'] = $insuranceType;
        return $res;
    }
}
