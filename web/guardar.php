<?php
// =====================================================
// guardar.php — Rep les dades dels sensors de la Virko
// i les guarda a la base de dades MySQL
// Exemple d'ús: guardar.php?timestamp=...&ldr=...&co2=...
// =====================================================

// Connectem a la base de dades MySQL
$conn = new mysqli("db", "root", "root", "virko");

// Comprovem si la connexió ha fallat
if ($conn->connect_error) {
    die("Error connexió: " . $conn->connect_error);
}

// Recollim els paràmetres enviats per la Virko via GET
$timestamp = $_GET['timestamp']; // Data i hora de la mesura
$ldr       = $_GET['ldr'];       // Sensor de llum (Light Dependent Resistor)
$co2       = $_GET['co2'];       // Concentració de CO2 en ppm
$temp      = $_GET['temp'];      // Temperatura en graus Celsius
$pressio   = $_GET['p'];         // Pressió atmosfèrica en hPa
$humitat   = $_GET['rh'];        // Humitat relativa en %
$iaq       = $_GET['iaq'];       // Índex de qualitat de l'aire
$mac       = $_GET['mac'];       // Adreça MAC de la Virko (identificador únic)

// Construïm la consulta SQL per inserir les dades
$sql = "INSERT INTO dades (timestamp, ldr, co2, temp, pressio, humitat, iaq, mac)
        VALUES ('$timestamp', '$ldr', '$co2', '$temp', '$pressio', '$humitat', '$iaq', '$mac')";

// Executem la consulta i comprovem si ha anat bé
if ($conn->query($sql)) {
    echo "Dades guardades correctament";
} else {
    echo "Error en guardar les dades: " . $conn->error;
}
?>