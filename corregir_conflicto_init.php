<?php
// corregir_conflicto_init.php - Corrige el problema después de ejecutar init_database.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

echo "<h2>🔧 Corrección de Conflicto init_database.php</h2>";
echo "<p>Este script corrige los problemas causados por el reseteo de AUTO_INCREMENT.</p>";

// Paso 1: Identificar cubiertas que existen pero no están en historial_bajas
$sql_huerfanas = "
    SELECT c.id, c.nombre, c.estado, c.coche_id 
    FROM cubiertas c 
    WHERE c.id NOT IN (
        SELECT DISTINCT cubierta_id 
        FROM historial_bajas 
        WHERE cubierta_id = c.id
    )
    ORDER BY c.id
";

$result_huerfanas = $conn->query($sql_huerfanas);
$cubiertas_huerfanas = [];

if ($result_huerfanas && $result_huerfanas->num_rows > 0) {
    while ($row = $result_huerfanas->fetch_assoc()) {
        $cubiertas_huerfanas[] = $row;
    }
}

echo "<div class='info'>📊 <strong>Diagnóstico:</strong></div>";
echo "<div class='warning'>Se encontraron " . count($cubiertas_huerfanas) . " cubiertas sin registro en historial_bajas</div>";

if (!empty($cubiertas_huerfanas)) {
    echo "<div class='info'>🔍 <strong>Cubiertas que necesitan corrección:</strong></div>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; color: white;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Coche ID</th></tr>";
    
    foreach ($cubiertas_huerfanas as $cubierta) {
        $highlight = (strpos(strtolower($cubierta['nombre']), 'energy') !== false) ? "style='background-color: #f39c12;'" : "";
        echo "<tr $highlight>";
        echo "<td>" . $cubierta['id'] . "</td>";
        echo "<td>" . $cubierta['nombre'] . "</td>";
        echo "<td>" . $cubierta['estado'] . "</td>";
        echo "<td>" . ($cubierta['coche_id'] ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Paso 2: Ofrecer corrección automática
    if (isset($_POST['corregir_automatico'])) {
        echo "<div class='info'>🔧 <strong>Ejecutando corrección automática...</strong></div>";
        
        $corregidas = 0;
        $errores_correccion = 0;
        
        foreach ($cubiertas_huerfanas as $cubierta) {
            $cubierta_id = $cubierta['id'];
            $cubierta_nombre = $cubierta['nombre'];
            
            // Determinar fecha de operación
            $sql_fecha_hist = "SELECT MIN(fecha_colocacion) as primera_fecha 
                              FROM historial_cubiertas 
                              WHERE cubierta_id = ?";
            $stmt_fecha = $conn->prepare($sql_fecha_hist);
            $stmt_fecha->bind_param("i", $cubierta_id);
            $stmt_fecha->execute();
            $result_fecha = $stmt_fecha->get_result();
            $fecha_data = $result_fecha->fetch_assoc();
            
            // Si no hay historial, usar fecha actual
            $fecha_operacion = $fecha_data['primera_fecha'] ?: date('Y-m-d H:i:s');
            $stmt_fecha->close();
            
            // Insertar en historial_bajas como ALTA
            $sql_insert = "INSERT INTO historial_bajas 
                          (cubierta_id, cubierta_nombre, tipo_operacion, motivo, fecha_operacion, coche_id) 
                          VALUES (?, ?, 'alta', 'Registro corregido después de init_database.php', ?, ?)";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("issi", $cubierta_id, $cubierta_nombre, $fecha_operacion, $cubierta['coche_id']);
            
            if ($stmt_insert->execute()) {
                echo "<div class='success'>✅ Corregida: $cubierta_nombre (ID: $cubierta_id)</div>";
                $corregidas++;
            } else {
                echo "<div class='error'>❌ Error al corregir: $cubierta_nombre - " . $stmt_insert->error . "</div>";
                $errores_correccion++;
            }
            
            $stmt_insert->close();
        }
        
        echo "<div class='info'>📊 <strong>Resultado de la corrección:</strong></div>";
        echo "<div class='success'>✅ Cubiertas corregidas: $corregidas</div>";
        if ($errores_correccion > 0) {
            echo "<div class='error'>❌ Errores: $errores_correccion</div>";
        }
        
        // Verificación final
        $result_final = $conn->query($sql_huerfanas);
        $restantes = $result_final->num_rows;
        
        if ($restantes == 0) {
            echo "<div class='success'>🎉 ¡Corrección completada! Todas las cubiertas ahora tienen registro en historial_bajas.</div>";
            echo "<div class='info'><a href='baja.php' style='color: #3498db; font-weight: bold;'>🔗 Ver historial de bajas corregido</a></div>";
        } else {
            echo "<div class='warning'>⚠️ Aún quedan $restantes cubiertas sin corregir.</div>";
        }
        
    } else {
        // Mostrar formulario para confirmar corrección
        echo "<div class='warning'>";
        echo "<h3>🛠️ ¿Ejecutar corrección automática?</h3>";
        echo "<p>Esto registrará todas las cubiertas huérfanas como 'ALTA' en la tabla historial_bajas.</p>";
        echo "<form method='POST'>";
        echo "<button type='submit' name='corregir_automatico' style='background: #27ae60; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer;'>";
        echo "✅ Sí, corregir automáticamente";
        echo "</button>";
        echo "</form>";
        echo "</div>";
    }
    
} else {
    echo "<div class='success'>🎉 ¡Perfecto! No se encontraron problemas. Todas las cubiertas están correctamente registradas.</div>";
}

// Paso 3: Verificar estado de las energy específicamente
echo "<div class='info'>🔍 <strong>Verificación específica de cubiertas 'energy':</strong></div>";

$sql_energy = "SELECT c.id, c.nombre, c.estado,
               (SELECT COUNT(*) FROM historial_bajas hb WHERE hb.cubierta_id = c.id) as en_historial
               FROM cubiertas c 
               WHERE c.nombre LIKE '%energy%' 
               ORDER BY c.nombre";

$result_energy = $conn->query($sql_energy);

if ($result_energy && $result_energy->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; color: white;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Estado</th><th>En Historial</th><th>Status</th></tr>";
    
    while ($row = $result_energy->fetch_assoc()) {
        $status = $row['en_historial'] > 0 ? "✅ OK" : "❌ FALTA";
        $color = $row['en_historial'] > 0 ? "#27ae60" : "#e74c3c";
        
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['nombre'] . "</td>";
        echo "<td>" . $row['estado'] . "</td>";
        echo "<td>" . $row['en_historial'] . "</td>";
        echo "<td style='color: $color; font-weight: bold;'>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning'>⚠️ No se encontraron cubiertas 'energy' en el sistema.</div>";
}

$conn->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    background: #2c3e50;
    color: #ecf0f1;
    padding: 20px;
    line-height: 1.6;
}

h2, h3 {
    color: #3498db;
}

table {
    background: #34495e;
    color: white;
    width: 100%;
    max-width: 800px;
}

th {
    background: #2c3e50;
    padding: 10px;
    text-align: left;
}

td {
    padding: 8px;
    border-bottom: 1px solid #444;
}

.info {
    background: rgba(52, 152, 219, 0.2);
    border-left: 5px solid #3498db;
    color: #ecf0f1;
    padding: 12px;
    margin: 10px 0;
    border-radius: 0 5px 5px 0;
}

.success {
    background: rgba(46, 204, 113, 0.2);
    border-left: 5px solid #2ecc71;
    color: #ecf0f1;
    padding: 12px;
    margin: 10px 0;
    border-radius: 0 5px 5px 0;
}

.warning {
    background: rgba(243, 156, 18, 0.2);
    border-left: 5px solid #f39c12;
    color: #ecf0f1;
    padding: 12px;
    margin: 10px 0;
    border-radius: 0 5px 5px 0;
}

.error {
    background: rgba(231, 76, 60, 0.2);
    border-left: 5px solid #e74c3c;
    color: #ecf0f1;
    padding: 12px;
    margin: 10px 0;
    border-radius: 0 5px 5px 0;
}
</style>