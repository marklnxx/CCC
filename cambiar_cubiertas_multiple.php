<?php
// cambiar_cubiertas_multiple.php
header('Content-Type: application/json');

// Recibir los datos enviados por POST
$json_datos = file_get_contents('php://input');
$datos = json_decode($json_datos, true);

// Verificar que se recibieron datos válidos
if (!$datos || !is_array($datos)) {
    echo json_encode(['exito' => false, 'mensaje' => 'Datos inválidos']);
    exit;
}

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['exito' => false, 'mensaje' => 'Error de conexión: ' . $conn->connect_error]);
    exit;
}

// Iniciar transacción
$conn->begin_transaction();

try {
    $cambios_realizados = 0;
    $errores = [];
    
    // Detectar formato de datos y normalizarlo
    $datos_normalizados = [];
    
    // Determinar si los datos son un array de objetos (formato index2.php) o un objeto de objetos
    $es_array_de_objetos = isset($datos[0]);
    
    if ($es_array_de_objetos) {
        // Formato de index2.php: Array de objetos
        $datos_normalizados = $datos;
    } else {
        // Formato anterior: Objeto con pares clave-valor
        foreach ($datos as $key => $value) {
            $datos_normalizados[] = $value;
        }
    }
    
    // Log para depuración (opcional)
    error_log("Datos normalizados: " . print_r($datos_normalizados, true));

    // FASE 1: VALIDACIÓN COMPLETA ANTES DE HACER CAMBIOS
    error_log("FASE 1: Iniciando validación completa");
    
    foreach ($datos_normalizados as $index => $cambio) {
        // Extraer datos del cambio
        $coche_id = isset($cambio['coche_id']) ? $cambio['coche_id'] : null;
        $cubierta_vieja_id = isset($cambio['cubierta_vieja_id']) ? $cambio['cubierta_vieja_id'] : null;
        $nueva_cubierta_id = isset($cambio['nueva_cubierta_id']) ? $cambio['nueva_cubierta_id'] : null;
        $kilometraje = isset($cambio['kilometraje']) ? $cambio['kilometraje'] : null;
        $posicion = isset($cambio['posicion']) ? $cambio['posicion'] : null;
        
        // Verificar si los datos obligatorios están presentes
        if (empty($coche_id) || empty($cubierta_vieja_id) || empty($nueva_cubierta_id) || empty($kilometraje)) {
            $errores[] = "Cambio #" . ($index + 1) . ": Datos incompletos (Coche: $coche_id, Vieja: $cubierta_vieja_id, Nueva: $nueva_cubierta_id, KM: $kilometraje)";
            continue;
        }
        
        // Validar que el kilometraje sea numérico y positivo
        if (!is_numeric($kilometraje) || $kilometraje <= 0) {
            $errores[] = "Cambio #" . ($index + 1) . ": Kilometraje inválido ($kilometraje)";
            continue;
        }
        
        // Verificar que la nueva cubierta existe y está disponible
        $sql_verificar_nueva = "SELECT id FROM cubiertas WHERE id = ? AND coche_id IS NULL AND estado = 'casanova'";
        $stmt_verificar = $conn->prepare($sql_verificar_nueva);
        $stmt_verificar->bind_param("i", $nueva_cubierta_id);
        $stmt_verificar->execute();
        $result_verificar = $stmt_verificar->get_result();
        
        if ($result_verificar->num_rows === 0) {
            $errores[] = "Cambio #" . ($index + 1) . ": La cubierta nueva ID $nueva_cubierta_id no existe o no está disponible";
            $stmt_verificar->close();
            continue;
        }
        $stmt_verificar->close();
        
        // Verificar que la cubierta vieja realmente está asignada al coche
        $sql_verificar_vieja = "SELECT id FROM cubiertas WHERE id = ? AND coche_id = ?";
        $stmt_verificar_vieja = $conn->prepare($sql_verificar_vieja);
        $stmt_verificar_vieja->bind_param("ii", $cubierta_vieja_id, $coche_id);
        $stmt_verificar_vieja->execute();
        $result_verificar_vieja = $stmt_verificar_vieja->get_result();
        
        if ($result_verificar_vieja->num_rows === 0) {
            $errores[] = "Cambio #" . ($index + 1) . ": La cubierta vieja ID $cubierta_vieja_id no está asignada al coche $coche_id";
            $stmt_verificar_vieja->close();
            continue;
        }
        $stmt_verificar_vieja->close();
        
        // Si no se proporcionó la posición, obtenerla de la base de datos
        if (empty($posicion)) {
            $sql_posicion = "SELECT posicion FROM cubiertas WHERE id = ?";
            $stmt_posicion = $conn->prepare($sql_posicion);
            $stmt_posicion->bind_param("i", $cubierta_vieja_id);
            $stmt_posicion->execute();
            $result_posicion = $stmt_posicion->get_result();
            
            if ($result_posicion->num_rows > 0) {
                $row_posicion = $result_posicion->fetch_assoc();
                $datos_normalizados[$index]['posicion'] = $row_posicion['posicion']; // Actualizar en el array
            } else {
                $errores[] = "Cambio #" . ($index + 1) . ": No se pudo obtener la posición de la cubierta vieja ID $cubierta_vieja_id";
            }
            $stmt_posicion->close();
        }
        
        // Verificar que el kilometraje sea mayor al último registrado para este coche
        $sql_ultimo_km = "SELECT MAX(kilometraje_retiro) as ultimo_km FROM historial_cubiertas WHERE coche_id = ?";
        $stmt_ultimo_km = $conn->prepare($sql_ultimo_km);
        $stmt_ultimo_km->bind_param("i", $coche_id);
        $stmt_ultimo_km->execute();
        $result_ultimo_km = $stmt_ultimo_km->get_result();
        $row_ultimo_km = $result_ultimo_km->fetch_assoc();
        $ultimo_km = $row_ultimo_km['ultimo_km'] ?: 0;
        $stmt_ultimo_km->close();
        
        if ($kilometraje < $ultimo_km) {
            $errores[] = "Cambio #" . ($index + 1) . ": Kilometraje $kilometraje es menor que el último registrado ($ultimo_km) para el coche $coche_id";
            continue;
        }
    }

    // Si hay errores en la validación, abortar TODO
    if (!empty($errores)) {
        error_log("Errores de validación en cambiar_cubiertas_multiple.php: " . implode(", ", $errores));
        $conn->rollback();
        echo json_encode(['exito' => false, 'mensaje' => 'Errores de validación encontrados: ' . implode(' | ', $errores)]);
        exit;
    }

    // FASE 2: EJECUTAR CAMBIOS (TODAS LAS VALIDACIONES PASARON)
    error_log("FASE 2: Ejecutando cambios - Validaciones completadas exitosamente");
    
    foreach ($datos_normalizados as $index => $cambio) {
        // Extraer datos del cambio (ya validados)
        $coche_id = $cambio['coche_id'];
        $cubierta_vieja_id = $cambio['cubierta_vieja_id'];
        $nueva_cubierta_id = $cambio['nueva_cubierta_id'];
        $kilometraje = $cambio['kilometraje'];
        $posicion = $cambio['posicion'];
        
        error_log("Procesando cambio #" . ($index + 1) . ": Coche $coche_id, Vieja $cubierta_vieja_id -> Nueva $nueva_cubierta_id");
        
        // ORDEN SEGURO DE OPERACIONES:
        
        // 1. Registrar la colocación de la nueva cubierta PRIMERO
        $sql_registrar_nueva = "INSERT INTO historial_cubiertas (cubierta_id, coche_id, fecha_colocacion, kilometraje_colocacion) VALUES (?, ?, NOW(), ?)";
        $stmt_registrar_nueva = $conn->prepare($sql_registrar_nueva);
        $stmt_registrar_nueva->bind_param("iii", $nueva_cubierta_id, $coche_id, $kilometraje);
        if (!$stmt_registrar_nueva->execute()) {
            throw new Exception("Cambio #" . ($index + 1) . ": Error al registrar colocación de nueva cubierta ID $nueva_cubierta_id: " . $stmt_registrar_nueva->error);
        }
        $stmt_registrar_nueva->close();
        
        // 2. Asignar la nueva cubierta al coche
        $sql_asignar_nueva = "UPDATE cubiertas SET coche_id = ?, posicion = ? WHERE id = ?";
        $stmt_asignar_nueva = $conn->prepare($sql_asignar_nueva);
        $stmt_asignar_nueva->bind_param("isi", $coche_id, $posicion, $nueva_cubierta_id);
        if (!$stmt_asignar_nueva->execute() || $stmt_asignar_nueva->affected_rows === 0) {
            throw new Exception("Cambio #" . ($index + 1) . ": Error al asignar nueva cubierta ID $nueva_cubierta_id al coche $coche_id");
        }
        $stmt_asignar_nueva->close();
        
        // 3. Registrar la retirada de la cubierta vieja
        $sql_retirar_vieja = "UPDATE historial_cubiertas 
                              SET fecha_retiro = NOW(), kilometraje_retiro = ? 
                              WHERE cubierta_id = ? AND coche_id = ? AND fecha_retiro IS NULL 
                              ORDER BY fecha_colocacion DESC LIMIT 1";
        $stmt_retirar_vieja = $conn->prepare($sql_retirar_vieja);
        $stmt_retirar_vieja->bind_param("iii", $kilometraje, $cubierta_vieja_id, $coche_id);
        if (!$stmt_retirar_vieja->execute()) {
            throw new Exception("Cambio #" . ($index + 1) . ": Error al registrar retiro de cubierta vieja ID $cubierta_vieja_id: " . $stmt_retirar_vieja->error);
        }
        $stmt_retirar_vieja->close();
        
        // 4. Desasignar la cubierta vieja del coche (ÚLTIMO PASO)
        $sql_desasignar_vieja = "UPDATE cubiertas SET coche_id = NULL, estado = 'casanova', posicion = NULL WHERE id = ?";
        $stmt_desasignar_vieja = $conn->prepare($sql_desasignar_vieja);
        $stmt_desasignar_vieja->bind_param("i", $cubierta_vieja_id);
        if (!$stmt_desasignar_vieja->execute()) {
            throw new Exception("Cambio #" . ($index + 1) . ": Error al desasignar cubierta vieja ID $cubierta_vieja_id: " . $stmt_desasignar_vieja->error);
        }
        $stmt_desasignar_vieja->close();
        
        // 5. Actualizar kilometraje_diario si existe la tabla
        $sql_check_table = "SHOW TABLES LIKE 'kilometraje_diario'";
        $result_check_table = $conn->query($sql_check_table);
        
        if ($result_check_table->num_rows > 0) {
            $fecha_actual = date('Y-m-d');
            
            // Verificar si ya existe un registro para esta fecha y coche
            $sql_check = "SELECT id FROM kilometraje_diario WHERE coche_id = ? AND fecha = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("is", $coche_id, $fecha_actual);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                // Actualizar registro existente
                $sql_update_km = "UPDATE kilometraje_diario SET kilometraje = ? WHERE coche_id = ? AND fecha = ?";
                $stmt_update_km = $conn->prepare($sql_update_km);
                $stmt_update_km->bind_param("iis", $kilometraje, $coche_id, $fecha_actual);
                $stmt_update_km->execute();
                $stmt_update_km->close();
            } else {
                // Insertar nuevo registro
                $sql_insert_km = "INSERT INTO kilometraje_diario (coche_id, fecha, kilometraje) VALUES (?, ?, ?)";
                $stmt_insert_km = $conn->prepare($sql_insert_km);
                $stmt_insert_km->bind_param("isi", $coche_id, $fecha_actual, $kilometraje);
                $stmt_insert_km->execute();
                $stmt_insert_km->close();
            }
            $stmt_check->close();
        }
        
        $cambios_realizados++;
        error_log("Cambio #" . ($index + 1) . " completado exitosamente");
    }
    
    // Si llegamos hasta aquí, todos los cambios fueron exitosos
    $conn->commit();
    error_log("Transacción completada exitosamente. $cambios_realizados cambios realizados.");
    
    echo json_encode([
        'exito' => true, 
        'mensaje' => "Se realizaron $cambios_realizados cambios de cubiertas correctamente."
    ]);
    
} catch (mysqli_sql_exception $e) {
    // Registrar el error específico de MySQL en el log
    error_log("Error SQL en cambiar_cubiertas_multiple.php: " . $e->getMessage());
    error_log("Código de error SQL: " . $e->getCode());
    
    // Revertir cambios en caso de error
    $conn->rollback();
    echo json_encode(['exito' => false, 'mensaje' => 'Error SQL: ' . $e->getMessage()]);
    
} catch (Exception $e) {
    // Registrar el error general en el log
    error_log("Excepción en cambiar_cubiertas_multiple.php: " . $e->getMessage());
    
    // Revertir cambios en caso de error
    $conn->rollback();
    echo json_encode(['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>