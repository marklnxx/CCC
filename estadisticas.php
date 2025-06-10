<?php
// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

// Conexión a la base de datos
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Configuración de filtros predeterminados
$desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-d', strtotime('-1 year'));
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d');
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'todos';

// Ajustar fechas según el periodo seleccionado
if ($periodo === 'ultimo_mes') {
    $desde = date('Y-m-d', strtotime('-1 month'));
} elseif ($periodo === 'ultimos_3_meses') {
    $desde = date('Y-m-d', strtotime('-3 months'));
} elseif ($periodo === 'ultimo_anio') {
    $desde = date('Y-m-d', strtotime('-1 year'));
}

// Consulta para obtener los coches con más cambios de cubiertas
$sql_cambios = "
    SELECT 
        hc.coche_id,
        COUNT(hc.id) AS cambios_totales,
        COUNT(DISTINCT hc.cubierta_id) AS cubiertas_distintas,
        MIN(hc.fecha_colocacion) AS primera_colocacion,
        MAX(hc.fecha_colocacion) AS ultima_colocacion
    FROM 
        historial_cubiertas hc
    WHERE 
        hc.fecha_colocacion BETWEEN '$desde 00:00:00' AND '$hasta 23:59:59'
    GROUP BY 
        hc.coche_id
    ORDER BY 
        cambios_totales DESC
";
$result_cambios = $conn->query($sql_cambios);
$datos_cambios = [];
if ($result_cambios->num_rows > 0) {
    while ($row = $result_cambios->fetch_assoc()) {
        $datos_cambios[] = $row;
    }
}

// Consulta para obtener los kilómetros recorridos por cubierta y por coche
$sql_kilometros = "
    SELECT 
        hc.coche_id,
        hc.cubierta_id,
        c.nombre AS nombre_cubierta,
        MIN(hc.fecha_colocacion) AS fecha_inicio,
        MAX(COALESCE(hc.fecha_retiro, CURRENT_TIMESTAMP)) AS fecha_fin,
        MIN(hc.kilometraje_colocacion) AS km_inicial,
        MAX(COALESCE(hc.kilometraje_retiro, 0)) AS km_final,
        (MAX(COALESCE(hc.kilometraje_retiro, 0)) - MIN(hc.kilometraje_colocacion)) AS km_recorridos,
        (SELECT COUNT(*) FROM reconstrucciones r WHERE r.cubierta_id = hc.cubierta_id) AS reconstrucciones
    FROM 
        historial_cubiertas hc
    JOIN 
        cubiertas c ON hc.cubierta_id = c.id
    WHERE 
        hc.fecha_colocacion BETWEEN '$desde 00:00:00' AND '$hasta 23:59:59'
        AND hc.kilometraje_colocacion IS NOT NULL
    GROUP BY 
        hc.coche_id, hc.cubierta_id
    ORDER BY 
        hc.coche_id, km_recorridos DESC
";
$result_kilometros = $conn->query($sql_kilometros);
$datos_kilometros = [];
$datos_por_coche = [];
if ($result_kilometros->num_rows > 0) {
    while ($row = $result_kilometros->fetch_assoc()) {
        $datos_kilometros[] = $row;
        
        // Agrupar datos por coche para gráficos
        if (!isset($datos_por_coche[$row['coche_id']])) {
            $datos_por_coche[$row['coche_id']] = [
                'km_total' => 0,
                'cubiertas' => [],
                'nombre_cubiertas' => [],
                'km_por_cubierta' => []
            ];
        }
        
        $datos_por_coche[$row['coche_id']]['km_total'] += $row['km_recorridos'];
        $datos_por_coche[$row['coche_id']]['cubiertas'][] = $row['cubierta_id'];
        $datos_por_coche[$row['coche_id']]['nombre_cubiertas'][] = $row['nombre_cubierta'];
        $datos_por_coche[$row['coche_id']]['km_por_cubierta'][] = $row['km_recorridos'];
    }
}

