<?php
// =====================================================
// regenerar_csv.php — Regenera el CSV de la Virko
// en segon pla sense recarregar la pàgina
// =====================================================

$conn = new mysqli("db", "root", "root", "virko");
if ($conn->connect_error) die("Error");

$mac = isset($_GET['mac']) ? $conn->real_escape_string($_GET['mac']) : '';
if (!$mac) die("Error");

$dades = $conn->query("SELECT * FROM dades WHERE mac='$mac' ORDER BY timestamp DESC LIMIT 500");

$nom_fitxer = "virko_" . preg_replace('/[^a-zA-Z0-9]/', '_', $mac) . ".csv";
$ruta       = __DIR__ . "/fulls/" . $nom_fitxer;

$fp = fopen($ruta, 'w');
fputcsv($fp, ['Timestamp', 'Temperatura (°C)', 'CO2 (ppm)', 'Humitat (%)', 'Pressió (hPa)', 'LDR', 'IAQ', 'Virko ID'], ',', '"', '\\');
while ($f = $dades->fetch_assoc()) {
    fputcsv($fp, [$f['timestamp'], $f['temp'], $f['co2'], $f['humitat'], $f['pressio'], $f['ldr'], $f['iaq'], $f['mac']], ',', '"', '\\');
}
fclose($fp);

echo "OK";
?>