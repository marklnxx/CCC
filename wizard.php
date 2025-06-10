<?php
header('Content-Type: text/html; charset=UTF-8');
// wizard.php - Sistema de configuracion inicial para migración
session_start();

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// CREAR TABLA WIZARD_CONFIG AL INICIO (antes de cualquier otra operación)
$sql_wizard_config = "CREATE TABLE IF NOT EXISTS wizard_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE,
    valor TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql_wizard_config);

// Verificar si falta la columna posicion en cubiertas
$sql_check_posicion = "SHOW COLUMNS FROM cubiertas LIKE 'posicion'";
$result_posicion = $conn->query($sql_check_posicion);
if ($result_posicion->num_rows == 0) {
    $sql_add_posicion = "ALTER TABLE cubiertas ADD COLUMN posicion VARCHAR(50) NULL";
    $conn->query($sql_add_posicion);
}

// Determinar el paso actual
$paso_actual = isset($_GET['paso']) ? (int)$_GET['paso'] : 1;
$max_pasos = 5; // Reducimos a 5 pasos para sistema nuevo

// Procesar datos según el paso
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paso_actual = procesarPaso($_POST, $conn);
}

function procesarPaso($data, $conn) {
    $paso = (int)$data['paso_actual'];
    
    switch($paso) {
        case 1: // Configuración inicial
            return configurarSistemaInicial($conn);
        case 2: // Agregar buses
            return procesarBuses($data, $conn);
        case 3: // Agregar cubiertas
            return procesarCubiertas($data, $conn);
        case 4: // Asignaciones iniciales (opcional)
            return procesarAsignacionesIniciales($data, $conn);
        case 5: // Finalización
            return finalizarConfiguracion($data, $conn);
        default:
            return 1;
    }
}

function configurarSistemaInicial($conn) {
    // Verificar que las tablas principales estén vacías/listas
    $sql = "SELECT 
                (SELECT COUNT(*) FROM coches) as total_buses,
                (SELECT COUNT(*) FROM cubiertas) as total_cubiertas";
    $result = $conn->query($sql);
    $totales = $result->fetch_assoc();
    
    // Marcar configuración inicial como completada
    $sql = "INSERT INTO wizard_config (clave, valor) VALUES ('config_inicial_ok', 'si') 
           ON DUPLICATE KEY UPDATE valor = 'si'";
    $conn->query($sql);
    
    return 2;
}

