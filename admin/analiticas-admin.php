<?php
// =========================================================================
// 1. LÓGICA DE BACKEND: CONEXIÓN Y PROCESAMIENTO DE DATOS (Módulo PHP)
// =========================================================================

$totalInscripciones = 0;
$categoriaLider = 'Sin datos';
$interesesGlobales = [];

// Variables de entorno (se configuran en el panel de Render)
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_NAME') ?: 'compuedu';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

try {
    // CORRECCIÓN: DSN completo con puerto y opciones de seguridad para Aiven
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // Necesario para conexiones a Aiven
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    // Consulta 1: Total histórico de inscripciones
    $stmtInscripciones = $pdo->query("SELECT COUNT(*) AS total FROM inscripcion");
    if ($stmtInscripciones) {
        $totalInscripciones = $stmtInscripciones->fetch()->total;
    }

    // Consulta 2: Agrupar y contar las categorías de interés
    $sqlIntereses = "SELECT categoria, COUNT(*) AS cantidad 
                     FROM filtros_estudiante 
                     GROUP BY categoria 
                     ORDER BY cantidad DESC";

    $stmtIntereses = $pdo->query($sqlIntereses);
    if ($stmtIntereses) {
        $interesesGlobales = $stmtIntereses->fetchAll();

        if (!empty($interesesGlobales)) {
            $categoriaLider = $interesesGlobales[0]->categoria;
        }
    }

} catch (PDOException $e) {
    // Registro de error para depuración sin exponer datos sensibles
    error_log("Error de conexión BD: " . $e->getMessage());
    $categoriaLider = 'Error de conexión';
}
?>

<!-- ========================================================================= -->
<!-- 2. INTERFAZ DE USUARIO: VISTA DE ANALÍTICAS AVANZADAS                      -->
<!-- ========================================================================= -->
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compuedu Admin - Analíticas Avanzadas</title>
    <!-- Frameworks Visuales -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8f9fa;
        }

        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            background-color: #ffffff;
            transition: transform 0.2s;
        }

        .card-custom:hover {
            transform: translateY(-2px);
        }

        .btn-return {
            background-color: #ffffff;
            color: #4f46e5;
            border: 1px solid #e5e7eb;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.2s;
        }

        .btn-return:hover {
            background-color: #f3f4f6;
            color: #4338ca;
            border-color: #d1d5db;
        }

        .chart-container {
            position: relative;
            height: 260px;
            width: 100%;
        }
    </style>
</head>

<body>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button
                onclick="if(window.history.length > 1) { window.history.back(); } else { window.location.href='/institucion/dashboard'; }"
                class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-1"></i> Volver al Panel
            </button>
            <span class="text-muted small">Módulo Integrado de Analítica Institucional</span>
        </div>

        <!-- Tarjetas Estadísticas -->
        <div class="row g-4 mb-4">
            <!-- Tarjeta: Total Postulaciones -->
            <div class="col-md-6">
                <div class="card card-custom p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase tracking-wider">Postulaciones
                                Totales</span>
                            <h2 class="display-6 fw-bold text-dark mt-1 mb-0"><?php echo $totalInscripciones; ?></h2>
                        </div>
                        <div class="p-3 bg-primary bg-opacity-10 rounded-circle text-primary">
                            <i class="fas fa-user-grad fa-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta: Mayor Interés -->
            <div class="col-md-6">
                <div class="card card-custom p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase tracking-wider">Área de Mayor
                                Interés</span>
                            <h2 class="h3 fw-bold text-dark mt-2 mb-0"><?php echo htmlspecialchars($categoriaLider); ?>
                            </h2>
                        </div>
                        <div class="p-3 bg-success bg-opacity-10 rounded-circle text-success">
                            <i class="fas fa-fire fa-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de Gráficos Estadísticos -->
        <div class="row g-4 mb-5">
            <!-- Gráfico de Barras: Distribución de Intereses -->
            <div class="col-lg-8">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-dark mb-4">
                        <i class="fas fa-chart-bar me-2 text-muted"></i>Distribución de Demanda por Categoría
                    </h5>
                    <div class="chart-container">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Pastel: Participación -->
            <div class="col-lg-4">
                <div class="card card-custom p-4">
                    <h5 class="fw-bold text-dark mb-4">
                        <i class="fas fa-chart-pie me-2 text-muted"></i>Participación Impacto
                    </h5>
                    <div class="chart-container">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Intereses por Categoría -->
        <div class="card card-custom overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fas fa-list-ol me-2 text-muted"></i>Métricas Consolidadas
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-4">Categoría Académica</th>
                            <th class="text-center">Alumnos Interesados (Alertas Activas)</th>
                            <th class="pe-4 text-end">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($interesesGlobales)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">No hay filtros registrados por
                                    estudiantes aún.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($interesesGlobales as $interes): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">
                                        <?php echo htmlspecialchars($interes->categoria); ?>
                                    </td>
                                    <td class="text-center fw-bold text-primary">
                                        <?php echo $interes->cantidad; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <span
                                            class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-20 px-3 py-1.5 rounded-pill">
                                            <i class="fas fa-chart-line me-1"></i> Monitoreado
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Scripts de Gráficos e Interactividad -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Mapeamos de manera segura el arreglo de PHP a formato JSON compatible con JavaScript
        const datosPHP = <?php echo json_encode($interesesGlobales); ?>;

        // Procesamos etiquetas (categorías) y datos (cantidades)
        const labels = datosPHP.map(item => item.categoria);
        const data = datosPHP.map(item => item.cantidad);

        // Si la base de datos está vacía, mostramos estados vacíos visuales
        const finalLabels = labels.length ? labels : ['Sin datos'];
        const finalData = data.length ? data : [0];

        // Paleta de colores moderna para Compuedu
        const backgroundColors = [
            'rgba(79, 70, 229, 0.85)',  // Indigo
            'rgba(16, 185, 129, 0.85)', // Esmeralda
            'rgba(245, 158, 11, 0.85)', // Ámbar
            'rgba(239, 68, 68, 0.85)',  // Rojo
            'rgba(6, 182, 212, 0.85)'   // Cian
        ];

        const borderColors = [
            '#4f46e5', '#10b981', '#f59e0b', '#ef6868', '#06b6d4'
        ];

        // 1. Configuración Gráfico de Barras
        const ctxBar = document.getElementById('barChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: finalLabels,
                datasets: [{
                    label: 'Cantidad de Alumnos interesados',
                    data: finalData,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });

        // 2. Configuración Gráfico de Pastel (Pie)
        const ctxPie = document.getElementById('pieChart').getContext('2d');
        new Chart(ctxPie, {
            type: 'pie',
            data: {
                labels: finalLabels,
                datasets: [{
                    data: finalData,
                    backgroundColor: backgroundColors,
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { family: 'Plus Jakarta Sans' } }
                    }
                }
            }
        });
    </script>
</body>

</html>