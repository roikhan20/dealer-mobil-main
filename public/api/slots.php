<?php
/** Sisa slot servis untuk tanggal tertentu (GET ?date=YYYY-MM-DD). */
require __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$date = input('date');
if (!valid_date($date)) { http_response_code(422); echo json_encode(['error' => 'Tanggal tidak valid']); exit; }
echo json_encode(['date' => $date, 'remaining' => ServiceBookingService::remaining(db(), $date)]);