// Consulta para obtener las reconstrucciones por cubierta
$sql_reconstrucciones = "
    SELECT 
        c.id AS cubierta_id,
        c.nombre AS nombre_cubierta,
        COUNT(r.id) AS total_reconstrucciones,
        MIN(r.fecha_reconstruccion) AS primera_reconstruccion,
        MAX(r.fecha_reconstruccion) AS ultima_reconstruccion
    FROM 
        cubiertas c
    LEFT JOIN 
        reconstrucciones r ON c.id = r.cubierta_id
    WHERE 
        r.fecha_reconstruccion BETWEEN '$desde 00:00:00' AND '$hasta 23:59:59'
    GROUP BY 
        c.id
    ORDER BY 
        total_reconstrucciones DESC
";
$result_reconstrucciones = $conn->query($sql_reconstrucciones);
$datos_reconstrucciones = [];
if ($result_reconstrucciones->num_rows > 0) {
    while ($row = $result_reconstrucciones->fetch_assoc()) {
        $datos_reconstrucciones[] = $row;
    }
}

// Datos para gráfico comparativo
$labels_coches = [];
$data_cambios = [];
$data_kilometros = [];

foreach ($datos_cambios as $coche) {
    $labels_coches[] = 'Coche ' . $coche['coche_id'];
    $data_cambios[] = $coche['cambios_totales'];
    
    // Buscar los kilómetros totales para este coche
    $km_total = isset($datos_por_coche[$coche['coche_id']]) ? $datos_por_coche[$coche['coche_id']]['km_total'] : 0;
    $data_kilometros[] = $km_total;
}

// Convertir arrays a formato JSON para usar en JavaScript
$json_labels = json_encode($labels_coches);
$json_cambios = json_encode($data_cambios);
$json_kilometros = json_encode($data_kilometros);

// Cerrar la conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Cubiertas</title>
    <!-- Enlace a Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Enlace a Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jsPDF para exportar a PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <!-- SheetJS para exportar a Excel -->
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <!-- Enlace a archivo CSS externo -->
    <link rel="stylesheet" href="styles-dark.css">
    <link rel="stylesheet" href="header-fix.css">
	<link rel="stylesheet" href="nuevo-header.css">
	
	<!-- SheetJS para exportar a Excel -->
<script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
<!-- jsPDF para exportar a PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
	
	
    <style>
	
	/* Estilo específico solo para el botón PDF */
.boton-pdf {
    background-color: #e74c3c !important; /* Rojo */
    border-color: #c0392b !important; /* Borde en rojo oscuro */
}

.boton-pdf:hover {
    background-color: #c0392b !important; /* Rojo más oscuro al pasar el mouse */
    box-shadow: 0 0 15px rgba(231, 76, 60, 0.5) !important; /* Resplandor rojo */
} 
        /* Estilos específicos para la página de estadísticas */
        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        .chart-container {
            background-color: var(--color-card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            transition: all 0.3s;
        }
        
        .chart-container:hover {
            transform: translateY(-5px);
            box-shadow: var(--glow);
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--color-primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-card {
            background-color: var(--color-secondary);
            border-radius: var(--border-radius);
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--glow);
        }
        
        .stats-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--color-primary);
            margin: 10px 0;
        }
        
        .stats-label {
            font-size: 14px;
            color: var(--color-text-secondary);
        }
        
        .filtros-container {
            background-color: var(--color-card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 20px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
        }
        
        .filtros-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filtro-grupo {
            flex: 1;
            min-width: 200px;
        }
        
        .filtro-grupo label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--color-primary);
        }
        
        .filtro-grupo select,
        .filtro-grupo input[type="date"] {
            width: 100%;
            padding: 10px;
            background-color: #333;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            color: var(--color-text);
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        
        .filtro-grupo select:focus,
        .filtro-grupo input[type="date"]:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
        }
        
        .botones-exportar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .tabla-container {
            overflow-x: auto;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .tabla-estadisticas {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .tabla-estadisticas th, 
        .tabla-estadisticas td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }
        
        .tabla-estadisticas th {
            background-color: var(--color-secondary);
            color: var(--color-text);
            font-weight: 600;
        }
        
        .tabla-estadisticas tbody tr {
            transition: all 0.3s;
            background-color: var(--color-card-bg);
        }
        
        .tabla-estadisticas tbody tr:hover {
            background-color: #383838;
        }
        
        .kpi-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .kpi-card {
            background-color: var(--color-secondary);
            border-radius: var(--border-radius);
            padding: 20px;
            flex: 1;
            min-width: 200px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--glow);
        }
        
        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--color-primary);
            margin: 10px 0;
        }
        
        .kpi-label {
            font-size: 14px;
            color: var(--color-text-secondary);
            margin-bottom: 10px;
        }
        
        .kpi-icon {
            font-size: 24px;
            color: var(--color-primary);
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .kpi-container {
                flex-direction: column;
            }
            
            .filtros-form {
                flex-direction: column;
            }
        }
		
		/* Estilos específicos para botones de exportación */
