<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'Database.php';

$db = new Database();
$conn = $db->conectar();

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'aluno') {
    echo json_encode(["sucesso" => false, "mensagem" => "Apenas alunos logam desempenho."]);
    exit;
}

$aluno_id = intval($_SESSION['usuario']['id']);
$aluno_nome = $conn->real_escape_string($_SESSION['usuario']['nome']);
$jogo_id = intval($_POST['jogo_id'] ?? 1);
$fase = intval($_POST['fase'] ?? 1);
$acertos = intval($_POST['acertos'] ?? 0);
$erros = intval($_POST['erros'] ?? 0);

// Pontuação: 10 pts por acerto
$pontuacao = $acertos * 10;

// Verifica o progresso anterior do aluno antes de salvar esta atividade
$resAntes = $conn->query("SELECT progresso, responsavel_id, turma_id FROM alunos WHERE usuario_id = $aluno_id");
$dadosAluno = $resAntes->fetch_assoc();
$progressoAnterior = intval($dadosAluno['progresso'] ?? 0);
$responsavel_id = intval($dadosAluno['responsavel_id'] ?? 0);
$turma_id = intval($dadosAluno['turma_id'] ?? 0);

// Insere o registro de desempenho
$stmt = $conn->prepare("INSERT INTO desempenho (aluno_id, jogo_id, fase, pontuacao, acertos, erros, concluida) VALUES (?, ?, ?, ?, ?, ?, 1)");
$stmt->bind_param("iiiiii", $aluno_id, $jogo_id, $fase, $pontuacao, $acertos, $erros);

if ($stmt->execute()) {
    // Atualiza pontuação total e progresso geral (20 fases no total: 10 de Letras + 10 de Sílabas)
    $sqlUpdate = "
        UPDATE alunos 
        SET pontuacao = (SELECT COALESCE(SUM(pontuacao), 0) FROM desempenho WHERE aluno_id = $aluno_id),
            progresso = LEAST(100, ROUND((SELECT COUNT(DISTINCT CONCAT(jogo_id, '-', fase)) FROM desempenho WHERE aluno_id = $aluno_id) * 100 / 20))
        WHERE usuario_id = $aluno_id
    ";
    $conn->query($sqlUpdate);

    // Consulta o novo progresso atualizado
    $resDepois = $conn->query("SELECT progresso FROM alunos WHERE usuario_id = $aluno_id");
    $novoProgresso = intval($resDepois->fetch_assoc()['progresso'] ?? 0);

    // Busca o nome do jogo
    $resJogo = $conn->query("SELECT titulo FROM jogos WHERE id = $jogo_id");
    $nomeJogo = $resJogo->fetch_assoc()['titulo'] ?? "Jogo";

    // 1. Notificação para o RESPONSÁVEL se o aluno concluir a fase SEM ERROS
    if ($erros === 0 && $responsavel_id > 0) {
        $msgSemErros = $conn->real_escape_string("⭐ Parabéns! $aluno_nome concluiu a Fase $fase ($nomeJogo) sem nenhum erro!");
        $conn->query("INSERT INTO notificacoes (usuario_id, mensagem) VALUES ($responsavel_id, '$msgSemErros')");
    }

    // 2. Notificação de 100% DE PROGRESSO para RESPONSÁVEL e PROFESSORES DA TURMA
    if ($novoProgresso >= 100 && $progressoAnterior < 100) {
        $msg100 = $conn->real_escape_string("🏆 Conquista! O(A) aluno(a) $aluno_nome concluiu 100% do progresso geral no EducaLetra!");

        if ($responsavel_id > 0) {
            $conn->query("INSERT INTO notificacoes (usuario_id, mensagem) VALUES ($responsavel_id, '$msg100')");
        }

        if ($turma_id > 0) {
            $resProfs = $conn->query("SELECT DISTINCT professor_id FROM professor_turmas WHERE turma_id = $turma_id");
            while ($prof = $resProfs->fetch_assoc()) {
                $prof_id = intval($prof['professor_id']);
                $conn->query("INSERT INTO notificacoes (usuario_id, mensagem) VALUES ($prof_id, '$msg100')");
            }
        }
    }

    echo json_encode(["sucesso" => true, "pontuacao_ganha" => $pontuacao]);
} else {
    echo json_encode(["sucesso" => false, "mensagem" => $conn->error]);
}
?>