function procesarBuses($data, $conn) {
    if (isset($data['metodo_buses'])) {
        if ($data['metodo_buses'] === 'rango' && isset($data['bus_inicio']) && isset($data['bus_fin'])) {
            // Crear buses en rango
            $inicio = (int)$data['bus_inicio'];
            $fin = (int)$data['bus_fin'];
            
            $conn->begin_transaction();
            try {
                for ($i = $inicio; $i <= $fin; $i++) {
                    $sql = "INSERT IGNORE INTO coches (id) VALUES (?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $i);
                    $stmt->execute();
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                return 2; // Volver al paso anterior
            }
            
        } elseif ($data['metodo_buses'] === 'lista' && isset($data['lista_buses'])) {
            // Crear buses desde lista
            $buses = array_filter(array_map('trim', explode("\n", $data['lista_buses'])));
            
            $conn->begin_transaction();
            try {
                foreach ($buses as $bus_id) {
                    if (is_numeric($bus_id)) {
                        $sql = "INSERT IGNORE INTO coches (id) VALUES (?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $bus_id);
                        $stmt->execute();
                    }
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                return 2;
            }
        }
    }
    return 3;
}

function procesarCubiertas($data, $conn) {
    if (isset($data['metodo_cubiertas'])) {
        $conn->begin_transaction();
        try {
            if ($data['metodo_cubiertas'] === 'manual' && isset($data['lista_cubiertas'])) {
                $cubiertas = array_filter(array_map('trim', explode("\n", $data['lista_cubiertas'])));
                
                foreach ($cubiertas as $nombre) {
                    if (!empty($nombre)) {
                        $sql = "INSERT INTO cubiertas (nombre, estado) VALUES (?, 'casanova')";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $nombre);
                        $stmt->execute();
                    }
                }
            } elseif ($data['metodo_cubiertas'] === 'patron' && isset($data['patron_prefix']) && isset($data['patron_inicio']) && isset($data['patron_fin'])) {
                // Generar cubiertas con patrón (ej: CUB001, CUB002, etc.)
                $prefix = $data['patron_prefix'];
                $inicio = (int)$data['patron_inicio'];
                $fin = (int)$data['patron_fin'];
                $digitos = isset($data['patron_digitos']) ? (int)$data['patron_digitos'] : 3;
                
                for ($i = $inicio; $i <= $fin; $i++) {
                    $numero = str_pad($i, $digitos, '0', STR_PAD_LEFT);
                    $nombre = $prefix . $numero;
                    
                    $sql = "INSERT INTO cubiertas (nombre, estado) VALUES (?, 'casanova')";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $nombre);
                    $stmt->execute();
                }
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            return 3;
        }
    }
    return 4;
}

function procesarAsignacionesIniciales($data, $conn) {
    // Este paso es opcional - permite asignar algunas cubiertas iniciales
    if (isset($data['hacer_asignaciones']) && $data['hacer_asignaciones'] === 'si') {
        // Procesar asignaciones si las hay
        if (isset($data['asignaciones'])) {
            $conn->begin_transaction();
            try {
                foreach ($data['asignaciones'] as $bus_id => $posiciones) {
                    $km_inicial = isset($data['km_inicial'][$bus_id]) ? (int)$data['km_inicial'][$bus_id] : 0;
                    
                    foreach ($posiciones as $posicion => $cubierta_id) {
                        if (!empty($cubierta_id)) {
                            // Asignar cubierta
                            $sql = "UPDATE cubiertas SET coche_id = ?, posicion = ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("isi", $bus_id, $posicion, $cubierta_id);
                            $stmt->execute();
                            
                            // Crear historial
                            $sql_hist = "INSERT INTO historial_cubiertas 
                                        (cubierta_id, coche_id, fecha_colocacion, kilometraje_colocacion) 
                                        VALUES (?, ?, NOW(), ?)";
                            $stmt_hist = $conn->prepare($sql_hist);
                            $stmt_hist->bind_param("iii", $cubierta_id, $bus_id, $km_inicial);
                            $stmt_hist->execute();
                        }
                    }
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                return 4;
            }
        }
    }
    return 5;
}

function finalizarConfiguracion($data, $conn) {
    // Marcar el wizard como completado
    $sql = "INSERT INTO wizard_config (clave, valor) VALUES ('wizard_completado', 'si') 
           ON DUPLICATE KEY UPDATE valor = 'si'";
    $conn->query($sql);
    
    // Limpiar datos temporales del wizard
    $sql_clean = "DELETE FROM wizard_config WHERE clave LIKE 'km_actual_bus_%'";
    $conn->query($sql_clean);
    
    return 7; // Paso final - redirección
}

// Funciones para obtener datos
function obtenerBuses($conn) {
    $sql = "SELECT id FROM coches ORDER BY id";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function obtenerCubiertasDisponibles($conn) {
    $sql = "SELECT id, nombre FROM cubiertas WHERE coche_id IS NULL ORDER BY nombre";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function obtenerCubiertasAsignadas($conn) {
    $sql = "SELECT c.id, c.nombre, c.coche_id, c.posicion 
           FROM cubiertas c 
           WHERE c.coche_id IS NOT NULL 
           ORDER BY c.coche_id, c.posicion";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function obtenerHistorialPendiente($conn) {
    $sql = "SELECT h.id, h.cubierta_id, h.coche_id, h.fecha_colocacion, c.nombre 
           FROM historial_cubiertas h 
           JOIN cubiertas c ON h.cubierta_id = c.id 
           WHERE h.fecha_retiro IS NULL 
           ORDER BY h.fecha_colocacion DESC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Verificar si el wizard ya fue completado
$sql_check_wizard = "SELECT valor FROM wizard_config WHERE clave = 'wizard_completado'";
$result_wizard = $conn->query($sql_check_wizard);
$wizard_completado = $result_wizard && $result_wizard->num_rows > 0;

if ($wizard_completado && !isset($_GET['forzar'])) {
    header("Location: index.php?mensaje=sistema_ya_configurado");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Inicial del Sistema</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles-dark.css">
    <link rel="stylesheet" href="wizard.css">
</head>
<body>
    <div class="wizard-container">
        <header class="wizard-header">
            <h1><i class="fas fa-magic"></i> Configuración Inicial del Sistema</h1>
            <div class="progress-bar">
                <div class="progress" style="width: <?php echo ($paso_actual / $max_pasos) * 100; ?>%"></div>
            </div>
            <p>Paso <?php echo $paso_actual; ?> de <?php echo $max_pasos; ?></p>
        </header>

        <div class="wizard-content">
            <?php
            switch($paso_actual) {
                case 1:
                    include 'wizard_steps/paso1_inicio.php';
                    break;
                case 2:
                    include 'wizard_steps/paso2_buses.php';
                    break;
                case 3:
                    include 'wizard_steps/paso3_cubiertas.php';
                    break;
                case 4:
                    include 'wizard_steps/paso4_asignaciones.php';
                    break;
                case 5:
                    include 'wizard_steps/paso5_finalizacion.php';
                    break;
                case 6:
                    echo "<div class='success-message'>";
                    echo "<i class='fas fa-check-circle'></i>";
                    echo "<h2>¡Sistema Configurado!</h2>";
                    echo "<p>El sistema ha sido configurado exitosamente y esta listo para usar.</p>";
                    echo "<a href='index.php' class='btn btn-primary'>Comenzar a Usar el Sistema</a>";
                    echo "</div>";
                    break;
            }
            ?>
        </div>
    </div>

    <script src="wizard.js"></script>
</body>
</html>

<?php $conn->close(); ?>