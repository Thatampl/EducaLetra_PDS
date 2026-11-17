<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'Database.php';

$db = new Database();
$conn = $db->conectar();

if (!isset($_SESSION['usuario'])) {
    echo json_encode(["autenticado" => false]);
    exit;
}

$usuario = $_SESSION['usuario'];
$uid = intval($usuario['id']);
$tipo = $usuario['tipo_usuario'];
$escola_id = intval($usuario['escola_id'] ?? 1);

// Notificações
$notificacoes = [];
$resNotif = $conn->query("SELECT id, mensagem, data_envio FROM notificacoes WHERE usuario_id = $uid ORDER BY data_envio DESC");
while ($row = $resNotif->fetch_assoc()) {
    $notificacoes[] = $row;
}

// Materiais Didáticos
$materiais = [];
$resMat = $conn->query("SELECT id, titulo, descricao, conteudo FROM materiais_didaticos WHERE selecionado_escola = 1 AND (escola_id = $escola_id OR escola_id IS NULL)");
while ($row = $resMat->fetch_assoc()) {
    $materiais[] = $row;
}

// Dados do Perfil por Tipo de Usuário
$dadosPerfil = [];

if ($tipo === 'aluno') {
    $sql = "SELECT a.cpf_aluno, a.pontuacao, a.progresso, t.nome AS turma, t.turno,
            (SELECT COUNT(DISTINCT CONCAT(d.jogo_id, '-', d.fase)) FROM desempenho d WHERE d.aluno_id = a.usuario_id AND d.concluida = 1) AS fases_concluidas
            FROM alunos a
            LEFT JOIN turmas t ON a.turma_id = t.id
            WHERE a.usuario_id = $uid";
    $dadosPerfil['detalhes'] = $conn->query($sql)->fetch_assoc();

    // Progresso individual por jogo (fases concluídas e fases sem erros para estrelas)
    $progressoJogos = [
        1 => ["max_fase" => 0, "sem_erros" => []],
        2 => ["max_fase" => 0, "sem_erros" => []]
    ];

    $resFases = $conn->query("SELECT jogo_id, fase, MIN(erros) AS menor_erro FROM desempenho WHERE aluno_id = $uid AND concluida = 1 GROUP BY jogo_id, fase");
    while ($f = $resFases->fetch_assoc()) {
        $jid = intval($f['jogo_id']);
        $numFase = intval($f['fase']);
        if (isset($progressoJogos[$jid])) {
            if ($numFase > $progressoJogos[$jid]['max_fase']) {
                $progressoJogos[$jid]['max_fase'] = $numFase;
            }
            if (intval($f['menor_erro']) === 0) {
                $progressoJogos[$jid]['sem_erros'][] = $numFase;
            }
        }
    }
    $dadosPerfil['progresso_jogos'] = $progressoJogos;

    $historico = [];
    $resHist = $conn->query("SELECT j.titulo, d.fase, d.pontuacao, d.acertos, d.erros FROM desempenho d JOIN jogos j ON d.jogo_id = j.id WHERE d.aluno_id = $uid ORDER BY d.id DESC LIMIT 10");
    while ($h = $resHist->fetch_assoc()) {
        $historico[] = $h;
    }
    $dadosPerfil['historico'] = $historico;

} elseif ($tipo === 'responsavel') {
    $dadosPerfil['detalhes'] = $conn->query("SELECT cpf_responsavel FROM responsaveis WHERE usuario_id = $uid")->fetch_assoc();

    $filhos = [];
    $sqlFilhos = "SELECT u.id, u.nome, u.codigo_identificacao, a.pontuacao, a.progresso, t.nome AS turma, t.turno,
                  (SELECT COUNT(DISTINCT CONCAT(d.jogo_id, '-', d.fase)) FROM desempenho d WHERE d.aluno_id = a.usuario_id AND d.concluida = 1) AS fases_concluidas
                  FROM alunos a
                  JOIN usuarios u ON a.usuario_id = u.id
                  LEFT JOIN turmas t ON a.turma_id = t.id
                  WHERE a.responsavel_id = $uid";
    $resFilhos = $conn->query($sqlFilhos);
    while ($f = $resFilhos->fetch_assoc()) {
        $filhos[] = $f;
    }
    $dadosPerfil['alunos_vinculados'] = $filhos;

} elseif ($tipo === 'professor') {
    $dadosPerfil['detalhes'] = $conn->query("SELECT cpf_professor FROM professores WHERE usuario_id = $uid")->fetch_assoc();

    $turmas = [];
    $resTurmas = $conn->query("SELECT t.id, t.nome, t.turno FROM professor_turmas pt JOIN turmas t ON pt.turma_id = t.id WHERE pt.professor_id = $uid");
    while ($t = $resTurmas->fetch_assoc()) {
        $turmas[] = $t;
    }
    $dadosPerfil['turmas'] = $turmas;

    $filtroNome = $conn->real_escape_string($_GET['nome'] ?? '');
    $filtroTurma = intval($_GET['turma_id'] ?? 0);
    $filtroTurno = $conn->real_escape_string($_GET['turno'] ?? '');

    $where = "pt.professor_id = $uid";
    if ($filtroNome !== '') $where .= " AND u.nome LIKE '%$filtroNome%'";
    if ($filtroTurma > 0) $where .= " AND t.id = $filtroTurma";
    if ($filtroTurno !== '') $where .= " AND t.turno = '$filtroTurno'";

    $alunos = [];
    $sqlAlunos = "SELECT u.id, u.nome, u.codigo_identificacao, a.pontuacao, a.progresso, t.nome AS turma, t.turno,
                  (SELECT COUNT(DISTINCT CONCAT(d.jogo_id, '-', d.fase)) FROM desempenho d WHERE d.aluno_id = a.usuario_id AND d.concluida = 1) AS fases_concluidas
                  FROM professor_turmas pt
                  JOIN turmas t ON pt.turma_id = t.id
                  JOIN alunos a ON a.turma_id = t.id
                  JOIN usuarios u ON a.usuario_id = u.id
                  WHERE $where
                  ORDER BY t.nome, u.nome";
    $resAlunos = $conn->query($sqlAlunos);
    while ($al = $resAlunos->fetch_assoc()) {
        $alunos[] = $al;
    }
    $dadosPerfil['alunos_turmas'] = $alunos;
}

echo json_encode([
    "autenticado" => true,
    "usuario" => $usuario,
    "notificacoes" => $notificacoes,
    "materiais" => $materiais,
    "perfil" => $dadosPerfil
]);
?>