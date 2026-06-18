<?php
// =========================================================================
// 1. CAPA DE DATOS - MÓDULO DE ANALÍTICA ESTADÍSTICA GENERAL
// =========================================================================

// Usamos getenv para evitar que GitHub detecte credenciales sensibles
$host    = getenv('DB_HOST') ?: 'localhost';
$db      = getenv('DB_NAME') ?: 'compuedu'; 
$user    = getenv('DB_USER') ?: 'root';
$pass    = getenv('DB_PASSWORD') ?: '';
$port    = getenv('DB_PORT') ?: '3306';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

// Configuración segura para PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Requerido si tu DB está en Aiven u otro servicio en la nube
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, 
];

// Variables Analíticas Inicializadas
$totalInscripciones   = 0;
$totalAlertasCreadas  = 0;
$areaMayorDemanda     = "Ninguna";
$areaMenorDemanda     = "Ninguna";
$areasHuerfanasCount  = 0;
$interesesGlobalesData = [];
$areasHuerfanasData    = [];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // MÉTRICA 1: Volúmenes Generales
    $stmt1 = $pdo->query("SELECT COUNT(*) as total FROM inscripcion");
    $totalInscripciones = $stmt1->fetch()['total'] ?? 0;

    $stmt2 = $pdo->query("SELECT COUNT(*) as total FROM filtros_estudiante");
    $totalAlertasCreadas = $stmt2->fetch()['total'] ?? 0;

    // MÉTRICA 2: Análisis de Extremos
    $queryExtremos = "SELECT c.titulo as area, COUNT(i.id) as total 
                      FROM inscripcion i
                      INNER JOIN convocatorias c ON i.convocatoria_id = c.id
                      GROUP BY c.titulo 
                      ORDER BY total DESC";
    
    $interesesGlobalesData = $pdo->query($queryExtremos)->fetchAll();

    if (!empty($interesesGlobalesData)) {
        $areaMayorDemanda = $interesesGlobalesData[0]['area'] . " (" . $interesesGlobalesData[0]['total'] . ")";
        $ultimo = end($interesesGlobalesData);
        $areaMenorDemanda = $ultimo['area'] . " (" . $ultimo['total'] . ")";
    }

    // MÉTRICA 3: Mapeo de Áreas Huérfanas
    $queryCountHuerfanas = "SELECT COUNT(DISTINCT f.palabra_clave) as total 
                            FROM filtros_estudiante f 
                            WHERE NOT EXISTS (
                                SELECT 1 FROM convocatorias c 
                                WHERE LOWER(c.titulo) LIKE CONCAT('%', LOWER(f.palabra_clave), '%') 
                                AND c.estado = 'ACTIVA'
                            )";
    $areasHuerfanasCount = $pdo->query($queryCountHuerfanas)->fetch()['total'] ?? 0;

    $queryTopHuerfanas = "SELECT f.palabra_clave as area, COUNT(*) as total 
                          FROM filtros_estudiante f 
                          WHERE NOT EXISTS (
                              SELECT 1 FROM convocatorias c 
                              WHERE LOWER(c.titulo) LIKE CONCAT('%', LOWER(f.palabra_clave), '%') 
                              AND c.estado = 'ACTIVA'
                          )
                          GROUP BY f.palabra_clave 
                          ORDER BY total DESC 
                          LIMIT 5";
    $areasHuerfanasData = $pdo->query($queryTopHuerfanas)->fetchAll();

} catch (PDOException $e) {
    error_log("Error de BD: " . $e->getMessage());
    $errorBD = "No se pudo conectar a la base de datos.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis Estadístico General - Compuedu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; }
        .metric-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: transform 0.2s; }
        .metric-card:hover { transform: translateY(-3px); }
        .chart-container { position: relative; width: 100%; height: 300px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <button onclick="window.history.back();" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-arrow-left me-1"></i> Volver al Panel
        </button>
        <span class="text-muted small">Módulo Integrado de Analítica Institucional</span>
    </div>

    <div class="mb-5">
        <h2 class="fw-bold text-dark mb-1">
            <i class="fas fa-chart-pie text-primary me-2"></i>Macromódulo de Analítica Vocacional e Intereses
        </h2>
        <p class="text-muted mb-0">Estudio descriptivo global de demandas absolutas, desintereses y brechas críticas del mercado estudiantil.</p>
        <?php if(isset($errorBD)): ?>
            <div class="alert alert-danger mt-3" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> Excepción de Base de Datos: <?php echo $errorBD; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="card metric-card bg-white p-4 border-start border-primary border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold mb-1">Área Mayor Demanda</h6>
                        <h5 class="fw-bold mb-0 text-primary text-truncate" style="max-width: 180px;" title="<?php echo $areaMayorDemanda; ?>">
                            <?php echo $areaMayorDemanda; ?>
                        </h5>
                    </div>
                    <div class="bg-primary-subtle p-3 rounded-circle text-primary"><i class="fas fa-chart-line fa-lg"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card bg-white p-4 border-start border-info border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold mb-1">Área Menor Demanda</h6>
                        <h5 class="fw-bold mb-0 text-info text-truncate" style="max-width: 180px;" title="<?php echo $areaMenorDemanda; ?>">
                            <?php echo $areaMenorDemanda; ?>
                        </h5>
                    </div>
                    <div class="bg-info-subtle p-3 rounded-circle text-info"><i class="fas fa-chart-line fa-flip-vertical fa-lg"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card bg-white p-4 border-start border-danger border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold mb-1">Focos Huérfanos</h6>
                        <h3 class="fw-bold mb-0 text-danger"><?php echo $areasHuerfanasCount; ?></h3>
                    </div>
                    <div class="bg-danger-subtle p-3 rounded-circle text-danger"><i class="fas fa-unlink fa-lg"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card bg-white p-4 border-start border-warning border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold mb-1">Filtros Activos</h6>
                        <h3 class="fw-bold mb-0 text-warning"><?php echo $totalAlertasCreadas; ?></h3>
                    </div>
                    <div class="bg-warning-subtle p-3 rounded-circle text-warning"><i class="fas fa-bell fa-lg"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card p-4 border-0 shadow-sm bg-white h-100">
                <h5 class="fw-bold text-dark mb-1">Distribución General de Demandas</h5>
                <p class="text-muted small mb-4">Volumen total de inscripciones por carrera/título dentro del sistema.</p>
                <div class="chart-container">
                    <canvas id="chartGlobalIntereses"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-4 border-0 shadow-sm bg-white h-100">
                <h5 class="fw-bold text-dark mb-1">Brecha Crítica: Focos Huérfanos</h5>
                <p class="text-muted small mb-4">Palabras clave en 'filtros_estudiante' que carecen de convocatorias activas.</p>
                <div class="chart-container">
                    <canvas id="chartHuerfanasCriticas"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Inyección de los arrays asociativos de PHP a JavaScript
    const datosGlobales  = <?php echo json_encode($interesesGlobalesData); ?>;
    const datosHuerfanas = <?php echo json_encode($areasHuerfanasData); ?>;

    // 1. Inicialización del Gráfico de Dona (Comportamiento General)
    const ctxGlobal = document.getElementById('chartGlobalIntereses').getContext('2d');
    new Chart(ctxGlobal, {
        type: 'doughnut',
        data: {
            labels: datosGlobales.map(item => item.area || 'No especificado'),
            datasets: [{
                data: datosGlobales.map(item => item.total),
                backgroundColor: ['#0d6efd', '#198754', '#0dcaf0', '#ffc107', '#6c757d']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    position: 'bottom',
                    labels: { boxWidth: 11, font: { family: 'Poppins' } } 
                } 
            }
        }
    });

    // 2. Inicialización del Gráfico de Barras (Áreas Huérfanas de Alta Alerta)
    const ctxHuerfanas = document.getElementById('chartHuerfanasCriticas').getContext('2d');
    new Chart(ctxHuerfanas, {
        type: 'bar',
        data: {
            labels: datosHuerfanas.map(item => item.area),
            datasets: [{
                label: 'Alumnos esperando convocatorias',
                data: datosHuerfanas.map(item => item.total),
                backgroundColor: '#dc3545',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true, 
                    ticks: { stepSize: 1, font: { family: 'Poppins' } } 
                },
                x: {
                    ticks: { font: { family: 'Poppins' } }
                }
            },
            plugins: {
                legend: {
                    labels: { font: { family: 'Poppins' } }
                }
            }
        }
    });
});
</script>
</body>
</html>