.boton.exportar-excel {
    background-color: #27ae60 !important; /* Verde */
    border-color: #219653 !important;
}

.boton.exportar-excel:hover {
    background-color: #219653 !important;
    box-shadow: 0 0 15px rgba(39, 174, 96, 0.5) !important;
}

.boton.exportar-pdf {
    background-color: #e74c3c; /* Rojo */
    border-color: #c0392b;
}

.boton.exportar-pdf:hover {
    background-color: #c0392b;
    box-shadow: 0 0 15px rgba(231, 76, 60, 0.5);
}

.boton.exportar-excel, .boton.exportar-pdf {
    min-width: 200px !important; /* Ancho mínimo fijo */
    width: auto !important; /* Para permitir que crezca si es necesario */
    height: 48px !important; /* Altura fija */
    padding: 10px 20px !important; /* Padding interno consistente */
    text-align: center !important; /* Centrar el texto */
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

/* Alinear los iconos */
.boton.exportar-excel i, .boton.exportar-pdf i {
    margin-right: 10px !important;
}



.boton.exportar-excel {
    background: #27ae60 !important; /* Verde */
    border-color: #219653 !important;
    min-width: 200px !important;
    width: auto !important;
    height: 48px !important;
    padding: 10px 20px !important;
    text-align: center !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.boton.exportar-excel:hover {
    background: #219653 !important;
    box-shadow: 0 0 15px rgba(39, 174, 96, 0.5) !important;
}

    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header con logo -->
        <header>
            <div class="logo-container">
                <img src="LOGO.PNG" alt="Logo de la empresa" class="fade-in">
                <h1 class="fade-in delay-1">ESTADÍSTICAS DE CUBIERTAS</h1>
            </div>
        </header>

        <div class="content">
            <!-- Sección de navegación -->
            <div class="nav-buttons">
                <button class="boton slide-in" onclick="window.location.href='index.php'">
                    <i class="fas fa-home"></i> VOLVER A INICIO
                </button>

            </div>

            <!-- Sección de filtros -->
            <div class="filtros-container fade-in delay-2">
                <h2><i class="fas fa-filter"></i> Filtros</h2>
                <form method="GET" class="filtros-form">
                    <div class="filtro-grupo">
                        <label for="periodo"><i class="fas fa-calendar-alt"></i> Período</label>
                        <select name="periodo" id="periodo" onchange="this.form.submit()">
                            <option value="todos" <?php echo $periodo === 'todos' ? 'selected' : ''; ?>>Todo el historial</option>
                            <option value="ultimo_mes" <?php echo $periodo === 'ultimo_mes' ? 'selected' : ''; ?>>Último mes</option>
                            <option value="ultimos_3_meses" <?php echo $periodo === 'ultimos_3_meses' ? 'selected' : ''; ?>>Últimos 3 meses</option>
                            <option value="ultimo_anio" <?php echo $periodo === 'ultimo_anio' ? 'selected' : ''; ?>>Último año</option>
                            <option value="personalizado" <?php echo $periodo === 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                        </select>
                    </div>
                    <div class="filtro-grupo" id="fechas-container" style="<?php echo $periodo === 'personalizado' ? 'display: flex;' : 'display: none;'; ?> gap: 10px;">
                        <div>
                            <label for="desde"><i class="fas fa-calendar-day"></i> Desde</label>
                            <input type="date" name="desde" id="desde" value="<?php echo $desde; ?>">
                        </div>
                        <div>
                            <label for="hasta"><i class="fas fa-calendar-day"></i> Hasta</label>
                            <input type="date" name="hasta" id="hasta" value="<?php echo $hasta; ?>">
                        </div>
                    </div>
                    <div class="filtro-grupo" style="<?php echo $periodo === 'personalizado' ? 'display: block;' : 'display: none;'; ?>">
                        <button type="submit" class="boton">
                            <i class="fas fa-search"></i> Aplicar Filtros
                        </button>
						
                    </div>
					
                </form>
                <div class="botones-exportar">
<div class="botones-exportar">
    <button class="boton exportar-excel" onclick="exportarExcel()">
        <i class="fas fa-file-excel"></i> Exportar a Excel
    </button>
    <button class="boton exportar-pdf" onclick="exportarPDF()">
        <i class="fas fa-file-pdf"></i> Exportar a PDF
    </button>
	<br>
</div>

            </div>

            <!-- KPIs principales -->
            <div class="kpi-container fade-in delay-3">
                <div class="kpi-card">
                    <div class="kpi-icon"><i class="fas fa-exchange-alt"></i></div>
                    <div class="kpi-value"><?php echo !empty($datos_cambios) ? array_sum(array_column($datos_cambios, 'cambios_totales')) : 0; ?></div>
                    <div class="kpi-label">Cambios Totales</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon"><i class="fas fa-road"></i></div>
                    <div class="kpi-value"><?php echo !empty($datos_kilometros) ? number_format(array_sum(array_column($datos_kilometros, 'km_recorridos')), 0, ',', '.') : 0; ?></div>
                    <div class="kpi-label">Kilómetros Totales</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon"><i class="fas fa-sync-alt"></i></div>
                    <div class="kpi-value"><?php echo !empty($datos_reconstrucciones) ? array_sum(array_column($datos_reconstrucciones, 'total_reconstrucciones')) : 0; ?></div>
                    <div class="kpi-label">Reconstrucciones Totales</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon"><i class="fas fa-car"></i></div>
                    <div class="kpi-value"><?php echo count($datos_cambios); ?></div>
                    <div class="kpi-label">Coches Activos</div>
                </div>
            </div>

            <!-- Dashboard con gráficos -->
            <div class="dashboard-container">
                <!-- Gráfico de cambios de cubiertas por coche -->
                <div class="chart-container fade-in delay-3">
                    <div class="chart-title"><i class="fas fa-chart-bar"></i> Cambios de Cubiertas por Coche</div>
                    <canvas id="graficoCoches"></canvas>
                </div>
                
                <!-- Gráfico de kilómetros recorridos por coche -->
                <div class="chart-container fade-in delay-4">
                    <div class="chart-title"><i class="fas fa-chart-line"></i> Kilómetros Recorridos por Coche</div>
                    <canvas id="graficoKilometros"></canvas>
                </div>
            </div>

            <!-- Tabla detallada de cambios por coche -->
            <div class="chart-container fade-in delay-4" style="margin-top: 25px;">
                <div class="chart-title"><i class="fas fa-table"></i> Detalle de Cambios de Cubiertas por Coche</div>
                <div class="tabla-container">
                    <table class="tabla-estadisticas" id="tablaCambios">
                        <thead>
                            <tr>
                                <th>Coche ID</th>
                                <th>Total Cambios</th>
                                <th>Cubiertas Distintas</th>
                                <th>Primera Colocación</th>
                                <th>Última Colocación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($datos_cambios)): ?>
                                <?php foreach ($datos_cambios as $coche): ?>
                                    <tr>
                                        <td><?php echo $coche['coche_id']; ?></td>
                                        <td><?php echo $coche['cambios_totales']; ?></td>
                                        <td><?php echo $coche['cubiertas_distintas']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($coche['primera_colocacion'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($coche['ultima_colocacion'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No hay datos disponibles para el período seleccionado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabla detallada de kilómetros por cubierta y coche -->
            <div class="chart-container fade-in delay-5" style="margin-top: 25px;">
                <div class="chart-title"><i class="fas fa-tachometer-alt"></i> Detalle de Kilómetros por Cubierta</div>
                <div class="tabla-container">
                    <table class="tabla-estadisticas" id="tablaKilometros">
                        <thead>
                            <tr>
                                <th>Coche ID</th>
                                <th>Cubierta</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Fin</th>
                                <th>KM Inicial</th>
                                <th>KM Final</th>
                                <th>KM Recorridos</th>
                                <th>Reconstrucciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($datos_kilometros)): ?>
                                <?php foreach ($datos_kilometros as $km): ?>
                                    <tr>
                                        <td><?php echo $km['coche_id']; ?></td>
                                        <td><?php echo $km['nombre_cubierta']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($km['fecha_inicio'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($km['fecha_fin'])); ?></td>
                                        <td><?php echo number_format($km['km_inicial'], 0, ',', '.'); ?></td>
                                        <td><?php echo $km['km_final'] > 0 ? number_format($km['km_final'], 0, ',', '.') : 'En uso'; ?></td>
                                        <td><?php echo number_format($km['km_recorridos'], 0, ',', '.'); ?></td>
                                        <td><?php echo $km['reconstrucciones']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No hay datos disponibles para el período seleccionado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <footer class="fade-in delay-5">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión de Cubiertas | Empresa Casanova S.A todos los derechos reservados</p>
        </footer>
    </div>

    <script>
        // Script para manejar animaciones
        document.addEventListener('DOMContentLoaded', function() {
            // Añadir clase de animación a elementos
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                }, 100 * index);
            });
            
            const slideElements = document.querySelectorAll('.slide-in');
            slideElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateX(0)';
                }, 100 * index);
            });
            
            // Manejar cambio en el selector de período
            document.getElementById('periodo').addEventListener('change', function() {
                const fechasContainer = document.getElementById('fechas-container');
                const botonFiltrar = fechasContainer.nextElementSibling;
                
                if (this.value === 'personalizado') {
                    fechasContainer.style.display = 'flex';
                    botonFiltrar.style.display = 'block';
                } else {
                    fechasContainer.style.display = 'none';
                    botonFiltrar.style.display = 'none';
                    this.form.submit();
                }
            });
            
            // Inicializar gráficos
            inicializarGraficos();
        });
        
        // Inicializar gráficos con Chart.js
