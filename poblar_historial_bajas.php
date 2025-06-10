<?php
// poblar_historial_bajas.php - VERSIÓN CORREGIDA

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Crear tabla historial_bajas si no existe
$sql_create_table = "CREATE TABLE IF NOT EXISTS historial_bajas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cubierta_id INT NOT NULL,
    cubierta_nombre VARCHAR(100) NOT NULL,
    tipo_operacion ENUM('alta', 'baja') NOT NULL,
    motivo VARCHAR(100) NULL,
    fecha_operacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_colocacion DATE NULL,
    fecha_retiro DATE NULL,
    kilometraje_retiro INT NULL,
    coche_id INT NULL,
    fecha_reconstruccion DATE NULL,
    usuario VARCHAR(50) NULL
)";

if (!$conn->query($sql_create_table)) {
    echo "Error al crear la tabla: " . $conn->error;
    exit;
}

echo "<h2>🔄 Poblando historial de bajas...</h2>";

// 1. REGISTRAR TODAS LAS CUBIERTAS EXISTENTES COMO ALTAS
$sql_cubiertas = "SELECT c.id, c.nombre, c.coche_id
                  FROM cubiertas c
                  WHERE c.id NOT IN (SELECT cubierta_id FROM historial_bajas WHERE cubierta_id = c.id)";

$result_cubiertas = $conn->query($sql_cubiertas);
$altas_registradas = 0;

if ($result_cubiertas && $result_cubiertas->num_rows > 0) {
    while ($cubierta = $result_cubiertas->fetch_assoc()) {
        // Obtener fecha de primera colocación si existe
        $sql_fecha = "SELECT MIN(fecha_colocacion) as primera_fecha FROM historial_cubiertas WHERE cubierta_id = ?";
        $stmt_fecha = $conn->prepare($sql_fecha);
        $stmt_fecha->bind_param("i", $cubierta['id']);
        $stmt_fecha->execute();
        $result_fecha = $stmt_fecha->get_result();
        $fecha_data = $result_fecha->fetch_assoc();
        $fecha_operacion = $fecha_data['primera_fecha'] ?: date('Y-m-d H:i:s');
        $stmt_fecha->close();
        
        // Registrar como ALTA
        $sql_alta = "INSERT INTO historial_bajas 
                     (cubierta_id, cubierta_nombre, tipo_operacion, motivo, fecha_operacion, coche_id) 
                     VALUES (?, ?, 'alta', 'Registro inicial - Cubierta agregada al sistema', ?, ?)";
        
        $stmt_alta = $conn->prepare($sql_alta);
        $stmt_alta->bind_param("issi", 
            $cubierta['id'], 
            $cubierta['nombre'], 
            $fecha_operacion,
            $cubierta['coche_id']
        );
        
        if ($stmt_alta->execute()) {
            $altas_registradas++;
        }
        $stmt_alta->close();
    }
}

echo "<p>✅ Registradas $altas_registradas cubiertas como ALTAS</p>";

// 2. REGISTRAR CUBIERTAS QUE FUERON ENVIADAS A SILACOR
$sql_silacor = "SELECT c.id, c.nombre
                FROM cubiertas c
                WHERE c.estado = 'silacor'
                AND c.id NOT IN (
                    SELECT cubierta_id FROM historial_bajas 
                    WHERE cubierta_id = c.id AND tipo_operacion = 'baja' AND motivo LIKE '%SILACOR%'
                )";

$result_silacor = $conn->query($sql_silacor);
$silacor_registradas = 0;

