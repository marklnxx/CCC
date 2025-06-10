<?php
/**
 * diagnostico_recuperacion.php
 * Herramienta completa para diagnosticar y recuperar cubiertas perdidas
 */

session_start();

// Configuración de base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Función principal de diagnóstico
function diagnosticar_sistema_completo($conn) {
    $diagnostico = [
        'resumen' => [
            'total_cubiertas' => 0,
            'cubiertas_en_uso' => 0,
            'cubiertas_disponibles' => 0,
            'cubiertas_problematicas' => 0
        ],
        'problemas' => [
            'criticos' => [],
            'advertencias' => [],
            'informativos' => []
        ],
        'detalles' => []
    ];
    
    // 1. Conteo general
    $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN coche_id IS NOT NULL THEN 1 ELSE 0 END) as en_uso,
            SUM(CASE WHEN coche_id IS NULL AND estado = 'casanova' THEN 1 ELSE 0 END) as disponibles
            FROM cubiertas 
            WHERE estado != 'baja'";
    
    $result = $conn->query($sql);
    $conteo = $result->fetch_assoc();
    $diagnostico['resumen']['total_cubiertas'] = $conteo['total'];
    $diagnostico['resumen']['cubiertas_en_uso'] = $conteo['en_uso'];
    $diagnostico['resumen']['cubiertas_disponibles'] = $conteo['disponibles'];
    
    // 2. PROBLEMA CRÍTICO: Cubiertas duplicadas en el mismo coche/posición
    $sql = "SELECT coche_id, posicion, COUNT(*) as cantidad,
            GROUP_CONCAT(CONCAT(id, ':', nombre) SEPARATOR ' | ') as cubiertas
            FROM cubiertas
            WHERE coche_id IS NOT NULL AND posicion IS NOT NULL
            GROUP BY coche_id, posicion
            HAVING COUNT(*) > 1";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $diagnostico['problemas']['criticos'][] = [
            'tipo' => 'posicion_duplicada',
            'descripcion' => "Coche {$row['coche_id']}: Múltiples cubiertas en posición {$row['posicion']}",
            'datos' => $row,
            'accion_requerida' => 'Mantener solo una cubierta en esta posición'
        ];
        $diagnostico['resumen']['cubiertas_problematicas'] += $row['cantidad'] - 1;
    }
    
    // 3. PROBLEMA CRÍTICO: Cubiertas "fantasma" (con coche_id pero sin historial activo)
    $sql = "SELECT c.id, c.nombre, c.coche_id, c.posicion, c.estado
            FROM cubiertas c
            WHERE c.coche_id IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM historial_cubiertas h 
                WHERE h.cubierta_id = c.id 
                AND h.coche_id = c.coche_id 
                AND h.fecha_retiro IS NULL
            )";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $diagnostico['problemas']['criticos'][] = [
            'tipo' => 'cubierta_fantasma',
            'descripcion' => "Cubierta '{$row['nombre']}' (ID: {$row['id']}) asignada a coche {$row['coche_id']} sin historial activo",
            'datos' => $row,
            'accion_requerida' => 'Crear historial o desasignar cubierta'
        ];
        $diagnostico['resumen']['cubiertas_problematicas']++;
    }
    
    // 4. PROBLEMA CRÍTICO: Historiales activos sin cubierta asignada
    $sql = "SELECT h.id as historial_id, h.cubierta_id, h.coche_id, h.fecha_colocacion,
            c.nombre, c.coche_id as coche_actual, c.estado
            FROM historial_cubiertas h
            JOIN cubiertas c ON h.cubierta_id = c.id
            WHERE h.fecha_retiro IS NULL
            AND (c.coche_id IS NULL OR c.coche_id != h.coche_id)";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $diagnostico['problemas']['criticos'][] = [
            'tipo' => 'historial_inconsistente',
            'descripcion' => "Historial activo de '{$row['nombre']}' en coche {$row['coche_id']}, pero cubierta " . 
                           ($row['coche_actual'] ? "asignada a coche {$row['coche_actual']}" : "no asignada"),
            'datos' => $row,
            'accion_requerida' => 'Cerrar historial o reasignar cubierta'
        ];
    }
    
    // 5. ADVERTENCIA: Estados inconsistentes
    $sql = "SELECT id, nombre, coche_id, estado, posicion
            FROM cubiertas
            WHERE (coche_id IS NOT NULL AND estado != 'en_uso')
               OR (coche_id IS NULL AND estado = 'en_uso')";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $diagnostico['problemas']['advertencias'][] = [
            'tipo' => 'estado_inconsistente',
            'descripcion' => "Cubierta '{$row['nombre']}' con estado '{$row['estado']}' " . 
                           ($row['coche_id'] ? "pero asignada a coche {$row['coche_id']}" : "pero sin asignar"),
            'datos' => $row,
            'accion_requerida' => 'Actualizar estado'
        ];
    }
    
    // 6. Verificar coches con cubiertas faltantes
    $sql = "SELECT c.id as coche_id, 
            COUNT(DISTINCT cu.posicion) as posiciones_ocupadas,
            GROUP_CONCAT(DISTINCT cu.posicion ORDER BY cu.posicion) as posiciones
            FROM coches c
            LEFT JOIN cubiertas cu ON c.id = cu.coche_id
            GROUP BY c.id
            HAVING posiciones_ocupadas < 6";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $todas_posiciones = [
            "DELANTERA CHOFER", "DELANTERA PUERTA",
            "TRASERA CHOFER AFUERA", "TRASERA PUERTA AFUERA",
            "TRASERA CHOFER ADENTRO", "TRASERA PUERTA ADENTRO"
        ];
        $posiciones_actuales = $row['posiciones'] ? explode(',', $row['posiciones']) : [];
        $faltantes = array_diff($todas_posiciones, $posiciones_actuales);
        
        $diagnostico['problemas']['informativos'][] = [
            'tipo' => 'coche_incompleto',
            'descripcion' => "Coche {$row['coche_id']} tiene solo {$row['posiciones_ocupadas']} cubiertas de 6",
            'datos' => [
                'coche_id' => $row['coche_id'],
                'posiciones_faltantes' => $faltantes
            ],
            'accion_requerida' => 'Asignar cubiertas faltantes'
        ];
    }
    
    return $diagnostico;
}

