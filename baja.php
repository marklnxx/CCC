<?php

// Configuraci贸n de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

// Conexi贸n a la base de datos
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexi贸n fallida: " . $conn->connect_error);
}

// Crear la tabla historial_bajas si no existe (con el nuevo ENUM que incluye 'silacor')
$sql_create_table = "CREATE TABLE IF NOT EXISTS historial_bajas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cubierta_id INT NOT NULL,
    cubierta_nombre VARCHAR(100) NOT NULL,
    tipo_operacion ENUM('alta', 'baja', 'silacor') NOT NULL,
    motivo VARCHAR(100) NULL,
    fecha_operacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_colocacion DATE NULL,
    fecha_retiro DATE NULL,
    kilometraje_retiro INT NULL,
    coche_id INT NULL,
    fecha_reconstruccion DATE NULL,
    usuario VARCHAR(50) NULL,
    FOREIGN KEY (cubierta_id) REFERENCES cubiertas(id) ON DELETE CASCADE
)";

if (!$conn->query($sql_create_table)) {
    echo "Error al crear la tabla: " . $conn->error;
}

// 馃敡 ACTUALIZAR LA TABLA SI YA EXISTE PARA INCLUIR 'silacor'
$sql_check_enum = "SHOW COLUMNS FROM historial_bajas LIKE 'tipo_operacion'";
$result_check = $conn->query($sql_check_enum);
if ($result_check->num_rows > 0) {
    $row = $result_check->fetch_assoc();
    if (strpos($row['Type'], 'silacor') === false) {
        // Si no incluye 'silacor', actualizar el ENUM
        $sql_update_enum = "ALTER TABLE historial_bajas 
                           MODIFY COLUMN tipo_operacion ENUM('alta', 'baja', 'silacor') NOT NULL";
        $conn->query($sql_update_enum);
    }
}

// Configuraci贸n de filtros
$desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-d', strtotime('-9 months'));
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d');
$tipo_filtro = isset($_GET['tipo_filtro']) ? $_GET['tipo_filtro'] : 'todos';
$motivo_filtro = isset($_GET['motivo_filtro']) ? $_GET['motivo_filtro'] : '';
$bus_filtro = isset($_GET['bus_filtro']) ? $_GET['bus_filtro'] : '';

// Procesar la baja de cubierta para que no se pierda 
if (isset($_POST['dar_baja']) && isset($_POST['cubierta_id_baja'])) {
    $cubierta_id = $_POST['cubierta_id_baja'];
    $motivo = isset($_POST['motivo_baja']) ? $_POST['motivo_baja'] : 'Baja sin motivo especificado';
    
    // Obtener informaci贸n de la cubierta antes de darla de baja
    $sql_info_cubierta = "SELECT c.nombre, c.coche_id, c.estado,
                          (SELECT MAX(h.fecha_colocacion) FROM historial_cubiertas h WHERE h.cubierta_id = c.id) as fecha_colocacion,
                          (SELECT MAX(h.fecha_retiro) FROM historial_cubiertas h WHERE h.cubierta_id = c.id) as fecha_retiro,
                          (SELECT MAX(h.kilometraje_retiro) FROM historial_cubiertas h WHERE h.cubierta_id = c.id) as km_retiro
                          FROM cubiertas c WHERE c.id = ?";
    $stmt_info = $conn->prepare($sql_info_cubierta);
    $stmt_info->bind_param("i", $cubierta_id);
    $stmt_info->execute();
    $result_info = $stmt_info->get_result();
    $cubierta_info = $result_info->fetch_assoc();
    $stmt_info->close();
    
    // Iniciar transacci贸n
    $conn->begin_transaction();
    
    try {
        // Registrar la baja en historial_bajas
        $sql_historial_baja = "INSERT INTO historial_bajas 
                              (cubierta_id, cubierta_nombre, tipo_operacion, motivo, fecha_colocacion, fecha_retiro, kilometraje_retiro, coche_id) 
                              VALUES (?, ?, 'baja', ?, ?, ?, ?, ?)";
        $stmt_historial = $conn->prepare($sql_historial_baja);
        $stmt_historial->bind_param("issssii", $cubierta_id, $cubierta_info['nombre'], $motivo, $cubierta_info['fecha_colocacion'], 
                                  $cubierta_info['fecha_retiro'], $cubierta_info['km_retiro'], $cubierta_info['coche_id']);
        $stmt_historial->execute();
        $stmt_historial->close();
        
        // Si la cubierta est谩 asignada a un coche, actualizar el historial_cubiertas
        if ($cubierta_info['coche_id']) {
            $sql_actualizar_historial = "UPDATE historial_cubiertas 
                                        SET fecha_retiro = NOW() 
                                        WHERE cubierta_id = ? AND fecha_retiro IS NULL";
            $stmt_act_historial = $conn->prepare($sql_actualizar_historial);
            $stmt_act_historial->bind_param("i", $cubierta_id);
            $stmt_act_historial->execute();
            $stmt_act_historial->close();
        }
        
        // En lugar de eliminar la cubierta, marcarla como "baja" o "inactiva"
        // Esto permite mantener un registro de todas las cubiertas, incluso las dadas de baja
        $estado_baja = "baja";
        $sql_update = "UPDATE cubiertas 
                      SET estado = ?, coche_id = NULL 
                      WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $estado_baja, $cubierta_id);
        $stmt_update->execute();
        $stmt_update->close();
        
        // Confirmar la transacci贸n
        $conn->commit();
        $mensaje_exito = "Cubierta dada de baja exitosamente y registrada en el historial.";
    } catch (Exception $e) {
        // Revertir transacci贸n en caso de error
        $conn->rollback();
        $mensaje_error = "Error al dar de baja la cubierta: " . $e->getMessage();
    }
}

