<?php
// reset_wizard.php - Crea este archivo para resetear el wizard

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Resetear configuración del wizard
$sql_reset = "DELETE FROM wizard_config WHERE clave IN ('wizard_completado', 'fecha_configuracion_inicial')";
$conn->query($sql_reset);

// Opcional: También limpiar datos de prueba si los hay
if (isset($_GET['limpiar_datos']) && $_GET['limpiar_datos'] == '1') {
    // CUIDADO: Esto eliminará TODOS los datos
    $conn->query("DELETE FROM historial_cubiertas");
    $conn->query("DELETE FROM cubiertas");
    $conn->query("DELETE FROM coches");
    $conn->query("DELETE FROM reconstrucciones");
    $conn->query("DELETE FROM kilometraje_diario");
    $conn->query("DELETE FROM historial_bajas");
    
    echo "<div style='background: #e74c3c; color: white; padding: 20px; text-align: center; font-family: Arial;'>";
    echo "<h2>⚠️ Datos eliminados</h2>";
    echo "<p>Se han eliminado todos los datos del sistema.</p>";
    echo "</div>";
}

$conn->close();

echo "<div style='background: #2ecc71; color: white; padding: 20px; text-align: center; font-family: Arial;'>";
echo "<h2>✅ Wizard reseteado</h2>";
echo "<p>El wizard se ha reseteado correctamente.</p>";
echo "<p><a href='/prueba24/wizard_setup.php' style='color: white; font-weight: bold;'>Ejecutar wizard nuevamente</a></p>";
echo "<p><a href='index.php' style='color: white;'>Ir al sistema principal</a></p>";
echo "</div>";

echo "<div style='background: #f39c12; color: white; padding: 20px; text-align: center; font-family: Arial; margin-top: 10px;'>";
echo "<h3>⚠️ Opción Avanzada</h3>";
echo "<p>Si también quieres eliminar TODOS los datos (buses, cubiertas, etc.):</p>";
echo "<p><a href='reset_wizard.php?limpiar_datos=1' style='color: white; font-weight: bold;' onclick='return confirm(\"¿ESTÁS SEGURO? Esto eliminará TODOS los datos del sistema.\")'>Limpiar todo y empezar desde cero</a></p>";
echo "</div>";
?>