// Función para recuperar cubiertas perdidas
function recuperar_cubiertas_perdidas($conn) {
    $recuperadas = [];
    $errores = [];
    
    $conn->begin_transaction();
    
    try {
        // 1. Recuperar cubiertas fantasma
        $sql = "SELECT c.id, c.nombre, c.coche_id 
                FROM cubiertas c
                WHERE c.coche_id IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM historial_cubiertas h 
                    WHERE h.cubierta_id = c.id 
                    AND h.coche_id = c.coche_id 
                    AND h.fecha_retiro IS NULL
                )";
        
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            // Obtener último kilometraje del coche
            $sql_km = "SELECT COALESCE(MAX(kilometraje_retiro), 0) as km 
                       FROM historial_cubiertas 
                       WHERE coche_id = ?";
            $stmt_km = $conn->prepare($sql_km);
            $stmt_km->bind_param("i", $row['coche_id']);
            $stmt_km->execute();
            $km_result = $stmt_km->get_result()->fetch_assoc();
            $kilometraje = $km_result['km'];
            
            // Crear historial faltante
            $sql_insert = "INSERT INTO historial_cubiertas 
                          (cubierta_id, coche_id, fecha_colocacion, kilometraje_colocacion) 
                          VALUES (?, ?, NOW(), ?)";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("iii", $row['id'], $row['coche_id'], $kilometraje);
            $stmt->execute();
            
            // Actualizar estado
            $sql_update = "UPDATE cubiertas SET estado = 'en_uso' WHERE id = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("i", $row['id']);
            $stmt->execute();
            
            $recuperadas[] = "Cubierta '{$row['nombre']}' (ID: {$row['id']}) - Historial creado, asignada a coche {$row['coche_id']}";
        }
        
        // 2. Cerrar historiales huérfanos
        $sql = "SELECT h.id, h.cubierta_id, h.coche_id, c.nombre, c.coche_id as coche_actual
                FROM historial_cubiertas h
                JOIN cubiertas c ON h.cubierta_id = c.id
                WHERE h.fecha_retiro IS NULL
                AND c.coche_id != h.coche_id";
        
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            // Cerrar historial antiguo
            $sql_close = "UPDATE historial_cubiertas 
                         SET fecha_retiro = NOW() 
                         WHERE id = ?";
            $stmt = $conn->prepare($sql_close);
            $stmt->bind_param("i", $row['id']);
            $stmt->execute();
            
            $recuperadas[] = "Historial cerrado para '{$row['nombre']}' en coche {$row['coche_id']}";
        }
        
        // 3. Corregir estados
        $sql_fix_estados = "UPDATE cubiertas 
                           SET estado = CASE 
                               WHEN coche_id IS NOT NULL THEN 'en_uso'
                               WHEN estado = 'en_uso' THEN 'casanova'
                               ELSE estado
                           END
                           WHERE (coche_id IS NOT NULL AND estado != 'en_uso')
                              OR (coche_id IS NULL AND estado = 'en_uso')";
        $conn->query($sql_fix_estados);
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $errores[] = "Error en recuperación: " . $e->getMessage();
    }
    
    return ['recuperadas' => $recuperadas, 'errores' => $errores];
}