// Preparar consulta con par谩metros seguros para prevenir inyecci贸n SQL
$sql_bajas = "
SELECT 
    hb.id,
    hb.cubierta_id,
    hb.cubierta_nombre,
    hb.tipo_operacion,
    hb.motivo,
    hb.fecha_operacion,
    
    /* Datos de kilometraje y fechas */
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
    ) AS kilometraje_retiro,
    
    /* Kil贸metros recorridos (calculado) */
    (
        COALESCE(hb.kilometraje_retiro, 
            (SELECT MAX(hc.kilometraje_retiro) 
             FROM historial_cubiertas hc 
             WHERE hc.cubierta_id = hb.cubierta_id)
        ) - 
        (SELECT MIN(hc.kilometraje_colocacion) 
         FROM historial_cubiertas hc 
         WHERE hc.cubierta_id = hb.cubierta_id 
         ORDER BY hc.fecha_colocacion ASC 
         LIMIT 1)
    ) AS km_recorridos,
    
    COALESCE(hb.coche_id, 
        (SELECT hc.coche_id 
         FROM historial_cubiertas hc 
         WHERE hc.cubierta_id = hb.cubierta_id 
         ORDER BY hc.fecha_colocacion DESC 
         LIMIT 1)
    ) AS coche_id,
    
    (SELECT r.fecha_reconstruccion 
     FROM reconstrucciones r 
     WHERE r.cubierta_id = hb.cubierta_id 
     ORDER BY r.fecha_reconstruccion DESC 
     LIMIT 1) AS fecha_reconstruccion
    
FROM historial_bajas hb
WHERE hb.fecha_operacion BETWEEN ? AND ?
";

// Preparar la consulta principal
$stmt = $conn->prepare($sql_bajas);

// Definir los par谩metros de la consulta base
$desde_inicio = $desde . ' 00:00:00';
$hasta_fin = $hasta . ' 23:59:59';
$tipos_param = "ss"; // Inicialmente dos par谩metros string
$params = [$desde_inicio, $hasta_fin];

// Construir la consulta con condiciones adicionales
$condiciones_adicionales = "";