function inicializarGraficos() {
    // Datos para los gráficos
    const labels = <?php echo $json_labels; ?>;
    const dataCambios = <?php echo $json_cambios; ?>;
    const dataKilometros = <?php echo $json_kilometros; ?>;
    
    // Gráfico de cambios de cubiertas por coche
    const ctxCoches = document.getElementById('graficoCoches').getContext('2d');
    new Chart(ctxCoches, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Número de Cambios',
                data: dataCambios,
                backgroundColor: 'rgba(52, 152, 219, 0.7)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
    y: {
        beginAtZero: true,
        min: 0, // Forzar el mínimo a cero
        ticks: {
            color: '#f5f5f5'
        },
        grid: {
            color: '#444'
        }
    },
                x: {
                    ticks: {
                        color: '#f5f5f5'
                    },
                    grid: {
                        color: '#444'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#f5f5f5'
                    }
                }
            }
        }
    });
    
    // Gráfico de kilómetros recorridos por coche
    const ctxKilometros = document.getElementById('graficoKilometros').getContext('2d');
    new Chart(ctxKilometros, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Kilómetros Recorridos',
                data: dataKilometros,
                backgroundColor: 'rgba(46, 204, 113, 0.7)',
                borderColor: 'rgba(46, 204, 113, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#f5f5f5'
                    },
                    grid: {
                        color: '#444'
                    }
                },
                x: {
                    ticks: {
                        color: '#f5f5f5'
                    },
                    grid: {
                        color: '#444'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#f5f5f5'
                    }
                }
            }
        }
    });
}

