<?php

require_once '../config.php';

exigirAdministrador($pdo);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Recife');

require_once 'header.php';

$statusNotificacoes = $pdo->query("
    SELECT
        u.id,
        u.nome,
        u.email,
        g.nome AS clan_nome,
        IF(un.endpoint IS NOT NULL, 1, 0) AS ativado
    FROM usuarios u
    LEFT JOIN usuarios_notificacoes un
        ON u.id = un.usuario_id
    LEFT JOIN grupos g
        ON u.grupo_id = g.id
    ORDER BY u.nome ASC
")->fetchAll(PDO::FETCH_ASSOC);
$feedback="";
?>


    <script src="https://cdn.tailwindcss.com"></script>


<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- ==========================================================
         CABEÇALHO
    =========================================================== -->
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
      
    </div>
    
    <div class="mb-6 border-b border-gray-200 pb-4">

        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">
            Status de Ativação do Web Push
        </h1>

        <p class="text-xs sm:text-sm text-gray-500 mt-1">
            Consulte quais participantes estão com alertas ativos.
        </p>

    </div>

 <?= $feedback ?>
    <!-- ==========================================================
         MENSAGENS DE SUCESSO
    =========================================================== -->

    


    <!-- ==========================================================
         MENSAGENS DE ERRO
    =========================================================== -->

   

    <!-- ==========================================================
         ERRO DE BANCO
    =========================================================== -->




    <!-- ==========================================================
         TABELA DESKTOP
    =========================================================== -->

  <div class="bg-white shadow rounded-xl overflow-hidden border border-gray-200">

                <div class="card p-3 border-0 shadow-sm rounded-4">

       

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle small mb-0">

                            <thead class="table-light font-monospace">
                                <tr>
                                    <th>Participante</th>
                                    <th>Clã</th>
                                    <th class="text-center">Push</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (empty($statusNotificacoes)): ?>

                                    <tr>
                                        <td colspan="3"
                                            class="text-muted text-center py-4">
                                            Nenhum participante registrado.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($statusNotificacoes as $usr): ?>

                                        <tr>

                                            <td>
                                                <div class="fw-bold text-dark">
                                                    <?= htmlspecialchars($usr['nome']) ?>
                                                </div>

                                                <span class="text-muted"
                                                      style="font-size: 0.75rem;">
                                                    <?= htmlspecialchars($usr['email']) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="badge bg-light text-secondary border">
                                                    <?= $usr['clan_nome']
                                                        ? htmlspecialchars($usr['clan_nome'])
                                                        : 'Sem Clã' ?>
                                                </span>
                                            </td>

                                            <td class="text-center notification-status">

                                                <?php if ($usr['ativado'] == 1): ?>

                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill fw-bold">
                                                        <i class="fa-solid fa-circle-check me-1"></i>
                                                        Ativo
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill fw-bold">
                                                        <i class="fa-solid fa-circle-xmark me-1"></i>
                                                        Inativo
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>
                    </div>

                </div>

            </div>



  

</div>



</body>
</html>