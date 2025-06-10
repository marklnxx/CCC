<?php
// wizard_steps/paso5_finalizacion.php

// Procesar finalizaci車n si se envi車 el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paso_actual']) && $_POST['paso_actual'] == '5') {
    // Marcar el wizard como completado
    $sql_wizard_completado = "INSERT INTO wizard_config (clave, valor) VALUES ('wizard_completado', 'si') 
                              ON DUPLICATE KEY UPDATE valor = 'si', fecha_creacion = NOW()";
    $conn->query($sql_wizard_completado);
    
    // Registrar fecha de configuraci車n inicial
    $sql_fecha_config = "INSERT INTO wizard_config (clave, valor) VALUES ('fecha_configuracion_inicial', NOW()) 
                         ON DUPLICATE KEY UPDATE valor = NOW()";
    $conn->query($sql_fecha_config);
    
    // Limpiar datos temporales del wizard si existen
    $sql_clean = "DELETE FROM wizard_config WHERE clave LIKE 'temp_%'";
    $conn->query($sql_clean);
    
    // Verificar que la columna posicion existe en cubiertas
    $sql_check_posicion = "SHOW COLUMNS FROM cubiertas LIKE 'posicion'";
    $result_posicion = $conn->query($sql_check_posicion);
    if ($result_posicion->num_rows == 0) {
        $sql_add_posicion = "ALTER TABLE cubiertas ADD COLUMN posicion VARCHAR(50) NULL";
        $conn->query($sql_add_posicion);
    }
    
    // Crear tabla historial_bajas si no existe
    $sql_historial_bajas = "CREATE TABLE IF NOT EXISTS historial_bajas (
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
        usuario VARCHAR(50) NULL,
        FOREIGN KEY (cubierta_id) REFERENCES cubiertas(id) ON DELETE CASCADE
    )";
    $conn->query($sql_historial_bajas);
    
    // Crear tabla kilometraje_diario si no existe
    $sql_kilometraje_diario = "CREATE TABLE IF NOT EXISTS kilometraje_diario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coche_id INT NOT NULL,
        fecha DATE NOT NULL,
        kilometraje INT NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_coche_fecha (coche_id, fecha),
        FOREIGN KEY (coche_id) REFERENCES coches(id) ON DELETE CASCADE
    )";
    $conn->query($sql_kilometraje_diario);
    
    // Redirigir al sistema principal
    header("Location: index.php?wizard_completado=1&mensaje=configuracion_exitosa");
    exit;
}

// Obtener resumen de configuraci車n final
$sql_resumen = "SELECT 
                    (SELECT COUNT(*) FROM coches) as total_buses,
                    (SELECT COUNT(*) FROM cubiertas) as total_cubiertas,
                    (SELECT COUNT(*) FROM cubiertas WHERE coche_id IS NOT NULL) as cubiertas_asignadas,
                    (SELECT COUNT(*) FROM cubiertas WHERE coche_id IS NULL) as cubiertas_disponibles,
                    (SELECT COUNT(*) FROM historial_cubiertas) as registros_historial";
$result_resumen = $conn->query($sql_resumen);
$resumen = $result_resumen->fetch_assoc();

// Obtener lista de buses
$sql_buses = "SELECT id FROM coches ORDER BY id";
$result_buses = $conn->query($sql_buses);
$buses = [];
while ($row = $result_buses->fetch_assoc()) {
    $buses[] = $row['id'];
}

// Obtener cubiertas por estado
$sql_estados = "SELECT estado, COUNT(*) as cantidad 
                FROM cubiertas 
                GROUP BY estado 
                ORDER BY cantidad DESC";
$result_estados = $conn->query($sql_estados);
$estados_cubiertas = [];
while ($row = $result_estados->fetch_assoc()) {
    $estados_cubiertas[] = $row;
}

