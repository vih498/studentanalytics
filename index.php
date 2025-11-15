<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentanalytics/index.php'));
$PAGE->set_title('Painel de Análise dos Estudantes');
$PAGE->set_heading('📊 Moodle Student Analytics');

// Incluindo CSS do plugin
$PAGE->requires->css('/local/studentanalytics/style.css');

echo $OUTPUT->header();

// Boas-vindas
echo "<h2>Bem-vindo ao painel de análise de dados dos estudantes!</h2>";

// ------------------------------
// Upload de CSV
if (isset($_POST['upload_csv']) && isset($_FILES['student_csv'])) {
    $uploadDir = __DIR__ . '/upload/';
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

    $uploadFile = $uploadDir . basename($_FILES['student_csv']['name']);

    if (move_uploaded_file($_FILES['student_csv']['tmp_name'], $uploadFile)) {
        echo "<p>CSV enviado com sucesso!</p>";

        // Executa o script Python passando o caminho do CSV
        $pythonPath = 'C:/Python310/python.exe'; // altere para seu Python
        $scriptPath = __DIR__ . '/python/predict_risk.py';
        $command = escapeshellcmd("$pythonPath $scriptPath $uploadFile");
        $output = shell_exec($command);

        // Lê o CSV de saída
        $riskCsv = __DIR__ . '/python/student_risk_predictions.csv';
        if (file_exists($riskCsv)) {
            $riskData = array_map('str_getcsv', file($riskCsv));
        }
    } else {
        echo "<p>Erro ao enviar CSV.</p>";
    }
}

// Formulário de upload
?>
<h3>📂 Upload de CSV de alunos</h3>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="student_csv" accept=".csv" required>
    <input type="submit" name="upload_csv" value="Enviar CSV">
</form>

<?php
// ------------------------------
// 1️⃣ Ranking dos 10 alunos mais ativos
$topstudents = local_studentanalytics_get_access_counts();
$names = [];
$accesscounts = [];

if ($topstudents) {
    foreach ($topstudents as $student) {
        $names[] = $student->firstname . ' ' . $student->lastname;
        $accesscounts[] = $student->accesscount;
    }
}

// ------------------------------
// 2️⃣ Tempo médio de acesso por aluno
$avgtime = local_studentanalytics_get_average_access_time();

// ------------------------------
// 3️⃣ Participação em fóruns (última semana)
$forumpart = local_studentanalytics_get_forum_participation();
$forumnames = [];
$forumposts = [];
if ($forumpart) {
    foreach ($forumpart as $student) {
        $forumnames[] = $student->firstname . ' ' . $student->lastname;
        $forumposts[] = $student->posts;
    }
}

// ------------------------------
// 4️⃣ Entregas de atividades (última semana)
$submissions = local_studentanalytics_get_submission_counts();
$subnames = [];
$subcounts = [];
if ($submissions) {
    foreach ($submissions as $student) {
        $subnames[] = $student->firstname . ' ' . $student->lastname;
        $subcounts[] = $student->submissions;
    }
}
?>

<h3>📥 Baixar modelo de CSV</h3>
<a href="<?php echo $CFG->wwwroot . '/local/studentanalytics/student_csv_template.csv'; ?>" download>
    <button type="button">Baixar Modelo CSV</button>
</a>

<div class="cards-container">
    <div class="card">
        <h3>👥 Top 10 alunos mais ativos</h3>
        <canvas id="accessChart"></canvas>
    </div>

    <div class="card">
        <h3>⏱️ Tempo médio de acesso (última semana)</h3>
        <p><strong><?php echo $avgtime > 0 ? $avgtime . " minutos" : "Sem dados suficientes nesta semana."; ?></strong></p>
    </div>

    <div class="card">
        <h3>💬 Participação em fóruns (última semana)</h3>
        <canvas id="forumChart"></canvas>
    </div>

    <div class="card">
        <h3>📝 Entregas de atividades (última semana)</h3>
        <canvas id="submissionChart"></canvas>
    </div>

    <div class="card">
        <h3>📈 Correlação: Engajamento vs Notas</h3>
        <canvas id="correlationChart"></canvas>
    </div>
</div>

<?php
// ------------------------------
// Tabela de risco de evasão
if (!empty($riskData)) {
    echo "<h3>⚠️ Risco de evasão</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Aluno</th><th>Risco</th></tr>";
    foreach ($riskData as $row) {
        echo "<tr><td>{$row[0]}</td><td>{$row[3]}</td></tr>"; // assume coluna 0 = nome, coluna 3 = risco
    }
    echo "</table>";
}
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de Top 10 alunos mais ativos
const ctx = document.getElementById('accessChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($names); ?>,
        datasets: [{
            label: 'Número de acessos',
            data: <?php echo json_encode($accesscounts); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Acessos' } },
            x: { title: { display: true, text: 'Alunos' } }
        }
    }
});

// Gráfico de participação em fóruns
const forumCtx = document.getElementById('forumChart').getContext('2d');
new Chart(forumCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($forumnames); ?>,
        datasets: [{
            label: 'Posts no fórum',
            data: <?php echo json_encode($forumposts); ?>,
            backgroundColor: 'rgba(255, 159, 64, 0.6)',
            borderColor: 'rgba(255, 159, 64, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Posts' } },
            x: { title: { display: true, text: 'Alunos' } }
        }
    }
});

// Gráfico de entregas de atividades
const submissionCtx = document.getElementById('submissionChart').getContext('2d');
new Chart(submissionCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($subnames); ?>,
        datasets: [{
            label: 'Entregas de atividades',
            data: <?php echo json_encode($subcounts); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.6)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Entregas' } },
            x: { title: { display: true, text: 'Alunos' } }
        }
    }
});

// Placeholder: Gráfico de correlação (mais tarde pode ser calculado com Python/JS)
const correlationCtx = document.getElementById('correlationChart').getContext('2d');
new Chart(correlationCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($names); ?>,
        datasets: [{
            label: 'Engajamento vs Notas',
            data: <?php echo json_encode($accesscounts); ?>,
            backgroundColor: 'rgba(153, 102, 255, 0.2)',
            borderColor: 'rgba(153, 102, 255, 1)',
            borderWidth: 2,
            fill: true,
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Notas / Engajamento' } },
            x: { title: { display: true, text: 'Alunos' } }
        }
    }
});
</script>

<?php
echo $OUTPUT->footer();
?>