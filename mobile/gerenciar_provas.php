<?php

require_once '../config.php';
exigirAdministrador($pdo);
require_once 'header.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Controle de acesso restrito para administradores
if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] != 'admin') {
    echo "<div class='alert alert-danger py-2 small text-center m-3'>Acesso restrito para administradores.</div>";
    exit;
}

date_default_timezone_set('America/Recife');
$feedback = '';





if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['cadastrar_prova'])) {

        $grupo_id = ($_POST['tipo'] == 'global') ? null : $_POST['grupo_id'];

        $stmt = $pdo->prepare("INSERT INTO provas (titulo, descricao, pontos, tipo, grupo_id, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['titulo_prova'],
            $_POST['descricao'],
            $_POST['pontos'],
            $_POST['tipo'],
            $grupo_id,
            $_POST['data_inicio'],
            $_POST['data_fim']
        ]);

        $feedback = "<div class='alert alert-success py-2 small text-center'>Missão adicionada à Arena!</div>";
    }

   
}



// 1. PROCESSAR EXCLUSÃO DE PROVA
if (isset($_POST['excluir_prova'])) {
    $id_excluir = (int)$_POST['id_prova'];
    
    $stmtDel = $pdo->prepare("DELETE FROM provas WHERE id = ? AND id NOT IN (1, 2, 999)");
    if ($stmtDel->execute([$id_excluir])) {
        $feedback = "<div class='alert alert-success py-2 small text-center mb-3'>Missão removida com sucesso!</div>";
    } else {
        $feedback = "<div class='alert alert-danger py-2 small text-center mb-3'>Erro ao remover a missão.</div>";
    }
}