// Obtener asignaciones actuales
$sql_asignaciones = "SELECT c.nombre, co.id as bus_id, c.posicion 
                     FROM cubiertas c 
                     JOIN coches co ON c.coche_id = co.id 
                     ORDER BY co.id, c.posicion";
$result_asignaciones = $conn->query($sql_asignaciones);
$asignaciones = [];
while ($row = $result_asignaciones->fetch_assoc()) {
    $asignaciones[] = $row;
}

// Calcular estad赤sticas adicionales
$buses_con_cubiertas = [];
if (!empty($asignaciones)) {
    foreach ($asignaciones as $asignacion) {
        $buses_con_cubiertas[$asignacion['bus_id']][] = $asignacion;
    }
}

$buses_completos = 0;
$buses_parciales = 0;
$buses_sin_cubiertas = 0;

foreach ($buses as $bus_id) {
    if (isset($buses_con_cubiertas[$bus_id])) {
        $num_cubiertas = count($buses_con_cubiertas[$bus_id]);
        if ($num_cubiertas >= 6) {
            $buses_completos++;
        } else {
            $buses_parciales++;
        }
    } else {
        $buses_sin_cubiertas++;
    }
}
?>

<div class="step-content">
    <div class="step-header">
        <i class="fas fa-flag-checkered"></i>
        <h2>Configuraci車n Completa</h2>
        <p>Revisa el resumen y finaliza la configuraci車n del sistema</p>
    </div>

    <div class="configuration-summary">
        <h3>?? Resumen de la Configuraci車n</h3>
        
        <div class="summary-grid">
            <div class="summary-card buses-card">
                <div class="card-header">
                    <i class="fas fa-bus"></i>
                    <h4>Buses Configurados</h4>
                </div>
                <div class="card-content">
                    <div class="main-number"><?php echo $resumen['total_buses']; ?></div>
                    <div class="buses-breakdown">
                        <div class="breakdown-item">
                            <span class="number"><?php echo $buses_completos; ?></span>
                            <span class="label">Completos (6 cubiertas)</span>
                        </div>
                        <div class="breakdown-item">
                            <span class="number"><?php echo $buses_parciales; ?></span>
                            <span class="label">Parciales</span>
                        </div>
                        <div class="breakdown-item">
                            <span class="number"><?php echo $buses_sin_cubiertas; ?></span>
                            <span class="label">Sin cubiertas</span>
                        </div>
                    </div>
                    
                    <?php if (count($buses) <= 20): ?>
                        <div class="buses-list">
                            <?php 
                            $buses_chunks = array_chunk($buses, 10);
                            foreach ($buses_chunks as $chunk):
                            ?>
                                <div class="bus-row">
                                    <?php foreach ($chunk as $bus_id): ?>
                                        <span class="bus-badge"><?php echo $bus_id; ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="buses-range">
                            <p>Buses del <?php echo min($buses); ?> al <?php echo max($buses); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="summary-card cubiertas-card">
                <div class="card-header">
                    <i class="fas fa-circle"></i>
                    <h4>Inventario de Cubiertas</h4>
                </div>
                <div class="card-content">
                    <div class="main-number"><?php echo $resumen['total_cubiertas']; ?></div>
                    <div class="cubierta-breakdown">
                        <div class="breakdown-item">
                            <span class="number"><?php echo $resumen['cubiertas_asignadas']; ?></span>
                            <span class="label">Asignadas</span>
                        </div>
                        <div class="breakdown-item">
                            <span class="number"><?php echo $resumen['cubiertas_disponibles']; ?></span>
                            <span class="label">Disponibles</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($estados_cubiertas)): ?>
                        <div class="estados-breakdown">
                            <?php foreach ($estados_cubiertas as $estado): ?>
                                <div class="estado-item">
                                    <span class="estado-badge estado-<?php echo $estado['estado']; ?>">
                                        <?php echo strtoupper($estado['estado']); ?>
                                    </span>
                                    <span class="estado-count"><?php echo $estado['cantidad']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($asignaciones)): ?>
                <div class="summary-card asignaciones-card">
                    <div class="card-header">
                        <i class="fas fa-link"></i>
                        <h4>Asignaciones Realizadas</h4>
                    </div>
                    <div class="card-content">
                        <div class="main-number"><?php echo count($asignaciones); ?></div>
                        <div class="asignaciones-preview">
                            <?php 
                            $mostrar_buses = array_slice(array_keys($buses_con_cubiertas), 0, 5);
                            foreach ($mostrar_buses as $bus_id):
                            ?>
                                <div class="bus-asignacion">
                                    <strong>Bus #<?php echo $bus_id; ?>:</strong>
                                    <span class="cubiertas-count"><?php echo count($buses_con_cubiertas[$bus_id]); ?> cubiertas</span>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($buses_con_cubiertas) > 5): ?>
                                <div class="mas-buses">
                                    + <?php echo count($buses_con_cubiertas) - 5; ?> buses m芍s
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="summary-card historial-card">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    <h4>Sistema de Seguimiento</h4>
                </div>
                <div class="card-content">
                    <div class="main-number"><?php echo $resumen['registros_historial']; ?></div>
                    <div class="historial-info">
                        <p>Registros de historial inicializados</p>
                        <p>Sistema de trazabilidad: <span class="status-ready">LISTO</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="system-status">
        <h3>?? Estado del Sistema</h3>
        <div class="status-checks">
            <div class="status-item success">
                <i class="fas fa-check-circle"></i>
                <span>Base de datos configurada correctamente</span>
            </div>
            <div class="status-item success">
                <i class="fas fa-check-circle"></i>
                <span>Buses registrados (<?php echo $resumen['total_buses']; ?>)</span>
            </div>
            <div class="status-item success">
                <i class="fas fa-check-circle"></i>
                <span>Cubiertas inventariadas (<?php echo $resumen['total_cubiertas']; ?>)</span>
            </div>
            <div class="status-item success">
                <i class="fas fa-check-circle"></i>
                <span>Tablas auxiliares creadas</span>
            </div>
            <?php if ($resumen['cubiertas_asignadas'] > 0): ?>
                <div class="status-item success">
                    <i class="fas fa-check-circle"></i>
                    <span>Asignaciones iniciales configuradas (<?php echo $resumen['cubiertas_asignadas']; ?>)</span>
                </div>
            <?php else: ?>
                <div class="status-item info">
                    <i class="fas fa-info-circle"></i>
                    <span>Sin asignaciones iniciales (se pueden hacer despu谷s)</span>
                </div>
            <?php endif; ?>
            <div class="status-item success">
                <i class="fas fa-check-circle"></i>
                <span>Sistema listo para usar</span>
            </div>
        </div>
    </div>

    <div class="next-steps">
        <h3>?? Pr車ximos Pasos</h3>
        <div class="steps-grid">
            <div class="next-step-card">
                <i class="fas fa-home"></i>
                <h4>Acceder al Sistema</h4>
                <p>Ir a la p芍gina principal para comenzar a gestionar las cubiertas</p>
            </div>
            
            <?php if ($resumen['cubiertas_asignadas'] == 0): ?>
                <div class="next-step-card">
                    <i class="fas fa-link"></i>
                    <h4>Asignar Cubiertas</h4>
                    <p>Seleccionar un bus y asignar cubiertas a cada posici車n</p>
                </div>
            <?php endif; ?>
            
            <div class="next-step-card">
                <i class="fas fa-tachometer-alt"></i>
                <h4>Registrar Kilometrajes</h4>
                <p>Ingresar los kilometrajes actuales de los tableros</p>
            </div>
            
            <div class="next-step-card">
                <i class="fas fa-tools"></i>
                <h4>Gestionar Gomer赤a</h4>
                <p>Administrar el inventario y estados de las cubiertas</p>
            </div>
            
            <div class="next-step-card">
                <i class="fas fa-chart-bar"></i>
                <h4>Ver Estad赤sticas</h4>
                <p>Monitorear el rendimiento y estado de las cubiertas</p>
            </div>
            
            <div class="next-step-card">
                <i class="fas fa-history"></i>
                <h4>Revisar Historial</h4>
                <p>Consultar el historial de cambios y movimientos</p>
            </div>
        </div>
    </div>

    <div class="important-info">
        <div class="info-box success">
            <i class="fas fa-check-circle"></i>
            <div>
                <h4>?Configuraci車n Exitosa!</h4>
                <p>El sistema ha sido configurado correctamente y est芍 listo para usar. Todos los datos se han guardado de forma permanente y las tablas necesarias han sido creadas.</p>
            </div>
        </div>
        
        <div class="info-box info">
            <i class="fas fa-lightbulb"></i>
            <div>
                <h4>Consejos para Empezar</h4>
                <ul>
                    <li>Comienza asignando cubiertas a los buses m芍s utilizados</li>
                    <li>Registra los kilometrajes actuales de los tableros</li>
                    <li>Usa la secci車n de alertas para monitorear cubiertas que necesiten cambio</li>
                    <li>Explora la gomer赤a para gestionar el inventario</li>
                    <li>Consulta las estad赤sticas regularmente para optimizar el uso</li>
                </ul>
            </div>
        </div>
        
        <div class="info-box warning">
            <i class="fas fa-shield-alt"></i>
            <div>
                <h4>Recomendaciones de Seguridad</h4>
                <p>Se recomienda hacer un backup de la base de datos regularmente. El sistema mantendr芍 un historial completo de todos los cambios y movimientos de cubiertas.</p>
            </div>
        </div>
    </div>

    <form method="POST" class="wizard-form">
        <input type="hidden" name="paso_actual" value="5">
        
        <div class="final-actions">
            <div class="action-buttons">
                <a href="?paso=4" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Paso Anterior
                </a>
                
                <button type="submit" class="btn btn-success btn-large">
                    <i class="fas fa-rocket"></i>
                    Finalizar y Acceder al Sistema
                </button>
            </div>
            
            <div class="completion-note">
                <p>Al hacer clic en "Finalizar", se marcar芍 la configuraci車n como completa y ser芍s redirigido al sistema principal donde podr芍s comenzar a gestionar tus cubiertas.</p>
            </div>
        </div>
    </form>