if ($result_silacor && $result_silacor->num_rows > 0) {
    while ($cubierta = $result_silacor->fetch_assoc()) {
        // Obtener fecha de último retiro si existe
        $sql_fecha_retiro = "SELECT MAX(fecha_retiro) as ultima_fecha FROM historial_cubiertas WHERE cubierta_id = ? AND fecha_retiro IS NOT NULL";
        $stmt_fecha_ret = $conn->prepare($sql_fecha_retiro);
        $stmt_fecha_ret->bind_param("i", $cubierta['id']);
        $stmt_fecha_ret->execute();
        $result_fecha_ret = $stmt_fecha_ret->get_result();
        $fecha_ret_data = $result_fecha_ret->fetch_assoc();
        $fecha_baja = $fecha_ret_data['ultima_fecha'] ?: date('Y-m-d H:i:s');
        $stmt_fecha_ret->close();
        
        // Registrar como BAJA por envío a SILACOR
        $sql_baja_silacor = "INSERT INTO historial_bajas 
                            (cubierta_id, cubierta_nombre, tipo_operacion, motivo, fecha_operacion) 
                            VALUES (?, ?, 'baja', 'Enviada a SILACOR para reconstrucción', ?)";
        
        $stmt_baja = $conn->prepare($sql_baja_silacor);
        $stmt_baja->bind_param("iss", $cubierta['id'], $cubierta['nombre'], $fecha_baja);
        
        if ($stmt_baja->execute()) {
            $silacor_registradas++;
        }
        $stmt_baja->close();
    }
}

echo "<p>✅ Registradas $silacor_registradas cubiertas enviadas a SILACOR</p>";

// 3. REGISTRAR CUBIERTAS DADAS DE BAJA
$sql_bajas = "SELECT c.id, c.nombre
              FROM cubiertas c
              WHERE c.estado = 'baja'
              AND c.id NOT IN (
                  SELECT cubierta_id FROM historial_bajas 
                  WHERE cubierta_id = c.id AND tipo_operacion = 'baja' AND motivo NOT LIKE '%SILACOR%'
              )";

$result_bajas = $conn->query($sql_bajas);
$bajas_registradas = 0;

if ($result_bajas && $result_bajas->num_rows > 0) {
    while ($cubierta = $result_bajas->fetch_assoc()) {
        // Obtener fecha de último retiro si existe
        $sql_fecha_retiro = "SELECT MAX(fecha_retiro) as ultima_fecha FROM historial_cubiertas WHERE cubierta_id = ? AND fecha_retiro IS NOT NULL";
        $stmt_fecha_ret = $conn->prepare($sql_fecha_retiro);
        $stmt_fecha_ret->bind_param("i", $cubierta['id']);
        $stmt_fecha_ret->execute();
        $result_fecha_ret = $stmt_fecha_ret->get_result();
        $fecha_ret_data = $result_fecha_ret->fetch_assoc();
        $fecha_baja = $fecha_ret_data['ultima_fecha'] ?: date('Y-m-d H:i:s');
        $stmt_fecha_ret->close();
        
        // Registrar como BAJA
        $sql_baja = "INSERT INTO historial_bajas 
                     (cubierta_id, cubierta_nombre, tipo_operacion, motivo, fecha_operacion) 
                     VALUES (?, ?, 'baja', 'Cubierta dada de baja - Registro histórico', ?)";
        
        $stmt_baja = $conn->prepare($sql_baja);
        $stmt_baja->bind_param("iss", $cubierta['id'], $cubierta['nombre'], $fecha_baja);
        
        if ($stmt_baja->execute()) {
            $bajas_registradas++;
        }
        $stmt_baja->close();
    }
}

echo "<p>✅ Registradas $bajas_registradas cubiertas dadas de baja</p>";

// 4. COMPLETAR INFORMACIÓN DE KILOMETRAJE DESDE historial_cubiertas
$sql_update_km = "UPDATE historial_bajas hb
                  JOIN (
                      SELECT 
                          cubierta_id,
                          MIN(fecha_colocacion) as primera_colocacion,
                          MAX(CASE WHEN fecha_retiro IS NOT NULL THEN fecha_retiro END) as ultimo_retiro,
                          MAX(CASE WHEN kilometraje_retiro IS NOT NULL THEN kilometraje_retiro END) as max_km_retiro
                      FROM historial_cubiertas 
                      GROUP BY cubierta_id
                  ) hc ON hb.cubierta_id = hc.cubierta_id
                  SET 
                      hb.fecha_colocacion = DATE(hc.primera_colocacion),
                      hb.fecha_retiro = DATE(hc.ultimo_retiro),
                      hb.kilometraje_retiro = hc.max_km_retiro
                  WHERE hb.fecha_colocacion IS NULL OR hb.kilometraje_retiro IS NULL";