// 馃敡 MODIFICACI脫N: Incluir filtro para 'silacor'
if ($tipo_filtro == 'alta') {
    $condiciones_adicionales .= " AND hb.tipo_operacion = 'alta'";
} elseif ($tipo_filtro == 'baja') {
    $condiciones_adicionales .= " AND hb.tipo_operacion = 'baja'";
} elseif ($tipo_filtro == 'silacor') {
    $condiciones_adicionales .= " AND hb.tipo_operacion = 'silacor'";
}

if (!empty($motivo_filtro)) {
    $condiciones_adicionales .= " AND hb.motivo LIKE ?";
    $tipos_param .= "s";
    $params[] = "%$motivo_filtro%";
}

if (!empty($bus_filtro)) {
    $condiciones_adicionales .= " AND (hb.coche_id = ? OR 
                         (SELECT hc.coche_id 
                          FROM historial_cubiertas hc 
                          WHERE hc.cubierta_id = hb.cubierta_id 
                          ORDER BY hc.fecha_colocacion DESC 
                          LIMIT 1) = ?)";
    $tipos_param .= "ii";
    $params[] = $bus_filtro;
    $params[] = $bus_filtro;
}

// A帽adir las condiciones a la consulta
$sql_bajas .= $condiciones_adicionales . " ORDER BY hb.fecha_operacion DESC";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($sql_bajas);

// Vincular los par谩metros
if (!empty($params)) {
    $stmt->bind_param($tipos_param, ...$params);
}

$stmt->execute();
$result_bajas = $stmt->get_result();
$registros_filtrados = [];

if ($result_bajas && $result_bajas->num_rows > 0) {
    while ($row = $result_bajas->fetch_assoc()) {
        $registros_filtrados[] = $row;
    }
}

$stmt->close();

// Obtener motivos distintos para el selector de filtro
$sql_motivos = "SELECT DISTINCT motivo FROM historial_bajas WHERE motivo IS NOT NULL AND motivo != '' ORDER BY motivo";
$result_motivos = $conn->query($sql_motivos);
$motivos = [];

if ($result_motivos && $result_motivos->num_rows > 0) {
    while ($row = $result_motivos->fetch_assoc()) {
        $motivos[] = $row['motivo'];
    }
}

// Obtener buses distintos para el selector de filtro
$sql_buses = "SELECT DISTINCT coche_id FROM historial_cubiertas WHERE coche_id IS NOT NULL
              UNION
              SELECT DISTINCT coche_id FROM historial_bajas WHERE coche_id IS NOT NULL
              ORDER BY coche_id";
$result_buses = $conn->query($sql_buses);
$buses = [];

if ($result_buses && $result_buses->num_rows > 0) {
    while ($row = $result_buses->fetch_assoc()) {
        $buses[] = $row['coche_id'];
    }
}

// 馃敡 MODIFICACI脫N: Calcular estad铆sticas para usar tanto en la p谩gina como en el PDF (incluyendo SILACOR)
$total_altas = 0;
$total_bajas = 0;
$total_silacor = 0; // 馃敡 NUEVO
$km_totales = 0;
$km_maximo = 0;
$cubierta_max_km = '';

foreach ($registros_filtrados as $registro) {
    if ($registro['tipo_operacion'] === 'alta') {
        $total_altas++;
    } elseif ($registro['tipo_operacion'] === 'baja') {
        $total_bajas++;
    } elseif ($registro['tipo_operacion'] === 'silacor') {
        $total_silacor++; // 馃敡 NUEVO
    }
    
    // Sumar kil贸metros totales
    if (isset($registro['km_recorridos']) && $registro['km_recorridos'] > 0) {
        $km_totales += $registro['km_recorridos'];
        
        // Encontrar la cubierta con m谩s kil贸metros
        if ($registro['km_recorridos'] > $km_maximo) {
            $km_maximo = $registro['km_recorridos'];
            $cubierta_max_km = $registro['cubierta_nombre'] . ' (ID: ' . $registro['cubierta_id'] . ')';
        }
    }
}

// Calcular promedio de kil贸metros por cubierta
$km_promedio = count($registros_filtrados) > 0 ? round($km_totales / count($registros_filtrados)) : 0;

