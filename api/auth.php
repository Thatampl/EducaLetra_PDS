<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'Database.php';

$db = new Database();
$conn = $db->conectar();

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

if ($acao === 'cadastrar') {
    $nome = trim($_POST['nome'] ?? '');
    $emailInput = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $tipo = $_POST['tipo_usuario'] ?? 'aluno';
    $escola_id = intval($_POST['escola_id'] ?? 1);
    $turma_id = intval($_POST['turma_id'] ?? 1);
    $turmas_professor = $_POST['turmas_professor'] ?? [];
    $cpf = trim($_POST['cpf'] ?? '');
    $vinculoAluno = trim($_POST['vinculo_aluno'] ?? '');

    if (!$nome || !$senha || !$tipo) {
        echo json_encode(["sucesso" => false, "mensagem" => "Preencha nome, senha e tipo de usuário."]);
        exit;
    }

    // E-mail é obrigatório apenas para professor e responsável; aluno pode deixar em branco
    if ($tipo !== 'aluno' && $emailInput === '') {
        echo json_encode(["sucesso" => false, "mensagem" => "E-mail é obrigatório para professor e responsável."]);
        exit;
    }

    // Valida se o professor selecionou pelo menos uma turma
    if ($tipo === 'professor' && (!is_array($turmas_professor) || count($turmas_professor) === 0)) {
        echo json_encode(["sucesso" => false, "mensagem" => "Selecione pelo menos uma turma para o professor."]);
        exit;
    }

    $email = ($emailInput === '') ? null : $emailInput;
    $cpfVal = ($cpf === '') ? null : $cpf;

    // Verifica duplicidade de e-mail caso tenha sido informado
    if ($email !== null) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(["sucesso" => false, "mensagem" => "E-mail já cadastrado."]);
            exit;
        }
    }

    // Se for responsável, valida e localiza o aluno pelo e-mail, código ou CPF antes de criar a conta
    $alunoIdParaVincular = null;
    $nomeAlunoVinculado = '';
    if ($tipo === 'responsavel') {
        if ($vinculoAluno === '') {
            echo json_encode(["sucesso" => false, "mensagem" => "Informe o e-mail, código ou CPF do aluno para vincular."]);
            exit;
        }

        $stmtBusca = $conn->prepare("
            SELECT u.id, u.nome 
            FROM usuarios u 
            JOIN alunos a ON u.id = a.usuario_id 
            WHERE u.tipo_usuario = 'aluno' 
              AND (u.email = ? OR u.codigo_identificacao = ? OR a.cpf_aluno = ?)
            LIMIT 1
        ");
        $stmtBusca->bind_param("sss", $vinculoAluno, $vinculoAluno, $vinculoAluno);
        $stmtBusca->execute();
        $resAluno = $stmtBusca->get_result();

        if ($alunoEncontrado = $resAluno->fetch_assoc()) {
            $alunoIdParaVincular = intval($alunoEncontrado['id']);
            $nomeAlunoVinculado = $alunoEncontrado['nome'];
        } else {
            echo json_encode(["sucesso" => false, "mensagem" => "Aluno não encontrado com esse e-mail, código ou CPF."]);
            exit;
        }
    }

    // Gera código único (ex: ALU123, PRO456, RES789)
    $codigo = strtoupper(substr($tipo, 0, 3)) . rand(100, 999);

    $stmt = $conn->prepare("INSERT INTO usuarios (codigo_identificacao, nome, email, senha, tipo_usuario, escola_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $codigo, $nome, $email, $senha, $tipo, $escola_id);

    if ($stmt->execute()) {
        $usuario_id = $conn->insert_id;

        if ($tipo === 'aluno') {
            $stmtAlu = $conn->prepare("INSERT INTO alunos (usuario_id, cpf_aluno, turma_id) VALUES (?, ?, ?)");
            $stmtAlu->bind_param("isi", $usuario_id, $cpfVal, $turma_id);
            $stmtAlu->execute();
            echo json_encode([
                "sucesso" => true,
                "mensagem" => "Conta de aluno criada com sucesso! Anote seu código para entrar: $codigo"
            ]);
        } elseif ($tipo === 'professor') {
            $stmtProf = $conn->prepare("INSERT INTO professores (usuario_id, cpf_professor) VALUES (?, ?)");
            $stmtProf->bind_param("is", $usuario_id, $cpfVal);
            $stmtProf->execute();

            $stmtTurmaProf = $conn->prepare("INSERT INTO professor_turmas (professor_id, turma_id) VALUES (?, ?)");
            foreach ($turmas_professor as $tId) {
                $tIdInt = intval($tId);
                if ($tIdInt > 0) {
                    $stmtTurmaProf->bind_param("ii", $usuario_id, $tIdInt);
                    $stmtTurmaProf->execute();
                }
            }

            echo json_encode([
                "sucesso" => true,
                "mensagem" => "Conta de professor criada! Seu código de acesso é: $codigo"
            ]);
        } elseif ($tipo === 'responsavel') {
            $stmtResp = $conn->prepare("INSERT INTO responsaveis (usuario_id, cpf_responsavel) VALUES (?, ?)");
            $stmtResp->bind_param("is", $usuario_id, $cpfVal);
            $stmtResp->execute();

            $conn->query("UPDATE alunos SET responsavel_id = $usuario_id WHERE usuario_id = $alunoIdParaVincular");

            echo json_encode([
                "sucesso" => true,
                "mensagem" => "Conta criada e vinculada ao aluno $nomeAlunoVinculado! Seu código de acesso é: $codigo"
            ]);
        }
    } else {
        echo json_encode(["sucesso" => false, "mensagem" => "Erro ao cadastrar usuário: " . $conn->error]);
    }
    exit;
}

if ($acao === 'login') {
    $identificador = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($identificador === '' || $senha === '') {
        echo json_encode(["sucesso" => false, "mensagem" => "Informe e-mail/código e senha."]);
        exit;
    }

    $stmt = $conn->prepare("SELECT u.*, e.nome AS escola_nome FROM usuarios u LEFT JOIN escolas e ON u.escola_id = e.id WHERE (u.email = ? OR u.codigo_identificacao = ?) AND u.senha = ?");
    $stmt->bind_param("sss", $identificador, $identificador, $senha);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($user = $res->fetch_assoc()) {
        unset($user['senha']);
        $_SESSION['usuario'] = $user;
        echo json_encode(["sucesso" => true, "usuario" => $user]);
    } else {
        echo json_encode(["sucesso" => false, "mensagem" => "E-mail/código ou senha incorretos."]);
    }
    exit;
}

if ($acao === 'logout') {
    session_destroy();
    echo json_encode(["sucesso" => true]);
    exit;
}
?>