$conn->query($sql_update_km);
echo "<p>✅ Actualizada información de kilometraje desde historial_cubiertas</p>";

// 5. AGREGAR INFORMACIÓN DE RECONSTRUCCIONES
$sql_update_reconstru = "UPDATE historial_bajas hb
                        JOIN (
                            SELECT cubierta_id, MAX(fecha_reconstruccion) as ultima_reconstru
                            FROM reconstrucciones 
                            GROUP BY cubierta_id
                        ) r ON hb.cubierta_id = r.cubierta_id
                        SET hb.fecha_reconstruccion = r.ultima_reconstru
                        WHERE hb.fecha_reconstruccion IS NULL";

$conn->query($sql_update_reconstru);
echo "<p>✅ Actualizada información de reconstrucciones</p>";

// 6. VERIFICAR SI EXISTEN TABLAS Y MOSTRAR INFORMACIÓN ADICIONAL
echo "<div style='background: #34495e; padding: 15px; margin: 20px 0; border-radius: 8px;'>";
echo "<h3>📋 Información del sistema:</h3>";

// Verificar estructura de tabla cubiertas
$sql_describe = "DESCRIBE cubiertas";
$result_describe = $conn->query($sql_describe);
echo "<p><strong>Columnas en tabla cubiertas:</strong> ";
$columnas = [];
while ($col = $result_describe->fetch_assoc()) {
    $columnas[] = $col['Field'];
}
echo implode(', ', $columnas) . "</p>";

// Contar registros en cada tabla
$tablas_info = [
    'cubiertas' => 'Cubiertas totales',
    'coches' => 'Buses registrados', 
    'historial_cubiertas' => 'Registros de historial',
    'reconstrucciones' => 'Reconstrucciones',
    'historial_bajas' => 'Registros de altas/bajas'
];

foreach ($tablas_info as $tabla => $descripcion) {
    $sql_count = "SELECT COUNT(*) as total FROM $tabla";
    $result_count = $conn->query($sql_count);
    if ($result_count) {
        $count_data = $result_count->fetch_assoc();
        echo "<p><strong>$descripcion:</strong> " . $count_data['total'] . "</p>";
    }
}
echo "</div>";

// Mostrar resumen final
$sql_resumen = "SELECT 
                    SUM(CASE WHEN tipo_operacion = 'alta' THEN 1 ELSE 0 END) as total_altas,
                    SUM(CASE WHEN tipo_operacion = 'baja' THEN 1 ELSE 0 END) as total_bajas,
                    COUNT(*) as total_registros
                FROM historial_bajas";

$result_resumen = $conn->query($sql_resumen);
$resumen = $result_resumen->fetch_assoc();

echo "<div style='background: #2ecc71; color: white; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
echo "<h3>📊 Resumen Final:</h3>";
echo "<p><strong>Total de registros en historial_bajas:</strong> " . $resumen['total_registros'] . "</p>";
echo "<p><strong>Altas registradas:</strong> " . $resumen['total_altas'] . "</p>";
echo "<p><strong>Bajas registradas:</strong> " . $resumen['total_bajas'] . "</p>";
echo "<p><a href='baja.php' style='color: white; font-weight: bold; text-decoration: none; background: rgba(255,255,255,0.2); padding: 10px; border-radius: 5px;'>🔗 Ir a ver el historial de bajas</a></p>";
echo "</div>";

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

p {
    background: rgba(255,255,255,0.1);
    padding: 12px;
    border-radius: 5px;
    margin: 10px 0;
}

.error {
    background: #e74c3c;
    color: white;
    padding: 15px;
    border-radius: 5px;
    margin: 10px 0;
}
</style>