// Preparar todos los datos para JavaScript (exportaci贸n PDF)
$estadisticas = [
    'total_altas' => $total_altas,
    'total_bajas' => $total_bajas,
    'total_silacor' => $total_silacor, // 馃敡 NUEVO
    'km_totales' => $km_totales,
    'km_promedio' => $km_promedio,
    'km_maximo' => $km_maximo,
    'cubierta_max_km' => $cubierta_max_km,
    'total_registros' => count($registros_filtrados)
];

// Guardar datos detallados de la tabla para posible uso en JS
$datos_tabla = [];
foreach ($registros_filtrados as $registro) {
    $datos_tabla[] = [
        'id' => $registro['cubierta_id'],
        'bus' => $registro['coche_id'] ?? 'N/A',
        'nombre' => $registro['cubierta_nombre'],
        'tipo_operacion' => $registro['tipo_operacion'],
        'motivo' => $registro['motivo'] ?? 'N/A',
        'fecha_colocacion' => $registro['fecha_colocacion'] ? date('d/m/Y', strtotime($registro['fecha_colocacion'])) : 'N/A',
        'fecha_retiro' => $registro['fecha_retiro'] ? date('d/m/Y', strtotime($registro['fecha_retiro'])) : 'N/A',
        'km_inicial' => $registro['kilometraje_colocacion'] ?? 'N/A',
        'km_final' => $registro['kilometraje_retiro'] ?? 'N/A',
        'km_recorridos' => isset($registro['km_recorridos']) && $registro['km_recorridos'] > 0 ? $registro['km_recorridos'] : 'N/A',
        'fecha_reconstruccion' => $registro['fecha_reconstruccion'] ? date('d/m/Y', strtotime($registro['fecha_reconstruccion'])) : 'N/A'
    ];
}

// Convertir a JSON para pasar a JavaScript
$estadisticas_json = json_encode($estadisticas);
$datos_tabla_json = json_encode($datos_tabla);

// Cerrar la conexi贸n
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Bajas y Altas</title>
    <!-- Enlace a Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Enlace a Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <!-- jsPDF para exportar a PDF -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <!-- Enlace a tu archivo CSS externo -->
    <link rel="stylesheet" href="styles-dark.css">
    <link rel="stylesheet" href="baja.css">
    <link rel="stylesheet" href="header-fix.css">
    <link rel="stylesheet" href="nuevo-header.css">
</head>
<body>