// Exportar a Excel
function exportarExcel() {
    try {
        // Crear un nuevo libro de trabajo
        const wb = XLSX.utils.book_new();
        
        // Crear una hoja para cambios de cubiertas
        const tablaCambios = document.getElementById('tablaCambios');
        const wsCambios = XLSX.utils.table_to_sheet(tablaCambios);
        XLSX.utils.book_append_sheet(wb, wsCambios, "Cambios de Cubiertas");
        
        // Crear una hoja para kilómetros
        const tablaKilometros = document.getElementById('tablaKilometros');
        const wsKilometros = XLSX.utils.table_to_sheet(tablaKilometros);
        XLSX.utils.book_append_sheet(wb, wsKilometros, "Kilómetros por Cubierta");
        
        // Guardar el archivo
        const fechaActual = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, `Estadisticas_Cubiertas_${fechaActual}.xlsx`);
        alert('Excel exportado con éxito');
    } catch(error) {
        console.error('Error al exportar a Excel:', error);
        alert('Error al exportar a Excel: ' + error.message);
    }
}

// Exportar a PDF
function exportarPDF() {
    try {
        // Inicializar jsPDF - corregido para usar la variable global correcta
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Añadir título
        doc.setFontSize(18);
        doc.text("Estadísticas de Cubiertas", 14, 20);
        
        // Añadir fecha de generación
        const fechaActual = new Date().toLocaleDateString();
        doc.setFontSize(10);
        doc.text(`Informe generado el ${fechaActual}`, 14, 30);
        
        // Añadir filtros aplicados
        doc.setFontSize(12);
        doc.text("Filtros aplicados:", 14, 40);
        const periodoElement = document.getElementById('periodo');
        const periodoText = periodoElement.options[periodoElement.selectedIndex].text;
        doc.text(`Período: ${periodoText}`, 14, 48);
        
        // Añadir tabla de cambios de cubiertas
        doc.setFontSize(14);
        doc.text("Cambios de Cubiertas por Coche", 14, 60);
        
        // Utilizar autoTable para la tabla de cambios
        doc.autoTable({
            html: '#tablaCambios',
            startY: 65,
            styles: { fontSize: 8 },
            theme: 'grid'
        });
        
        // Añadir tabla de kilómetros en una nueva página
        doc.addPage();
        doc.setFontSize(14);
        doc.text("Kilómetros por Cubierta", 14, 20);
        
        // Utilizar autoTable para la tabla de kilómetros
        doc.autoTable({
            html: '#tablaKilometros',
            startY: 25,
            styles: { fontSize: 8 },
            theme: 'grid'
        });
        
        // Guardar el PDF
        doc.save(`Estadisticas_Cubiertas_${fechaActual.replace(/\//g, '-')}.pdf`);
        alert('PDF exportado con éxito');
    } catch(error) {
        console.error('Error al exportar a PDF:', error);
        alert('Error al exportar a PDF: ' + error.message);
    }
}
    </script>
</body>
</html>