</div>

<style>
.configuration-summary {
    margin: 30px 0;
}

.summary-grid {
    display: grid;
    gap: 20px;
    margin: 20px 0;
}

.summary-card {
    background: #34495e;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #4a6741;
}

.card-header {
    background: #2c3e50;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #4a6741;
}

.card-header i {
    font-size: 20px;
    color: #3498db;
}

.card-header h4 {
    margin: 0;
    color: #ecf0f1;
    font-size: 16px;
}

.card-content {
    padding: 20px;
}

.main-number {
    font-size: 36px;
    font-weight: bold;
    color: #3498db;
    text-align: center;
    margin-bottom: 15px;
}

.buses-breakdown, .cubierta-breakdown {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
}

.breakdown-item {
    text-align: center;
    padding: 10px;
    background: #2c3e50;
    border-radius: 6px;
}

.breakdown-item .number {
    display: block;
    font-size: 20px;
    font-weight: bold;
    color: #2ecc71;
}

.breakdown-item .label {
    font-size: 11px;
    color: #bdc3c7;
    text-transform: uppercase;
}

.buses-list {
    max-height: 100px;
    overflow-y: auto;
}

.bus-row {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin: 5px 0;
}

.bus-badge {
    background: #3498db;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
}

.buses-range {
    text-align: center;
    color: #bdc3c7;
    font-style: italic;
}

