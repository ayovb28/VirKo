<?php
// =====================================================
// sync_sheets.php — Sincronitza les dades del Google Sheets
// de la Virko amb la base de dades MySQL local
// S'executa automàticament cada 30 segons des del navegador
// =====================================================

// Connectem a la base de dades MySQL
$conn = new mysqli("db", "root", "root", "virko");

// Comprovem si la connexió ha fallat
if ($conn->connect_error) {
    die("Error connexió: " . $conn->connect_error);
}

// URL d'exportació CSV del Google Sheets públic de la Virko
$url = "https://docs.google.com/spreadsheets/d/1uXvdjWImZwIbnrSjo6uEbUCxzmo9HaiAWvBLGqRmyu0/export?format=csv&gid=0";

// Descarreguem el CSV des de Google Sheets
$csv = file_get_contents($url);
if (!$csv) die("Error descarregant el CSV");

// Dividim el CSV en línies individuals
$lines = explode("\n", trim($csv));

// Comptadors per al resum final
$importats = 0; // Registres nous importats
$saltats   = 0; // Registres que ja existien

// Iterem des de la línia 3 (saltem les dues primeres de capçalera)
for ($i = 2; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    if (empty($line)) continue;

    // Parsegem cada línia del CSV
    $cols = str_getcsv($line, ",", '"');
    if (count($cols) < 7) continue;

    // Assignem cada columna al seu camp corresponent
    $timestamp = $conn->real_escape_string(trim($cols[1]));
    $ldr       = $conn->real_escape_string(trim($cols[2]));
    $co2       = $conn->real_escape_string(trim($cols[3]));
    // Convertim la coma decimal europea al punt anglès
    $temp      = str_replace(",", ".", trim($cols[4]));
    $pressio   = str_replace(",", ".", trim($cols[5]));
    $humitat   = str_replace(",", ".", trim($cols[6]));
    $iaq       = isset($cols[7]) ? $conn->real_escape_string(trim($cols[7])) : 0;
    // Identificador fix de la Virko de l'aula d'informàtica
    $mac       = "B358";

    if (empty($timestamp)) continue;

    // Comprovem si aquest registre ja existeix per evitar duplicats
    $check = $conn->query("SELECT id FROM dades WHERE timestamp='$timestamp' AND mac='$mac'");
    if ($check->num_rows > 0) {
        $saltats++;
        continue;
    }

    // Inserim el nou registre a la base de dades
    $conn->query("INSERT INTO dades (timestamp, ldr, co2, temp, pressio, humitat, iaq, mac)
                  VALUES ('$timestamp', '$ldr', '$co2', '$temp', '$pressio', '$humitat', '$iaq', '$mac')");
    $importats++;
}

// Mostrem el resum de la sincronització
echo "Sincronització completada. Importats: $importats | Ja existien: $saltats";
?>