// 2. PROCESSAR EDIÇÃO DE PROVA VIA POP-UP
if (isset($_POST['editar_prova_submit'])) {
    $id_editar = (int)$_POST['id_prova'];
    $novo_titulo = trim($_POST['titulo']);
    $novos_pontos = (int)$_POST['pontos'];
    $nova_data_inicio = $_POST['data_evento_inicio'];
    $nova_data_fim = $_POST['data_evento_fim'];

    $stmtUpd = $pdo->prepare("
        UPDATE provas 
        SET titulo = ?, pontos = ?, data_inicio = ?, data_fim = ? 
        WHERE id = ? AND id NOT IN (1, 2, 999)
    ");
    if ($stmtUpd->execute([$novo_titulo, $novos_pontos, $nova_data_inicio, $nova_data_fim, $id_editar])) {
        $feedback = "<div class='alert alert-success py-2 small text-center mb-3'>Missão atualizada com sucesso!</div>";
    } else {
        $feedback = "<div class='alert alert-danger py-2 small text-center mb-3'>Erro ao atualizar a missão.</div>";
    }
}

// Filtro de mês padrão (Mês atual corrente)
$mes_selecionado = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');

// 3. BUSCAR TODAS AS PROVAS DO MÊS SELECIONADO (Escondendo IDs do sistema)
$stmt = $pdo->prepare("
    SELECT id, titulo, pontos, data_inicio, data_fim
    FROM provas 
    WHERE id NOT IN (1, 2, 999) 
      AND data_inicio LIKE ?
    ORDER BY data_inicio ASC, titulo ASC
");
$stmt->execute([$mes_selecionado . '%']);
$provasMes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    body { background-color: #f8fafc !important; color: #1e293b; }
    .main-wrapper { max-width: 500px; margin: 0 auto; padding: 1.5rem 1rem 5rem 1rem; }
    .task-admin-card { background: #ffffff; border-radius: 16px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.01); display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .search-input-group { position: relative; }
    .search-input-group i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .search-input-group input { width: 100%; padding: 0.5rem 1rem 0.5rem 2.2rem; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.9rem; outline: none; }
</style>

<div class="main-wrapper">
    <div class="d-flex align-items-center mb-3">
        <a href="gerenciar.php" class="btn btn-sm btn-outline-secondary rounded-3 me-3"><i class="fa-solid fa-arrow-left"></i></a>
        <h4 class="fw-bold my-0 text-dark">Acervo de Missões</h4>
    </div>

    <?= $feedback ?>
 <div class="row g-3">

                   
                    <div class="col-12 col-lg-6">

                        <!-- Prova -->
                        <div class="form-section p-3 shadow-sm mb-3">

                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center"
                                     style="width:38px;height:38px;">
                                    <i class="fa-solid fa-trophy"></i>
                                </div>

                                <div>
                                    <h6 class="fw-bold mb-0 text-primary">Adicionar Nova Prova</h6>
                                    <div class="small text-muted">Cadastre uma nova missão na Arena.</div>
                                </div>
                            </div>

                            <form method="POST" class="small">

                                <input type="hidden" name="cadastrar_prova" value="1">

                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Título da Prova</label>
                                    <input type="text"
                                           name="titulo_prova"
                                           class="form-control form-control-sm"
                                           placeholder="Título da Prova"
                                           required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Descrição</label>
                                    <textarea name="descricao"
                                              class="form-control form-control-sm"
                                              rows="3"
                                              placeholder="Instruções e regras da missão..."
                                              required></textarea>
                                </div>

                                <div class="row g-2 mb-2">

                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Pontuação</label>
                                        <input type="number"
                                               name="pontos"
                                               class="form-control form-control-sm"
                                               placeholder="Pontuação"
                                               required>
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Tipo</label>
                                        <select name="tipo" class="form-select form-select-sm">
                                            <option value="global">Global</option>
                                            <option value="grupo">Por Clã</option>
                                        </select>
                                    </div>

                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Clã</label>
                                    <select name="grupo_id" class="form-select form-select-sm">
                                        <option value="">Se for por Clã, selecione qual:</option>

                                        <?php foreach ($grupos as $g): ?>
                                            <option value="<?= $g['id'] ?>">
                                                <?= htmlspecialchars($g['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>
                                </div>

                                <div class="row g-2 mb-3">

                                    <div class="col-6">
                                        <label class="form-label text-muted small mb-1">Data Início</label>
                                        <input type="date"
                                               name="data_inicio"
                                               class="form-control form-control-sm"
                                               required>
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label text-muted small mb-1">Data Fim</label>
                                        <input type="date"
                                               name="data_fim"
                                               class="form-control form-control-sm"
                                               required>
                                    </div>

                                </div>

                                <button type="submit"
                                        class="btn btn-primary btn-sm w-100 fw-bold py-2 rounded-3">
                                    <i class="fa-solid fa-rocket me-1"></i>
                                    Lançar Missão
                                </button>

                            </form>

                        </div>

                    </div>

                    


                </div>
    <div class="card p-3 border-0 shadow-sm rounded-4 mb-3 bg-white">
        <div class="mb-2">
            <label class="form-label small fw-bold text-secondary">Filtrar por Mês de Atividade:</label>
            <select class="form-select form-select-sm rounded-3" onchange="location = this.value;">
                <?php
                // Gera opções para os meses de Junho a Dezembro de 2026
                for ($m = 6; $m <= 12; $m++) {
                    $val = "2026-" . str_pad($m, 2, '0', STR_PAD_LEFT);
                    $label = date('F / Y', strtotime($val . "-01"));
                    // Tradução simples para português
                    $label = strtr($label, ['June'=>'Junho','July'=>'Julho','August'=>'Agosto','September'=>'Setembro','October'=>'Outubro','November'=>'Novembro','December'=>'Dezembro']);
                    $selected = ($val === $mes_selecionado) ? 'selected' : '';
                    echo "<option value='gerenciar_provas.php?mes={$val}' {$selected}>{$label}</option>";
                }
                ?>
            </select>
        </div>
        <div class="search-input-group mt-1">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="adminTaskSearch" placeholder="Digitar termo para filtrar na tela...">
        </div>
    </div>

    <div class="d-flex justify-content-between mb-2 small fw-bold text-muted px-1">
        <span>Listando objetivos do mês</span>
        <span>Total: <?= count($provasMes) ?></span>
    </div>

    <div id="adminTasksContainer">
        <?php if (empty($provasMes)): ?>
            <div class="text-center py-5 text-muted small bg-white rounded-4 border">
                <i class="fa-solid fa-folder-open d-block fs-2 mb-2 text-warning"></i>
                Nenhuma prova cadastrada para este mês.
            </div>
        <?php else: ?>
            <?php foreach ($provasMes as $p): 
                $data_exibicao = date('d/m', strtotime($p['data_inicio']));
            ?>
                <div class="task-admin-card" data-title="<?= strtolower(htmlspecialchars($p['titulo'])) ?>">
                    <div>
                        <span class="badge bg-light text-dark border me-1"><?= $data_exibicao ?></span>
                        <strong class="text-dark d-block mt-1" style="font-size: 0.92rem;"><?= htmlspecialchars($p['titulo']) ?></strong>
                        <span class="text-muted" style="font-size: 0.8rem;">🏆 <?= $p['pontos'] ?> Pontos</span>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 rounded-3" 
                                onclick="abrirModalEdicao(<?= $p['id'] ?>, '<?= htmlspecialchars($p['titulo'], ENT_QUOTES) ?>', <?= $p['pontos'] ?>, '<?= $p['data_inicio'] ?>', '<?= $p['data_fim'] ?>')">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        
                        <form method="POST" onsubmit="return confirm('Deseja realmente apagar esta missão definitiva?');" class="d-inline">
                            <input type="hidden" name="id_prova" value="<?= $p['id'] ?>">
                            <button type="submit" name="excluir_prova" class="btn btn-sm btn-outline-danger py-1 px-2 rounded-3">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalEditarProva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" style="max-width: 380px;">
        <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 15px 50px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark">📝 Editar Missão Devocional</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="editar_prova_submit" value="1">
                <input type="hidden" name="id_prova" id="edit_id_prova">
                <div class="modal-body py-3">
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-secondary">Título da Missão:</label>
                        <input type="text" name="titulo" id="edit_titulo" class="form-control form-control-sm rounded-3" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-secondary">Pontuação Recompensa:</label>
                        <input type="number" name="pontos" id="edit_pontos" class="form-control form-control-sm rounded-3" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-bold text-secondary">Data do Evento (Fim):</label>
                        <input type="date" name="data_evento_inicio" id="edit_data_inicio" class="form-control form-control-sm rounded-3" required>
                    </div>
                     <div class="mb-1">
                        <label class="form-label small fw-bold text-secondary">Data do Evento (Fim):</label>
                        <input type="date" name="data_evento_fim" id="edit_data_fim" class="form-control form-control-sm rounded-3" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary rounded-3 fw-bold px-4">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Lógica de busca rápida client-side
        const searchInput = document.getElementById('adminTaskSearch');
        const cards = document.querySelectorAll('.task-admin-card');

        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase().trim();
            cards.forEach(card => {
                const title = card.getAttribute('data-title');
                card.style.display = title.includes(term) ? 'flex' : 'none';
            });
        });
    });

    // Injeta os dados dinamicamente no Modal antes de exibi-lo
    function abrirModalEdicao(id, titulo, pontos, data_inicio,data_fim) {
        document.getElementById('edit_id_prova').value = id;
        document.getElementById('edit_titulo').value = titulo;
        document.getElementById('edit_pontos').value = pontos;
        document.getElementById('edit_data_inicio').value = data_inicio;
        document.getElementById('edit_data_fim').value = data_fim;

        const modal = new bootstrap.Modal(document.getElementById('modalEditarProva'));
        modal.show();
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>