.estados-breakdown {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.estado-item {
    display: flex;
    align-items: center;
    gap: 5px;
    background: #2c3e50;
    padding: 5px 10px;
    border-radius: 15px;
}

.estado-badge {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 8px;
    font-weight: 600;
}

.estado-casanova {
    background: #2ecc71;
    color: white;
}

.estado-silacor {
    background: #3498db;
    color: white;
}

.estado-baja {
    background: #e74c3c;
    color: white;
}

.estado-count {
    color: #ecf0f1;
    font-size: 12px;
    font-weight: 500;
}

.asignaciones-preview {
    font-size: 13px;
}

.bus-asignacion {
    display: flex;
    justify-content: space-between;
    margin: 8px 0;
    padding: 8px;
    background: #2c3e50;
    border-radius: 4px;
}

.bus-asignacion strong {
    color: #3498db;
}

.cubiertas-count {
    color: #2ecc71;
    font-weight: 500;
}

.mas-buses {
    text-align: center;
    color: #bdc3c7;
    font-style: italic;
    margin-top: 10px;
}

.historial-info p {
    margin: 5px 0;
    color: #bdc3c7;
    font-size: 14px;
}

.status-ready {
    color: #2ecc71;
    font-weight: 600;
}

.system-status {
    background: #2c3e50;
    padding: 25px;
    border-radius: 8px;
    margin: 30px 0;
}

.status-checks {
    display: grid;
    gap: 10px;
    margin-top: 15px;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
}

.status-item.success i {
    color: #2ecc71;
}

.status-item.info i {
    color: #3498db;
}

.status-item span {
    color: #ecf0f1;
}

.next-steps {
    margin: 30px 0;
}

.steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.next-step-card {
    background: #34495e;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.next-step-card:hover {
    border-color: #3498db;
    transform: translateY(-3px);
}

.next-step-card i {
    font-size: 32px;
    color: #3498db;
    margin-bottom: 10px;
    display: block;
}

.next-step-card h4 {
    margin: 10px 0 8px 0;
    color: #ecf0f1;
    font-size: 16px;
}

.next-step-card p {
    margin: 0;
    color: #bdc3c7;
    font-size: 13px;
    line-height: 1.4;
}

.important-info {
    margin: 30px 0;
}

.info-box {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 20px;
    border-radius: 8px;
    margin: 15px 0;
}

.info-box.success {
    background: rgba(46, 204, 113, 0.1);
    border: 1px solid #2ecc71;
}

.info-box.info {
    background: rgba(52, 152, 219, 0.1);
    border: 1px solid #3498db;
}

.info-box.warning {
    background: rgba(241, 196, 15, 0.1);
    border: 1px solid #f1c40f;
}

.info-box i {
    font-size: 24px;
    margin-top: 2px;
    flex-shrink: 0;
}

.info-box.success i {
    color: #2ecc71;
}

.info-box.info i {
    color: #3498db;
}

.info-box.warning i {
    color: #f1c40f;
}

.info-box h4 {
    margin: 0 0 8px 0;
    color: #ecf0f1;
    font-size: 16px;
}

.info-box p {
    margin: 0;
    color: #bdc3c7;
    line-height: 1.5;
}

.info-box ul {
    margin: 8px 0 0 0;
    padding-left: 20px;
    color: #bdc3c7;
}

.info-box li {
    margin: 5px 0;
    line-height: 1.4;
}

.final-actions {
    margin-top: 40px;
    text-align: center;
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 15px;
}

.btn-large {
    padding: 15px 30px;
    font-size: 16px;
    font-weight: 600;
}

.btn-success {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #27ae60, #1e8449);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 204, 113, 0.4);
}

.completion-note {
    color: #bdc3c7;
    font-size: 13px;
    font-style: italic;
}

@media (max-width: 768px) {
    .buses-breakdown, .cubierta-breakdown {
        grid-template-columns: 1fr;
    }
    
    .steps-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .info-box {
        flex-direction: column;
        text-align: center;
    }
    
    .status-checks {
        grid-template-columns: 1fr;
    }
}
</style>