// Procesar acciones
$accion = $_POST['accion'] ?? '';
$mensaje = '';
$tipo_mensaje = '';

if ($accion === 'recuperar_todo') {
    $resultado = recuperar_cubiertas_perdidas($conn);
    if (!empty($resultado['recuperadas'])) {
        $mensaje = "Recuperación completada:<br>" . implode("<br>", $resultado['recuperadas']);
        $tipo_mensaje = 'exito';
    }
    if (!empty($resultado['errores'])) {
        $mensaje .= "<br>Errores:<br>" . implode("<br>", $resultado['errores']);
        $tipo_mensaje = 'error';
    }
}

// Ejecutar diagnóstico
$diagnostico = diagnosticar_sistema_completo($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico y Recuperación de Cubiertas</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles-dark.css">
    <style>
        .diagnostico-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            padding: 20px;
            color: white;
            text-align: center;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, #FC466B 0%, #3F5EFB 100%);
        }
        
        .stat-card.danger {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
        }
        
        .stat-number {
            font-size: 48px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .problema-card {
            background-color: #2c3e50;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid #e74c3c;
            transition: all 0.3s;
        }
        
        .problema-card.warning {
            border-left-color: #f39c12;
        }
        
        .problema-card.info {
            border-left-color: #3498db;
        }
        
        .problema-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .problema-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .problema-tipo {
            background-color: #e74c3c;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .problema-tipo.warning {
            background-color: #f39c12;
        }
        
        .problema-tipo.info {
            background-color: #3498db;
        }
        
        .accion-requerida {
            background-color: rgba(52, 73, 94, 0.5);
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .boton-recuperar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 30px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
            margin: 30px auto;
        }
        
        .boton-recuperar:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        
        .boton-recuperar:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }
        
        .sin-problemas {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            font-size: 18px;
            margin: 20px 0;
        }
        
        .seccion-problemas {
            margin-bottom: 40px;
        }
        
        .seccion-titulo {
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .contador-problemas {
            background-color: #e74c3c;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <header>
            <div class="logo-container">
                <img src="LOGO.PNG" alt="Logo de la empresa" class="fade-in">
                <h1 class="fade-in delay-1">DIAGNÓSTICO Y RECUPERACIÓN</h1>
            </div>
        </header>

        <div class="content">
            <div class="nav-buttons">
                <button class="boton slide-in" onclick="window.location.href='index.php'">
                    <i class="fas fa-home"></i> VOLVER AL INICIO
                </button>
                <button class="boton slide-in" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt"></i> ACTUALIZAR DIAGNÓSTICO
                </button>
            </div>

            <?php if ($mensaje): ?>
                <div class="mensaje-<?php echo $tipo_mensaje; ?> fade-in">
                    <i class="fas fa-<?php echo $tipo_mensaje === 'exito' ? 'check' : 'exclamation'; ?>-circle"></i>
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard de estadísticas -->
            <div class="diagnostico-dashboard fade-in">
                <div class="stat-card">
                    <i class="fas fa-database" style="font-size: 40px;"></i>
                    <div class="stat-number"><?php echo $diagnostico['resumen']['total_cubiertas']; ?></div>
                    <div class="stat-label">Total Cubiertas</div>
                </div>
                
                <div class="stat-card success">
                    <i class="fas fa-check-circle" style="font-size: 40px;"></i>
                    <div class="stat-number"><?php echo $diagnostico['resumen']['cubiertas_en_uso']; ?></div>
                    <div class="stat-label">En Uso</div>
                </div>
                
                <div class="stat-card warning">
                    <i class="fas fa-warehouse" style="font-size: 40px;"></i>
                    <div class="stat-number"><?php echo $diagnostico['resumen']['cubiertas_disponibles']; ?></div>
                    <div class="stat-label">Disponibles</div>
                </div>
                
                <div class="stat-card danger">
                    <i class="fas fa-exclamation-triangle" style="font-size: 40px;"></i>
                    <div class="stat-number"><?php echo $diagnostico['resumen']['cubiertas_problematicas']; ?></div>
                    <div class="stat-label">Con Problemas</div>
                </div>
            </div>

            <?php 
            $total_problemas = count($diagnostico['problemas']['criticos']) + 
                              count($diagnostico['problemas']['advertencias']) + 
                              count($diagnostico['problemas']['informativos']);
            
            if ($total_problemas === 0): 
            ?>
                <div class="sin-problemas fade-in">
                    <i class="fas fa-check-circle" style="font-size: 60px; margin-bottom: 20px;"></i>
                    <h2>¡Sistema en perfecto estado!</h2>
                    <p>No se detectaron problemas de integridad en las cubiertas.</p>
                </div>
            <?php else: ?>
                
                <!-- Problemas Críticos -->
                <?php if (!empty($diagnostico['problemas']['criticos'])): ?>
                <div class="seccion-problemas fade-in">
                    <h2 class="seccion-titulo">
                        <i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i>
                        Problemas Críticos
                        <span class="contador-problemas"><?php echo count($diagnostico['problemas']['criticos']); ?></span>
                    </h2>
                    
                    <?php foreach ($diagnostico['problemas']['criticos'] as $problema): ?>
                    <div class="problema-card">
                        <div class="problema-header">
                            <div>
                                <strong><?php echo $problema['descripcion']; ?></strong>
                            </div>
                            <span class="problema-tipo"><?php echo strtoupper(str_replace('_', ' ', $problema['tipo'])); ?></span>
                        </div>
                        
                        <?php if (isset($problema['datos'])): ?>
                        <div style="font-size: 14px; color: #bdc3c7; margin: 10px 0;">
                            <?php 
                            foreach ($problema['datos'] as $key => $value) {
                                if (!in_array($key, ['tipo'])) {
                                    echo "<div><strong>" . ucfirst(str_replace('_', ' ', $key)) . ":</strong> $value</div>";
                                }
                            }
                            ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="accion-requerida">
                            <i class="fas fa-wrench"></i> <strong>Acción requerida:</strong> <?php echo $problema['accion_requerida']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Advertencias -->
                <?php if (!empty($diagnostico['problemas']['advertencias'])): ?>
                <div class="seccion-problemas fade-in">
                    <h2 class="seccion-titulo">
                        <i class="fas fa-exclamation-triangle" style="color: #f39c12;"></i>
                        Advertencias
                        <span class="contador-problemas" style="background-color: #f39c12;"><?php echo count($diagnostico['problemas']['advertencias']); ?></span>
                    </h2>
                    
                    <?php foreach ($diagnostico['problemas']['advertencias'] as $problema): ?>
                    <div class="problema-card warning">
                        <div class="problema-header">
                            <div>
                                <strong><?php echo $problema['descripcion']; ?></strong>
                            </div>
                            <span class="problema-tipo warning"><?php echo strtoupper(str_replace('_', ' ', $problema['tipo'])); ?></span>
                        </div>
                        
                        <div class="accion-requerida">
                            <i class="fas fa-info-circle"></i> <strong>Acción sugerida:</strong> <?php echo $problema['accion_requerida']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Información -->
                <?php if (!empty($diagnostico['problemas']['informativos'])): ?>
                <div class="seccion-problemas fade-in">
                    <h2 class="seccion-titulo">
                        <i class="fas fa-info-circle" style="color: #3498db;"></i>
                        Información
                        <span class="contador-problemas" style="background-color: #3498db;"><?php echo count($diagnostico['problemas']['informativos']); ?></span>
                    </h2>
                    
                    <?php foreach ($diagnostico['problemas']['informativos'] as $problema): ?>
                    <div class="problema-card info">
                        <div class="problema-header">
                            <div>
                                <strong><?php echo $problema['descripcion']; ?></strong>
                            </div>
                            <span class="problema-tipo info"><?php echo strtoupper(str_replace('_', ' ', $problema['tipo'])); ?></span>
                        </div>
                        
                        <?php if ($problema['tipo'] === 'coche_incompleto' && isset($problema['datos']['posiciones_faltantes'])): ?>
                        <div style="margin: 10px 0;">
                            <strong>Posiciones faltantes:</strong>
                            <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px;">
                                <?php foreach ($problema['datos']['posiciones_faltantes'] as $pos): ?>
                                <span style="background-color: #34495e; padding: 5px 10px; border-radius: 15px; font-size: 12px;">
                                    <?php echo $pos; ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Botón de recuperación automática -->
                <?php if (count($diagnostico['problemas']['criticos']) > 0): ?>
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="recuperar_todo">
                    <button type="submit" class="boton-recuperar" 
                            onclick="return confirm('¿Está seguro de ejecutar la recuperación automática? Se corregirán todos los problemas detectados.')">
                        <i class="fas fa-magic"></i> EJECUTAR RECUPERACIÓN AUTOMÁTICA
                    </button>
                </form>
                <?php endif; ?>
                
            <?php endif; ?>

        </div>

        <footer class="fade-in delay-5">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión de Cubiertas | Herramienta de Diagnóstico</p>
        </footer>
    </div>

    <script>
        // Animaciones
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                }, 100 * index);
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>