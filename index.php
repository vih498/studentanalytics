<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentanalytics/index.php'));
$PAGE->set_title('Painel de Análise dos Estudantes');
$PAGE->set_heading('📊 Moodle Student Analytics');
$PAGE->requires->js_call_amd('local_studentanalytics/graficos', 'init');
$PAGE->requires->js_call_amd('local_studentanalytics/graficos', 'init_realtime');
$PAGE->requires->js_call_amd('local_studentanalytics/graficos', 'init_realtime', array($courseid));

// 💡 Chama o novo arquivo CSS
$PAGE->requires->css(new moodle_url('/local/studentanalytics/style.css'));

echo $OUTPUT->header();

echo "<h2>Bem-vindo ao painel de análise de dados dos estudantes!</h2>";

/* ============================================================
    UPLOAD DE CSV
    ============================================================ */

if (isset($_POST['upload_csv']) && isset($_FILES['student_csv'])) {
    $uploadDir = __DIR__ . '/upload/';
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

    $uploadFile = $uploadDir . basename($_FILES['student_csv']['name']);

    if (move_uploaded_file($_FILES['student_csv']['tmp_name'], $uploadFile)) {
        echo "<p class='notification ok' style='color:green;'>CSV enviado com sucesso!</p>"; // Classe ajustada

        // Executa Python (opcional)
        $pythonPath = 'C:/Python310/python.exe';
        $scriptPath = __DIR__ . '/python/predict_risk.py';
        $command = escapeshellcmd("$pythonPath $scriptPath $uploadFile");
        $output = shell_exec($command);

        // Ler CSV de risco (opcional)
        $riskCsv = __DIR__ . '/python/student_risk_predictions.csv';
        $riskData = [];
        if (file_exists($riskCsv)) {
            $riskData = array_map('str_getcsv', file($riskCsv));
        }

    } else {
        echo "<p class='notification error' style='color:red;'>Erro ao enviar CSV.</p>"; // Classe ajustada
    }
} else {
    // Inicializa $riskData se não houve upload
    $riskData = [];
}
?>

<h3>📂 Upload de CSV de alunos</h3>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="student_csv" accept=".csv" required>
    <input type="submit" name="upload_csv" value="Enviar CSV">
</form>

<?php
/* ============================================================
    LEITURA DO CSV — PRIORIDADE TOTAL
    ============================================================ */

$students_csv = [];
$csvDir = __DIR__ . '/upload/';
$csvFiles = glob($csvDir . "*.csv");
$latestCsv = !empty($csvFiles) ? end($csvFiles) : null;

if ($latestCsv && file_exists($latestCsv)) {

    if (($handle = fopen($latestCsv, "r")) !== false) {

        $firstLine = fgets($handle);
        $separator = strpos($firstLine, ";") !== false ? ";" : ",";

        rewind($handle);

        $header = fgetcsv($handle, 0, $separator);

        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            if (count($row) == count($header)) {
                $students_csv[] = array_combine($header, $row);
            }
        }

        fclose($handle);
    }
}

/* ============================================================
   CÁLCULOS PARA OS CARDS
   ============================================================ */

$total_alunos = count($students_csv);
$sum_grades = 0;
$alunos_risco = 0; // Você pode ajustar esta lógica para usar $riskData

if ($total_alunos > 0) {
    
    // Cálculo da Média das Notas
    foreach ($students_csv as $st) {
        if (isset($st['average_grade']) && is_numeric($st['average_grade'])) {
            $sum_grades += (float)$st['average_grade'];
        }
    }
    $media_notas = $sum_grades / $total_alunos;

    // A lógica de contagem de risco deve ser inserida aqui
    // Exemplo Simples (se o modelo Python funcionar):
    // $alunos_risco = count($riskData) > 0 ? count($riskData) - 1 : 0; // Subtrai o cabeçalho

} else {
    $media_notas = 0;
}

/* ============================================================
   IMPRESSÃO DOS CARDS (USANDO AS CLASSES DO CSS)
   ============================================================ */
?>

<h3>📈 Resumo da Análise</h3>
<div class="cards-container">

    <div class="card">
        <h3>Total de Alunos Importados</h3>
        <p style="font-size: 2.5rem; font-weight: bold; color: #3498db;"><?php echo $total_alunos; ?></p>
        <p>Dados extraídos do último CSV.</p>
    </div> 

    <div class="card">
        <h3>Média Geral de Notas</h3>
        <p style="font-size: 2.5rem; font-weight: bold; color: #2ecc71;"><?php echo number_format($media_notas, 2); ?></p>
        <p>Média geral da coluna "average_grade".</p>
    </div>

    <div class="card">
        <h3>Alunos Classificados com Risco</h3>
        <p style="font-size: 2.5rem; font-weight: bold; color: #e74c3c;"><?php echo $alunos_risco; ?></p>
        <p>Este dado requer o CSV de predição do Python.</p>
    </div> 

</div>
<?php
/* ============================================================
    BAIXAR MODELO
    ============================================================ */
?>
<h3>📥 Baixar modelo de CSV</h3>
<a href="<?php echo $CFG->wwwroot . '/local/studentanalytics/student_csv_template.csv'; ?>" download>
    <button type="button" class="btn">Baixar Modelo CSV</button>
</a>

<?php
/* ============================================================
    TABELA DO CSV
    ============================================================ */

if (!empty($students_csv)) {
    echo "<h3>📄 Dados Importados do CSV</h3>";
    // A classe 'generaltable' será estilizada no CSS
    echo "<table class='generaltable'>
                <tr>
                    <th>Aluno</th>
                    <th>Ações</th>
                </tr>";

    foreach ($students_csv as $st) {
        $json = htmlspecialchars(json_encode($st), ENT_QUOTES, 'UTF-8');

        echo "<tr>
                    <td>{$st['firstname']} {$st['lastname']}</td>
                    <td><button class='btn sa-details' data-user=\"$json\">Ver Detalhes</button></td>
                </tr>";
    }

    echo "</table>";
} else {
    echo "<p>Nenhum CSV encontrado. Envie um CSV acima.</p>";
}
?>

<div id="sa-modal" class="modal" tabindex="-1" style="display:none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Detalhes do aluno</h5>
                <button class="close" onclick="document.getElementById('sa-modal').style.display='none'">&times;</button>
            </div>
            <div id="sa-modal-body" class="modal-body">Carregando...</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
/* =====================================================
    MODAL — DETALHES DO CSV
====================================================== */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sa-details').forEach(btn => {
        btn.addEventListener('click', () => {
            let data = JSON.parse(btn.dataset.user);
            let html = `
                <p><strong>Aluno:</strong> ${data.firstname} ${data.lastname}</p>
                <p><strong>Acessos:</strong> ${data.total_access}</p>
                <p><strong>Posts no fórum:</strong> ${data.forum_posts}</p>
                <p><strong>Entregas:</strong> ${data.assignments_submitted}</p>
                <p><strong>Média:</strong> ${data.average_grade}</p>
            `;
            // Adicione mais campos aqui se seu CSV tiver dados de risco
            document.getElementById('sa-modal-body').innerHTML = html;
            document.getElementById('sa-modal').style.display = 'flex'; // Mudado para 'flex' para usar a centralização do CSS
        });
    });
});
</script>

<?php
echo $OUTPUT->footer();
?>

