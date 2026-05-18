<?php
// =====================================================
// descarregar_full.php — Genera i descarrega un fitxer
// Excel amb les dades de la Virko seleccionada
// =====================================================

// Iniciem la sessió per verificar autenticació
session_start();
if (!isset($_SESSION['usuari'])) { header("Location: login.php"); exit; }

// Connectem a la base de dades MySQL
$conn = new mysqli("db", "root", "root", "virko");
if ($conn->connect_error) die("Error connexió");

// Obtenim la MAC de la Virko
$mac = isset($_GET['mac']) ? $conn->real_escape_string($_GET['mac']) : '';
if (!$mac) die("Cal especificar una MAC");

// Obtenim el nom de l'aula
$full_info = $conn->query("SELECT nom FROM fulls WHERE mac='$mac' LIMIT 1")->fetch_assoc();
$nom_aula  = $full_info ? $full_info['nom'] : $mac;

// Obtenim les dades de la Virko
$dades = $conn->query("SELECT * FROM dades WHERE mac='$mac' ORDER BY timestamp DESC LIMIT 500");

// Nom del fitxer per descarregar
$nom_fitxer = "virko_" . preg_replace('/[^a-zA-Z0-9]/', '_', $mac) . ".xls";

// Generem el contingut Excel en format XML
$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
  <Worksheet ss:Name="' . htmlspecialchars($nom_aula) . '">
    <Table>
      <Row>
        <Cell><Data ss:Type="String">Timestamp</Data></Cell>
        <Cell><Data ss:Type="String">Temperatura (C)</Data></Cell>
        <Cell><Data ss:Type="String">CO2 (ppm)</Data></Cell>
        <Cell><Data ss:Type="String">Humitat (%)</Data></Cell>
        <Cell><Data ss:Type="String">Pressio (hPa)</Data></Cell>
        <Cell><Data ss:Type="String">LDR</Data></Cell>
        <Cell><Data ss:Type="String">IAQ</Data></Cell>
        <Cell><Data ss:Type="String">Virko ID</Data></Cell>
      </Row>';

while ($f = $dades->fetch_assoc()) {
    $xml .= '
      <Row>
        <Cell><Data ss:Type="String">' . $f['timestamp'] . '</Data></Cell>
        <Cell><Data ss:Type="Number">' . $f['temp']      . '</Data></Cell>
        <Cell><Data ss:Type="Number">' . $f['co2']       . '</Data></Cell>
        <Cell><Data ss:Type="Number">' . $f['humitat']   . '</Data></Cell>
        <Cell><Data ss:Type="Number">' . $f['pressio']   . '</Data></Cell>
        <Cell><Data ss:Type="Number">' . $f['ldr']       . '</Data></Cell>
        <Cell><Data ss:Type="Number">' . $f['iaq']       . '</Data></Cell>
        <Cell><Data ss:Type="String">' . $f['mac']       . '</Data></Cell>
      </Row>';
}

$xml .= '
    </Table>
  </Worksheet>
</Workbook>';

// Enviem les capçaleres per forçar la descàrrega del fitxer
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $nom_fitxer . '"');
header('Cache-Control: max-age=0');

// Enviem el contingut del fitxer
echo $xml;
exit;
?>