<style>
    footer {
        background-color: #2c3e50 !important;
        color: #b3b3b3 !important;
        text-align: center !important;
        padding: 20px !important;
        margin-top: auto !important;
        border-radius: 10 !important;
        width: 100% !important;
        box-sizing: border-box !important;
        font-size: 14px !important;
    }

    footer p {
        margin: 0 !important;
    }

    .main-container {
        display: flex !important;
        flex-direction: column !important;
        min-height: 100vh !important;
    }

    .content {
        flex: 1 !important;
    }

    /* 馃敡 NUEVOS ESTILOS PARA SILACOR */
    .fila-silacor {
        background-color: rgba(243, 156, 18, 0.1) !important;
        border-left: 3px solid #f39c12 !important;
    }

    .fila-silacor:hover {
        background-color: rgba(243, 156, 18, 0.2) !important;
    }

    .badge-warning {
        background-color: #f39c12;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Mantener estilos existentes */
    .fila-alta {
        background-color: rgba(46, 204, 113, 0.1) !important;
        border-left: 3px solid #2ecc71 !important;
    }

    .fila-baja {
        background-color: rgba(231, 76, 60, 0.1) !important;
        border-left: 3px solid #e74c3c !important;
    }

    .badge-success {
        background-color: #2ecc71;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-danger {
        background-color: #e74c3c;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }
</style>

    <div class="main-container">
        <!-- Header con logo -->
        <header>
            <div class="header-container">
                <div class="empty-space"></div>
                <h1 class="header-title fade-in delay-1">Control de Bajas y Altas</h1>
                <div class="logo-wrapper">
                    <img src="LOGO.PNG" alt="Logo de la empresa" class="header-logo fade-in">
                </div>
            </div>
        </header>

        <div class="content">
            <!-- Secci贸n de navegaci贸n -->
            <div class="nav-buttons">
                <button class="boton slide-in" onclick="window.location.href='index.php'">
                    <i class="fas fa-home"></i> VOLVER AL INICIO
                </button>
            </div>

            <!-- Filtros para historial combinado -->
            <div class="filtros-container fade-in delay-2">
                <h2><i class="fas fa-filter"></i> Filtros</h2>
                <form method="GET" class="filtros-form">
                    <div class="filtro-grupo">
                        <label for="tipo_filtro"><i class="fas fa-tags"></i> Tipo de Operaci贸n:</label>
                        <select name="tipo_filtro" id="tipo_filtro">
                            <option value="todos" <?php echo $tipo_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="alta" <?php echo $tipo_filtro === 'alta' ? 'selected' : ''; ?>>Solo Altas</option>
                            <option value="baja" <?php echo $tipo_filtro === 'baja' ? 'selected' : ''; ?>>Solo Bajas Definitivas</option>
                            <option value="silacor" <?php echo $tipo_filtro === 'silacor' ? 'selected' : ''; ?>>Solo Env铆os a SILACOR</option>
                        </select>
                    </div>
                    <div class="filtro-grupo">
                        <label for="motivo_filtro"><i class="fas fa-question-circle"></i> Motivo:</label>
                        <select name="motivo_filtro" id="motivo_filtro">
                            <option value="">Todos los motivos</option>
                            <?php foreach ($motivos as $motivo): ?>
                                <option value="<?php echo htmlspecialchars($motivo); ?>" <?php echo $motivo_filtro === $motivo ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($motivo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filtro-grupo">
                        <label for="bus_filtro"><i class="fas fa-bus"></i> ID Bus:</label>
                        <select name="bus_filtro" id="bus_filtro">
                            <option value="">Todos los buses</option>
                            <?php foreach ($buses as $bus): ?>
                                <option value="<?php echo htmlspecialchars($bus); ?>" <?php echo $bus_filtro == $bus ? 'selected' : ''; ?>>
                                    Bus <?php echo htmlspecialchars($bus); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filtro-grupo">
                        <label for="desde"><i class="fas fa-calendar-day"></i> Desde:</label>
                        <input type="date" name="desde" id="desde" value="<?php echo $desde; ?>">
                    </div>
                    <div class="filtro-grupo">
                        <label for="hasta"><i class="fas fa-calendar-day"></i> Hasta:</label>
                        <input type="date" name="hasta" id="hasta" value="<?php echo $hasta; ?>">
                    </div>
                    <div class="botones-exportar">
                        <button type="submit" class="boton aplicar-filtros">
                            <i class="fas fa-search"></i> APLICAR FILTROS
                        </button>
                        <button type="button" class="boton exportar-pdf">
                            <i class="fas fa-file-pdf"></i> EXPORTAR A PDF
                        </button>
                    </div>
                </form>
            </div>

            		
			
			<!-- Tabla combinada de historial con información de kilometraje -->
            <div class="historial-section fade-in delay-3">
                <h2 class="seccion-title"><i class="fas fa-history"></i> Historial Completo de Cubiertas</h2>
                <p>Esta sección muestra el historial integrado de cubiertas, incluyendo operaciones de alta, baja definitiva, envíos a SILACOR y los kilómetros recorridos por cada cubierta.</p>
                
                <table class="tabla-historial" id="tabla-historial-completo">
                    <thead>
                        <tr>
                            <th><span style="display:inline-block; white-space:nowrap; font-size: 0.70rem;"><i class="fas fa-hashtag"></i>ID</span></th>
                            <th><span style="display:inline-block; white-space:nowrap; font-size: 0.70rem;"><i class="fas fa-bus"></i>Bus</span></th>
                            <th><span style="display:inline-block; white-space:nowrap; font-size: 0.70rem;"><i class="fas fa-tag"></i>Cubierta</span></th>
                            <th><span style="display:inline-block; white-space:nowrap; font-size: 0.60rem;"><i class="fas fa-exchange-alt"></i>Operación</span></th>
                            <th><span style="display:inline-block; white-space:nowrap; font-size: 0.70rem;"><i class="fas fa-question-circle"></i>Motivo</span></th>
                            <th><span style="display:inline-block; white-space:nowrap; font-size: 0.70rem;"><i class="fas fa-calendar-plus"></i>Colocación</span></th>
                            <th><span style="display:inline-block; white-space:nowrap; font-size: 0.70rem;"><i class="fas fa-calendar-minus"></i>Retiro</span></th>
                            <th class="col-kilometraje"><span style="display:inline-block; white-space:nowrap; font-size: 0.75rem;"><i class="fas fa-tachometer-alt"></i>KM Inicial</span></th>
                            <th class="col-kilometraje"><span style="display:inline-block; white-space:nowrap; font-size: 0.75rem;"><i class="fas fa-tachometer-alt"></i>KM Final</span></th>
                            <th class="col-kilometraje"><span style="display:inline-block; white-space:nowrap; font-size: 0.75rem;"><i class="fas fa-road"></i>KM hechos</span></th>
                            <th><span style="display:inline-block; white-space:nowrap; font-size: 0.75rem;"><i class="fas fa-tools"></i>Silacor</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($registros_filtrados) > 0): ?>
                            <?php foreach ($registros_filtrados as $registro): ?>
                                <?php
                                    // 🔧 MODIFICACIÓN: Incluir caso para 'silacor'
                                    $clase_fila = '';
                                    $tipo_badge = '';
                                    $operacion_text = '';
                                    
                                    if ($registro['tipo_operacion'] === 'alta') {
                                        $clase_fila = 'fila-alta';
                                        $tipo_badge = 'badge-success';
                                        $operacion_text = 'ALTA';
                                    } elseif ($registro['tipo_operacion'] === 'baja') {
                                        $clase_fila = 'fila-baja';
                                        $tipo_badge = 'badge-danger';
                                        $operacion_text = 'BAJA';
                                    } elseif ($registro['tipo_operacion'] === 'silacor') {
                                        $clase_fila = 'fila-silacor';
                                        $tipo_badge = 'badge-warning';
                                        $operacion_text = 'SILACOR';
                                    }
                                    
                                    // Determinar clase para los KM recorridos
                                    $km_clase = 'km-destacado';
                                    if (isset($registro['km_recorridos']) && $registro['km_recorridos'] >= 50000) {
                                        $km_clase .= ' alto';
                                    } elseif (isset($registro['km_recorridos']) && $registro['km_recorridos'] >= 45000) {
                                        $km_clase .= ' medio';
                                    }
                                ?>
                                <tr class="<?php echo $clase_fila; ?>">
                                    <td><?php echo htmlspecialchars($registro['cubierta_id']); ?></td>
                                    <td><?php echo $registro['coche_id'] ?? 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($registro['cubierta_nombre']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $tipo_badge; ?>"><?php echo $operacion_text; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($registro['motivo'] ?? 'N/A'); ?></td>
                                    <td><?php echo $registro['fecha_colocacion'] ? date('d/m/Y', strtotime($registro['fecha_colocacion'])) : 'N/A'; ?></td>
                                    <td><?php echo $registro['fecha_retiro'] ? date('d/m/Y', strtotime($registro['fecha_retiro'])) : 'N/A'; ?></td>
                                    <td class="col-kilometraje"><?php echo $registro['kilometraje_colocacion'] ? number_format($registro['kilometraje_colocacion'], 0, ',', '.') . ' km' : 'N/A'; ?></td>
                                    <td class="col-kilometraje"><?php echo $registro['kilometraje_retiro'] ? number_format($registro['kilometraje_retiro'], 0, ',', '.') . ' km' : 'N/A'; ?></td>
                                    <td class="col-kilometraje">
                                        <?php if (isset($registro['km_recorridos']) && $registro['km_recorridos'] > 0): ?>
                                            <span class="<?php echo $km_clase; ?>"><?php echo number_format($registro['km_recorridos'], 0, ',', '.'); ?> km</span>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $registro['fecha_reconstruccion'] ? date('d/m/Y', strtotime($registro['fecha_reconstruccion'])) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="mensaje-no-datos">No hay registros que coincidan con los filtros seleccionados</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Estadísticas resumen con información de kilometraje -->
            <?php if (count($registros_filtrados) > 0): ?>
                <div class="resumen-estadisticas fade-in delay-4">
                    <h2><i class="fas fa-chart-pie"></i> Resumen de Registros</h2>
                    <div class="estadisticas-cards">
                        <div class="estadistica-card">
                            <div class="estadistica-icon"><i class="fas fa-plus-circle"></i></div>
                            <div class="estadistica-valor"><?php echo $total_altas; ?></div>
                            <div class="estadistica-label">Altas</div>
                        </div>
                        <div class="estadistica-card">
                            <div class="estadistica-icon"><i class="fas fa-minus-circle"></i></div>
                            <div class="estadistica-valor"><?php echo $total_bajas; ?></div>
                            <div class="estadistica-label">Bajas Definitivas</div>
                        </div>
                        <!-- 🔧 NUEVA TARJETA PARA SILACOR -->
                        <div class="estadistica-card">
                            <div class="estadistica-icon"><i class="fas fa-tools"></i></div>
                            <div class="estadistica-valor"><?php echo $total_silacor; ?></div>
                            <div class="estadistica-label">Envíos a SILACOR</div>
                        </div>
                        <div class="estadistica-card">
                            <div class="estadistica-icon"><i class="fas fa-road"></i></div>
                            <div class="estadistica-valor"><?php echo number_format($km_totales, 0, ',', '.'); ?></div>
                            <div class="estadistica-label">Total KM Recorridos</div>
                        </div>
                        <div class="estadistica-card">
                            <div class="estadistica-icon"><i class="fas fa-tachometer-alt"></i></div>
                            <div class="estadistica-valor"><?php echo number_format($km_promedio, 0, ',', '.'); ?></div>
                            <div class="estadistica-label">Promedio KM por Cubierta</div>
                        </div>
                        <div class="estadistica-card">
                            <div class="estadistica-icon"><i class="fas fa-trophy"></i></div>
                            <div class="estadistica-valor"><?php echo number_format($km_maximo, 0, ',', '.'); ?></div>
                            <div class="estadistica-label">Mayor KM: <?php echo $cubierta_max_km; ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <br>
            <footer class="fade-in delay-5">
                <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión de Cubiertas | Empresa Casanova S.A todos los derechos reservados</p>
            </footer>
        </div>
    </div> <!-- Cierre del div.content -->

</div> <!-- Cierre del div.main-container -->

    <!-- Pasamos datos precalculados a JavaScript usando JSON -->
    <script>
        // Datos precalculados desde PHP
        const estadisticas = <?php echo $estadisticas_json; ?>;
        const datosTabla = <?php echo $datos_tabla_json; ?>;
        
        // Añadir clase de animación a elementos
        document.addEventListener('DOMContentLoaded', function() {
            // Animaciones de elementos
            document.querySelectorAll('.fade-in').forEach((el, i) => {
                setTimeout(() => { el.style.opacity = '1'; }, 100 * i);
            });
            
            document.querySelectorAll('.slide-in').forEach((el, i) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateX(0)';
                }, 100 * i);
            });
            
            // Conectar exportación PDF y aplicar estilos a filas
            document.querySelector('.exportar-pdf')?.addEventListener('click', exportarPDF);
            
            // Estilos para filas de tabla
            document.querySelectorAll('#tabla-historial-completo tbody tr').forEach((fila, i) => {
                // Alternar colores de fondo en filas
                if (!fila.className && i % 2 === 0) {
                    fila.style.backgroundColor = 'rgba(52, 73, 94, 0.2)';
                }
                
                // Efecto hover en filas
                fila.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
                    this.style.transition = 'all 0.3s ease';
                });
                
                fila.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });
        });
        
        // Función para exportar a PDF usando datos precalculados
        function exportarPDF() {
            try {
                if (typeof window.jspdf === 'undefined') {
                    throw new Error('La biblioteca jsPDF no está disponible');
                }
                
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                // Título y metadata
                doc.setFontSize(18);
                doc.text("Historial de Cubiertas", 14, 20);
                
                const fechaActual = new Date().toLocaleDateString();
                doc.setFontSize(10);
                doc.text(`Informe generado el ${fechaActual}`, 14, 30);
                
                // Filtros aplicados
                doc.setFontSize(12);
                doc.text("Filtros aplicados:", 14, 40);
				
				
                
                // Obtener valores de filtros de forma segura
                const getSelectText = (id) => {
                    const el = document.getElementById(id);
                    return (el && el.selectedIndex >= 0) ? el.options[el.selectedIndex].text : '';
                };
                
                const desdeValue = document.getElementById('desde')?.value || '';
                const hastaValue = document.getElementById('hasta')?.value || '';
                const tipoFiltroText = getSelectText('tipo_filtro');
                const motivoFiltroText = getSelectText('motivo_filtro');
                const busFiltroText = getSelectText('bus_filtro');
                
                doc.text(`Período: ${desdeValue} a ${hastaValue}`, 14, 48);
                doc.text(`Tipo: ${tipoFiltroText}`, 14, 56);
                doc.text(`Motivo: ${motivoFiltroText}`, 14, 64);
                doc.text(`Bus: ${busFiltroText}`, 14, 72);
                
                // Generar tabla desde HTML
doc.autoTable({
    html: '#tabla-historial-completo',
    startY: 80,
    styles: { fontSize: 8 },
    headStyles: { fillColor: [52, 152, 219] },
    alternateRowStyles: { fillColor: [240, 240, 240] },
    margin: { top: 80 }
    // Comentar temporalmente la personalización de colores
    /*
    didParseCell: function(data) {
        // ... código comentado
    }
    */
});
                
                // Resumen estadístico usando datos precalculados
                if (doc.lastAutoTable?.finalY) {
                    const finalY = doc.lastAutoTable.finalY + 10;
                    
                    doc.setFontSize(14);
                    doc.text("Resumen de Registros", 14, finalY);
                    
                    doc.setFontSize(10);
                    doc.text(`Altas: ${estadisticas.total_altas}`, 20, finalY + 10);
                    doc.text(`Bajas Definitivas: ${estadisticas.total_bajas}`, 60, finalY + 10);
                    doc.text(`Envíos a SILACOR: ${estadisticas.total_silacor}`, 120, finalY + 10);
                    doc.text(`Total Registros: ${estadisticas.total_registros}`, 20, finalY + 20);
                    doc.text(`Total KM Recorridos: ${parseInt(estadisticas.km_totales).toLocaleString()}`, 80, finalY + 20);
                    doc.text(`Promedio KM: ${parseInt(estadisticas.km_promedio).toLocaleString()}`, 140, finalY + 20);
                    
                    if (estadisticas.cubierta_max_km) {
                        doc.text(`Mayor KM: ${parseInt(estadisticas.km_maximo).toLocaleString()} (${estadisticas.cubierta_max_km})`, 20, finalY + 30);
                    }
                }
                
                // Pie de página con fecha de generación
                const totalPages = doc.internal.getNumberOfPages();
                for (let i = 1; i <= totalPages; i++) {
                    doc.setPage(i);
                    doc.setFontSize(8);
                    const pageWidth = doc.internal.pageSize.width;
                    doc.text(`Informe generado el ${fechaActual} - Página ${i} de ${totalPages}`, pageWidth / 2, 287, {
                        align: 'center'
                    });
                }
                
                // Guardar el PDF
                doc.save(`Historial_Cubiertas_${fechaActual.replace(/\//g, '-')}.pdf`);
                alert('PDF exportado con éxito');
                
            } catch(error) {
                console.error('Error al exportar a PDF:', error);
                alert('Error al exportar a PDF: ' + error.message);
            }
        }
    </script>
</body>
</html>