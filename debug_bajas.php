<?php
// debug_bajas.php - Script para debuggear el problema con energy-200 y energy-201

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

echo "<h2>🔍 Debug: Investigando energy-200 y energy-201</h2>";

// 1. Verificar si existen las cubiertas en la tabla cubiertas
echo "<h3>1. Verificando existencia en tabla 'cubiertas':</h3>";
$sql_verificar = "SELECT id, nombre, estado, coche_id FROM cubiertas WHERE nombre LIKE '%energy%' ORDER BY nombre";
$result_verificar = $conn->query($sql_verificar);

if ($result_verificar->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Coche ID</th></tr>";
    while ($row = $result_verificar->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['nombre'] . "</td>";
        echo "<td>" . $row['estado'] . "</td>";
        echo "<td>" . ($row['coche_id'] ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No se encontraron cubiertas con 'energy' en el nombre.";
}

// 2. Verificar si están en historial_bajas
echo "<h3>2. Verificando en tabla 'historial_bajas':</h3>";
$sql_historial = "SELECT * FROM historial_bajas WHERE cubierta_nombre LIKE '%energy%' ORDER BY cubierta_nombre, fecha_operacion";
$result_historial = $conn->query($sql_historial);

if ($result_historial->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Cubierta ID</th><th>Nombre</th><th>Operación</th><th>Motivo</th><th>Fecha</th></tr>";
    while ($row = $result_historial->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['cubierta_id'] . "</td>";
        echo "<td>" . $row['cubierta_nombre'] . "</td>";
        echo "<td>" . $row['tipo_operacion'] . "</td>";
        echo "<td>" . ($row['motivo'] ?: 'NULL') . "</td>";
        echo "<td>" . $row['fecha_operacion'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No se encontraron registros de 'energy' en historial_bajas.";
}

// 3. Verificar qué consulta usa baja.php exactamente
echo "<h3>3. Simulando la consulta de baja.php:</h3>";

// Esta es la consulta que usa baja.php para obtener registros
$desde = date('Y-m-d', strtotime('-1 month')) . ' 00:00:00';
$hasta = date('Y-m-d') . ' 23:59:59';

$sql_bajas_original = "
SELECT 
    hb.id,
    hb.cubierta_id,
    hb.cubierta_nombre,
    hb.tipo_operacion,
    hb.motivo,
    hb.fecha_operacion,
    
    COALESCE(hb.fecha_colocacion, 
        (SELECT MIN(hc.fecha_colocacion) 
         FROM historial_cubiertas hc 
         WHERE hc.cubierta_id = hb.cubierta_id)
    ) AS fecha_colocacion,
    
    COALESCE(hb.fecha_retiro, 
        (SELECT MAX(hc.fecha_retiro) 
         FROM historial_cubiertas hc 
         WHERE hc.cubierta_id = hb.cubierta_id)
    ) AS fecha_retiro,
    
    (SELECT MIN(hc.kilometraje_colocacion) 
     FROM historial_cubiertas hc 
     WHERE hc.cubierta_id = hb.cubierta_id 
     ORDER BY hc.fecha_colocacion ASC 
     LIMIT 1) AS kilometraje_colocacion,
    
    COALESCE(hb.kilometraje_retiro, 
        (SELECT MAX(hc.kilometraje_retiro) 
         FROM historial_cubiertas hc 
         WHERE hc.cubierta_id = hb.cubierta_id)
    ) AS kilometraje_retiro
    
FROM historial_bajas hb
WHERE hb.fecha_operacion BETWEEN ? AND ?
ORDER BY hb.fecha_operacion DESC
";

echo "<p><strong>Consulta completa de baja.php:</strong></p>";
echo "<pre>" . htmlspecialchars($sql_bajas_original) . "</pre>";
echo "<p><strong>Parámetros:</strong> Desde: $desde, Hasta: $hasta</p>";

$stmt = $conn->prepare($sql_bajas_original);
$stmt->bind_param("ss", $desde, $hasta);
$stmt->execute();
$result_bajas_original = $stmt->get_result();

echo "<h4>Resultados de la consulta de baja.php:</h4>";
if ($result_bajas_original->num_rows > 0) {
    echo "<p>Se encontraron " . $result_bajas_original->num_rows . " registros en el rango de fechas.</p>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; font-size: 12px;'>";
    echo "<tr><th>Cubierta ID</th><th>Nombre</th><th>Operación</th><th>Fecha</th></tr>";
    
    $energy_encontradas = 0;
    while ($row = $result_bajas_original->fetch_assoc()) {
        $is_energy = strpos(strtolower($row['cubierta_nombre']), 'energy') !== false;
        if ($is_energy) $energy_encontradas++;
        
        echo "<tr" . ($is_energy ? " style='background-color: #ffffcc;'" : "") . ">";
        echo "<td>" . $row['cubierta_id'] . "</td>";
        echo "<td>" . $row['cubierta_nombre'] . "</td>";
        echo "<td>" . $row['tipo_operacion'] . "</td>";
        echo "<td>" . $row['fecha_operacion'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><strong>Cubiertas 'energy' encontradas en los resultados: $energy_encontradas</strong></p>";
} else {
    echo "❌ La consulta de baja.php no devuelve ningún resultado.";
}

$stmt->close();

// 4. Verificar si las energy están fuera del rango de fechas
echo "<h3>4. Verificando todas las fechas de energy en historial_bajas:</h3>";
$sql_energy_todas = "SELECT cubierta_id, cubierta_nombre, tipo_operacion, fecha_operacion 
                     FROM historial_bajas 
                     WHERE cubierta_nombre LIKE '%energy%' 
                     ORDER BY fecha_operacion DESC";
$result_energy_todas = $conn->query($sql_energy_todas);

if ($result_energy_todas->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Cubierta ID</th><th>Nombre</th><th>Operación</th><th>Fecha</th><th>En rango?</th></tr>";
    while ($row = $result_energy_todas->fetch_assoc()) {
        $fecha_op = $row['fecha_operacion'];
        $en_rango = ($fecha_op >= $desde && $fecha_op <= $hasta) ? "✅ SÍ" : "❌ NO";
        
        echo "<tr>";
        echo "<td>" . $row['cubierta_id'] . "</td>";
        echo "<td>" . $row['cubierta_nombre'] . "</td>";
        echo "<td>" . $row['tipo_operacion'] . "</td>";
        echo "<td>" . $fecha_op . "</td>";
        echo "<td>" . $en_rango . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No hay registros de energy en historial_bajas en ninguna fecha.";
}

// 5. Verificar estructura de tabla historial_bajas
echo "<h3>5. Estructura de tabla historial_bajas:</h3>";
$sql_describe = "DESCRIBE historial_bajas";
$result_describe = $conn->query($sql_describe);

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result_describe->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . ($row['Default'] ?: 'NULL') . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// 6. Solución sugerida
echo "<h3>6. 🔧 Posibles soluciones:</h3>";
echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";

if ($result_energy_todas->num_rows == 0) {
    echo "<p><strong>Problema identificado:</strong> Las cubiertas energy-200 y energy-201 NO están registradas en historial_bajas.</p>";
    echo "<p><strong>Soluciones:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Ejecutar poblar_historial_bajas.php</strong> para migrar los registros existentes</li>";
    echo "<li><strong>Registrar manualmente</strong> usando el formulario de gomería</li>";
    echo "<li><strong>Usar ajustar_silacor.php</strong> si fueron enviadas a Silacor</li>";
    echo "</ol>";
} else {
    echo "<p><strong>Problema identificado:</strong> Las cubiertas energy están registradas pero fuera del rango de fechas mostrado en baja.php.</p>";
    echo "<p><strong>Solución:</strong> Ajustar el filtro de fechas en baja.php para incluir fechas más antiguas.</p>";
}

echo "</div>";

// 7. Script de corrección automática
echo "<h3>7. 🛠️ Script de corrección automática:</h3>";
echo "<form method='POST'>";
echo "<button type='submit' name='corregir_energy' style='background: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;'>";
echo "Registrar energy-200 y energy-201 en historial_bajas";
echo "</button>";
echo "</form>";

if (isset($_POST['corregir_energy'])) {
    echo "<h4>Ejecutando corrección automática:</h4>";
    
    // Buscar las cubiertas energy que no están en historial_bajas
    $sql_energy_faltantes = "SELECT c.id, c.nombre, c.estado 
                            FROM cubiertas c 
                            WHERE c.nombre LIKE '%energy%' 
                            AND c.id NOT IN (SELECT cubierta_id FROM historial_bajas WHERE cubierta_id = c.id)";
    
    $result_faltantes = $conn->query($sql_energy_faltantes);
    
    if ($result_faltantes->num_rows > 0) {
        while ($cubierta = $result_faltantes->fetch_assoc()) {
            // Registrar como ALTA
            $sql_insertar = "INSERT INTO historial_bajas 
                           (cubierta_id, cubierta_nombre, tipo_operacion, motivo, fecha_operacion) 
                           VALUES (?, ?, 'alta', 'Registro corregido - Cubierta agregada al sistema', NOW())";
            
            $stmt_insertar = $conn->prepare($sql_insertar);
            $stmt_insertar->bind_param("is", $cubierta['id'], $cubierta['nombre']);
            
            if ($stmt_insertar->execute()) {
                echo "<p>✅ Registrada: " . $cubierta['nombre'] . " (ID: " . $cubierta['id'] . ")</p>";
            } else {
                echo "<p>❌ Error al registrar: " . $cubierta['nombre'] . " - " . $stmt_insertar->error . "</p>";
            }
            
            $stmt_insertar->close();
        }
        
        echo "<p><strong>🎉 Corrección completada. Recarga la página para ver los resultados actualizados.</strong></p>";
    } else {
        echo "<p>ℹ️ No se encontraron cubiertas energy que necesiten corrección.</p>";
    }
}

$conn->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    color: #333;
    padding: 20px;
    line-height: 1.6;
}

h2, h3, h4 {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 5px;
}

table {
    width: 100%;
    max-width: 800px;
    background: white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

th {
    background: #3498db;
    color: white;
    padding: 10px;
    text-align: left;
}

td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}

pre {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #3498db;
    overflow-x: auto;
    font-size: 12px;
}

.highlight {
    background-color: #ffffcc !important;
}
</style>