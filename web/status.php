<?php
// status.php — Retorna l'estat actual de la VirKo en JSON
date_default_timezone_set('Europe/Madrid');
session_start();
if (!isset($_SESSION['usuari'])) { http_response_code(401); exit; }

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$conn = new mysqli("db", "root", "root", "virko");
if ($conn->connect_error) { echo json_encode(['error' => true]); exit; }

$mac_filter = isset($_GET['mac']) ? $conn->real_escape_string($_GET['mac']) : '';
$where = $mac_filter ? "WHERE mac='$mac_filter'" : '';

$res = $conn->query("SELECT timestamp, co2 FROM dades $where ORDER BY timestamp DESC LIMIT 1");

if (!$res || !($row = $res->fetch_assoc())) {
    echo json_encode(['connectada' => false, 'minuts' => 0, 'qualitat' => 'excelllent', 'led' => 'verd', 'co2' => 0]);
    exit;
}

$ts_virko = strtotime($row['timestamp']);
$ts_ara   = time();
$segons   = $ts_ara - $ts_virko;
if ($segons < 0) $segons = abs($segons);

$connectada = ($segons < 15);
$minuts     = intval($segons / 60);
$co2        = intval($row['co2']);

if ($co2 >= 1000)    { $qualitat = 'dolent';     $led = 'vermell'; }
elseif ($co2 >= 800) { $qualitat = 'acceptable'; $led = 'blau'; }
else                 { $qualitat = 'excelllent'; $led = 'verd'; }

echo json_encode([
    'connectada' => $connectada,
    'minuts'     => $minuts,
    'qualitat'   => $qualitat,
    'led'        => $led,
    'co2'        => $co2,
]);
$conn->close();