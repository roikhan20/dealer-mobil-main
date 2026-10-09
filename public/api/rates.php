<?php
/** JSON parameter leasing aktif untuk kalkulator front-end. */
require __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$partners = db()->query('SELECT id, name, min_dp_percent, admin_fee FROM leasing_partners WHERE is_active = 1 ORDER BY name')->fetchAll();
$rates = db()->query('SELECT partner_id, tenor, interest_rate, insurance_tlo_rate, insurance_ar_rate FROM leasing_rates ORDER BY tenor')->fetchAll();
foreach ($partners as &$p) {
    $p['id'] = (int)$p['id'];
    $p['min_dp_percent'] = (float)$p['min_dp_percent'];
    $p['admin_fee'] = (float)$p['admin_fee'];
    $p['rates'] = [];
    foreach ($rates as $r) {
        if ((int)$r['partner_id'] === $p['id']) {
            $p['rates'][(string)(int)$r['tenor']] = [
                'interest' => (float)$r['interest_rate'], 'tlo' => (float)$r['insurance_tlo_rate'], 'ar' => (float)$r['insurance_ar_rate'],
            ];
        }
    }
}
echo json_encode($partners, JSON_UNESCAPED_UNICODE);
