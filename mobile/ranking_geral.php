<?php
/**
 * ranking_geral.php
 * Missão Rocket — Ranking Geral dos Clãs
 *
 * Página integrada ao layout padrão do /mobile/.
 * Exibe os grupos ordenados pelos pontos aprovados no período informado.
 *
 * O logotipo de cada clã é carregado do campo BLOB da tabela grupos
 * através de gerenciar_grupos.php?logo=ID.
 */

declare(strict_types=1);

if (!isset($pdo)) {
    require_once '../config.php';
}

date_default_timezone_set('America/Recife');

/*
|--------------------------------------------------------------------------
| Período
|--------------------------------------------------------------------------
*/
$de = isset($_GET['de']) && is_string($_GET['de'])
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['de'])
    ? $_GET['de']
    : date('Y-m-01');

$ate = isset($_GET['ate']) && is_string($_GET['ate'])
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['ate'])
    ? $_GET['ate']
    : date('Y-m-t');

if ($de > $ate) {
    [$de, $ate] = [$ate, $de];
}

$inicio = $de . ' 00:00:00';
$fim    = $ate . ' 23:59:59';

/*
|--------------------------------------------------------------------------
| Funções
|--------------------------------------------------------------------------
*/
if (!function_exists('ranking_e')) {
    function ranking_e(mixed $valor): string
    {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ranking_pontos')) {
    function ranking_pontos(mixed $valor): string
    {
        return number_format((float)$valor, 0, ',', '.');
    }
}

/*
|--------------------------------------------------------------------------
| Ranking dos clãs
|--------------------------------------------------------------------------
| Os filtros ficam no LEFT JOIN para que clãs sem pontuação continuem
| aparecendo com 0 pontos.
|
| O BLOB não é carregado nesta consulta. Apenas verificamos se existe
| uma imagem para que a página possa exibi-la quando necessário.
*/
$query = "
    SELECT
        g.id,
        g.nome,

        CASE
            WHEN g.logo IS NOT NULL
                 AND OCTET_LENGTH(g.logo) > 0
            THEN 1
            ELSE 0
        END AS possui_logo,

        COALESCE(hp.pontos_provas, 0) AS pontos_provas,

        COALESCE(pa.pontos_administrativos, 0) AS pontos_administrativos,

        (
            COALESCE(hp.pontos_provas, 0)
            +
            COALESCE(pa.pontos_administrativos, 0)
        ) AS total_pontos

    FROM grupos g

    LEFT JOIN (
        SELECT
            grupo_id,
            SUM(pontos) AS pontos_provas
        FROM historico_pontos
        WHERE status = 'aprovado'
          AND criado_em BETWEEN ? AND ?
        GROUP BY grupo_id
    ) hp
        ON hp.grupo_id = g.id

    LEFT JOIN (
        SELECT
            grupo_id,
            SUM(
                CASE
                    WHEN tipo = 'adicao' THEN pontos
                    WHEN tipo = 'penalizacao' THEN -pontos
                    ELSE 0
                END
            ) AS pontos_administrativos
        FROM penalizacoes_grupos
        WHERE criado_em BETWEEN ? AND ?
        GROUP BY grupo_id
    ) pa
        ON pa.grupo_id = g.id

    ORDER BY
        total_pontos DESC,
        g.nome ASC
";

try {
    $stmt = $pdo->prepare($query);

    $stmt->execute([
        $inicio,
        $fim,
        $inicio,
        $fim
    ]);

    $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Não foi possível carregar o ranking.');
}

$totalClans = count($ranking);
$totalPontos = 0;

foreach ($ranking as $grupo) {
    $totalPontos += (int)$grupo['total_pontos'];
}

$primeiro = $ranking[0] ?? null;
$segundo  = $ranking[1] ?? null;
$terceiro = $ranking[2] ?? null;

/*
|--------------------------------------------------------------------------
| URL da logo
|--------------------------------------------------------------------------
*/
if (!function_exists('ranking_logo_url')) {
    function ranking_logo_url(mixed $grupoId): string
    {
        return 'gerenciar_grupos.php?logo=' . (int)$grupoId;
    }
}

/*
|--------------------------------------------------------------------------
| Header padrão do aplicativo
|--------------------------------------------------------------------------
*/
require_once 'header.php';
?>

<style>
    .ranking-page-title {
        color: #1e293b;
        font-weight: 800;
        font-size: 1.25rem;
        line-height: 1.2;
    }

    .ranking-page-subtitle {
        color: #64748b;
        font-size: .78rem;
    }

    .ranking-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: rgba(139, 92, 246, .10);
        color: #8b5cf6;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 40px;
    }

    .ranking-filter {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
    }

    .ranking-filter .form-label {
        color: #64748b;
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .ranking-filter .form-control {
        border-color: #dee2e6;
        font-size: .82rem;
        min-height: 38px;
    }

    .ranking-stat {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
        padding: 12px;
        height: 100%;
    }

    .ranking-stat-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: rgba(139, 92, 246, .10);
        color: #8b5cf6;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 7px;
    }

    .ranking-stat-label {
        color: #94a3b8;
        font-size: .64rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .ranking-stat-value {
        color: #1e293b;
        font-size: 1.05rem;
        font-weight: 900;
        line-height: 1.2;
    }

    .ranking-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .05);
        height: 100%;
        overflow: hidden;
    }

    .ranking-card-header {
        border-bottom: 1px solid #f1f5f9;
        padding: 12px 14px;
        color: #1e293b;
        font-size: .85rem;
        font-weight: 800;
    }

    .ranking-card-body {
        padding: 14px;
    }

    .top-clan {
        position: relative;
        text-align: center;
    }

    .top-clan-logo {
        width: 92px;
        height: 92px;
        object-fit: contain;
        border-radius: 22px;
        background: #fff;
        border: 1px solid #e5e7eb;
        padding: 7px;
        box-shadow: 0 5px 15px rgba(15, 23, 42, .08);
        margin: 0 auto 10px;
        display: block;
    }

    .top-clan-logo-empty {
        width: 92px;
        height: 92px;
        border-radius: 22px;
        background: #f1f5f9;
        color: #94a3b8;
        border: 1px dashed #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-size: 1.7rem;
    }

    .top-clan-position {
        width: 38px;
        height: 38px;
        margin: 0 auto 9px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: .9rem;
    }

    .top-clan-1 .top-clan-position {
        background: rgba(255, 193, 7, .15);
        color: #b88600;
    }

    .top-clan-2 .top-clan-position {
        background: rgba(108, 117, 125, .12);
        color: #6c757d;
    }

    .top-clan-3 .top-clan-position {
        background: rgba(205, 127, 50, .12);
        color: #9a5a20;
    }

    .top-clan-name {
        color: #1e293b;
        font-size: .92rem;
        font-weight: 800;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .top-clan-points {
        color: #8b5cf6;
        font-size: 1.35rem;
        font-weight: 900;
        margin-top: 5px;
    }

    .top-clan-label {
        color: #94a3b8;
        font-size: .65rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .top-clan-badge {
        margin-top: 8px;
    }

    .ranking-list {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .05);
        overflow: hidden;
    }

    .ranking-list-title {
        padding: 13px 14px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .ranking-list-title strong {
        color: #1e293b;
        font-size: .9rem;
    }

    .ranking-row {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 11px 13px;
        border-bottom: 1px solid #f1f5f9;
    }

    .ranking-row:last-child {
        border-bottom: 0;
    }

    .ranking-position {
        width: 32px;
        height: 32px;
        flex: 0 0 32px;
        border-radius: 9px;
        background: #f1f5f9;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .75rem;
        font-weight: 900;
    }

    .ranking-clan-logo {
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        object-fit: contain;
        border-radius: 12px;
        background: #fff;
        border: 1px solid #e5e7eb;
        padding: 4px;
    }

    .ranking-clan-logo-empty {
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        border-radius: 12px;
        background: #f1f5f9;
        color: #94a3b8;
        border: 1px dashed #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .95rem;
    }

    .ranking-clan {
        min-width: 0;
        flex: 1;
    }

    .ranking-clan-name {
        color: #1e293b;
        font-size: .84rem;
        font-weight: 750;
        overflow-wrap: anywhere;
    }

    .ranking-clan-label {
        color: #94a3b8;
        font-size: .64rem;
    }

    .ranking-row-points {
        color: #8b5cf6;
        font-size: .82rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .ranking-empty {
        padding: 30px 15px;
        text-align: center;
        color: #64748b;
    }

    .ranking-empty-icon {
        width: 52px;
        height: 52px;
        margin: 0 auto 10px;
        border-radius: 15px;
        background: #f1f5f9;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .ranking-footer {
        color: #94a3b8;
        font-size: .65rem;
        text-align: center;
    }

    @media (max-width: 575.98px) {
        .ranking-page-title {
            font-size: 1.1rem;
        }

        .ranking-filter {
            padding: 12px !important;
        }

        .top-clan-points {
            font-size: 1.2rem;
        }

        .top-clan-name {
            font-size: .82rem;
        }

        .top-clan-logo,
        .top-clan-logo-empty {
            width: 76px;
            height: 76px;
        }

        .ranking-row {
            padding: 10px 11px;
            gap: 8px;
        }

        .ranking-clan-logo,
        .ranking-clan-logo-empty {
            width: 40px;
            height: 40px;
            flex-basis: 40px;
        }
    }
</style>


<!-- CABEÇALHO DA PÁGINA -->
<div class="row g-2 mb-3">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between gap-2">

            <div class="d-flex align-items-center gap-2">
                <div class="ranking-icon">
                    <i class="fa-solid fa-ranking-star"></i>
                </div>

                <div>
                    <h4 class="ranking-page-title my-0">
                        Ranking Geral
                    </h4>

                    <div class="ranking-page-subtitle">
                        Classificação dos GCs
                    </div>
                </div>
            </div>

            <a
                href="dashboard.php"
                class="btn btn-sm btn-outline-secondary rounded-3"
                title="Voltar ao dashboard"
            >
                <i class="fa-solid fa-arrow-left"></i>
                <span class="d-none d-sm-inline ms-1">Voltar</span>
            </a>

        </div>
    </div>
</div>


<!-- FILTRO DO PERÍODO -->
<div class="ranking-filter p-3 mb-3">

    <form method="get">

        <div class="row g-2 align-items-end">

            <div class="col-6 col-md-4">
                <label class="form-label">
                    Data inicial
                </label>

                <input
                    type="date"
                    name="de"
                    value="<?= ranking_e($de) ?>"
                    class="form-control form-control-sm"
                >
            </div>

            <div class="col-6 col-md-4">
                <label class="form-label">
                    Data final
                </label>

                <input
                    type="date"
                    name="ate"
                    value="<?= ranking_e($ate) ?>"
                    class="form-control form-control-sm"
                >
            </div>

            <div class="col-12 col-md-4">
                <button
                    type="submit"
                    class="btn btn-dark btn-sm w-100 fw-bold rounded-3"
                >
                    <i class="fa-solid fa-filter me-1"></i>
                    Atualizar Ranking
                </button>
            </div>

        </div>

    </form>

</div>


<!-- RESUMO -->
<div class="row g-2 mb-3">

    <div class="col-6 col-md-4">
        <div class="ranking-stat">

            <div class="ranking-stat-icon">
                <i class="fa-solid fa-users"></i>
            </div>

            <div class="ranking-stat-label">
                GCs
            </div>

            <div class="ranking-stat-value">
                <?= ranking_pontos($totalClans) ?>
            </div>

        </div>
    </div>


    <div class="col-6 col-md-4">
        <div class="ranking-stat">

            <div class="ranking-stat-icon">
                <i class="fa-solid fa-bolt"></i>
            </div>

            <div class="ranking-stat-label">
                Pontos aprovados
            </div>

            <div class="ranking-stat-value">
                <?= ranking_pontos($totalPontos) ?>
            </div>

        </div>
    </div>


    <div class="col-12 col-md-4">
        <div class="ranking-stat">

            <div class="ranking-stat-icon">
                <i class="fa-solid fa-crown"></i>
            </div>

            <div class="ranking-stat-label">
                Líder atual
            </div>

            <div class="ranking-stat-value text-truncate">
                <?= $primeiro
                    ? ranking_e($primeiro['nome'])
                    : '—'
                ?>
            </div>

        </div>
    </div>

</div>


<?php if (!empty($ranking)): ?>

    <!-- TOP 3 -->
    <div class="row g-2 mb-3">

        <?php if ($primeiro): ?>
            <div class="col-12 col-md-4">

                <div class="ranking-card top-clan top-clan-1">

                    <div class="ranking-card-header">
                        <i class="fa-solid fa-trophy text-warning me-1"></i>
                        1º Lugar
                    </div>

                    <div class="ranking-card-body">

                        <?php if ((int)$primeiro['possui_logo'] === 1): ?>
                            <img
                                src="<?= ranking_e(ranking_logo_url($primeiro['id'])) ?>"
                                alt="Logo de <?= ranking_e($primeiro['nome']) ?>"
                                class="top-clan-logo"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="top-clan-logo-empty" title="GC sem logotipo">
                                <i class="fa-solid fa-image"></i>
                            </div>
                        <?php endif; ?>

                        <div class="top-clan-position">
                            <i class="fa-solid fa-crown"></i>
                        </div>

                        <div class="top-clan-name">
                            <?= ranking_e($primeiro['nome']) ?>
                        </div>

                        <div class="top-clan-points">
                            <?= ranking_pontos($primeiro['total_pontos']) ?>
                        </div>

                        <div class="top-clan-label">
                            pontos
                        </div>

                        <div class="top-clan-badge">
                            <span class="badge bg-warning text-dark">
                                LÍDER
                            </span>
                        </div>

                    </div>

                </div>

            </div>
        <?php endif; ?>


        <?php if ($segundo): ?>
            <div class="col-6 col-md-4">

                <div class="ranking-card top-clan top-clan-2">

                    <div class="ranking-card-header">
                        <i class="fa-solid fa-medal text-secondary me-1"></i>
                        2º Lugar
                    </div>

                    <div class="ranking-card-body">

                        <?php if ((int)$segundo['possui_logo'] === 1): ?>
                            <img
                                src="<?= ranking_e(ranking_logo_url($segundo['id'])) ?>"
                                alt="Logo de <?= ranking_e($segundo['nome']) ?>"
                                class="top-clan-logo"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="top-clan-logo-empty" title="GC sem logotipo">
                                <i class="fa-solid fa-image"></i>
                            </div>
                        <?php endif; ?>

                        <div class="top-clan-position">
                            2º
                        </div>

                        <div class="top-clan-name">
                            <?= ranking_e($segundo['nome']) ?>
                        </div>

                        <div class="top-clan-points">
                            <?= ranking_pontos($segundo['total_pontos']) ?>
                        </div>

                        <div class="top-clan-label">
                            pontos
                        </div>

                    </div>

                </div>

            </div>
        <?php endif; ?>


        <?php if ($terceiro): ?>
            <div class="col-6 col-md-4">

                <div class="ranking-card top-clan top-clan-3">

                    <div class="ranking-card-header">
                        <i class="fa-solid fa-medal me-1" style="color:#a35f1c;"></i>
                        3º Lugar
                    </div>

                    <div class="ranking-card-body">

                        <?php if ((int)$terceiro['possui_logo'] === 1): ?>
                            <img
                                src="<?= ranking_e(ranking_logo_url($terceiro['id'])) ?>"
                                alt="Logo de <?= ranking_e($terceiro['nome']) ?>"
                                class="top-clan-logo"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="top-clan-logo-empty" title="GC sem logotipo">
                                <i class="fa-solid fa-image"></i>
                            </div>
                        <?php endif; ?>

                        <div class="top-clan-position">
                            3º
                        </div>

                        <div class="top-clan-name">
                            <?= ranking_e($terceiro['nome']) ?>
                        </div>

                        <div class="top-clan-points">
                            <?= ranking_pontos($terceiro['total_pontos']) ?>
                        </div>

                        <div class="top-clan-label">
                            pontos
                        </div>

                    </div>

                </div>

            </div>
        <?php endif; ?>

    </div>


    <!-- CLASSIFICAÇÃO COMPLETA -->
    <div class="ranking-list mb-3">

        <div class="ranking-list-title">

            <strong>
                <i class="fa-solid fa-list-ol text-warning me-1"></i>
                Classificação geral
            </strong>

            <span class="badge bg-light text-dark border">
                <?= ranking_pontos($totalClans) ?> GCs
            </span>

        </div>


        <?php foreach ($ranking as $indice => $grupo): ?>

            <?php
                $posicao = $indice + 1;

                if ($posicao === 1) {
                    $posClasse = 'bg-warning text-dark';
                } elseif ($posicao === 2) {
                    $posClasse = 'bg-secondary text-white';
                } elseif ($posicao === 3) {
                    $posClasse = 'text-white';
                } else {
                    $posClasse = '';
                }
            ?>

            <div class="ranking-row">

                <div
                    class="ranking-position <?= $posClasse ?>"
                    <?php if ($posicao === 3): ?>
                        style="background:#a35f1c;"
                    <?php endif; ?>
                >
                    <?= $posicao ?>
                </div>


                <?php if ((int)$grupo['possui_logo'] === 1): ?>
                    <img
                        src="<?= ranking_e(ranking_logo_url($grupo['id'])) ?>"
                        alt="Logo de <?= ranking_e($grupo['nome']) ?>"
                        class="ranking-clan-logo"
                        loading="lazy"
                    >
                <?php else: ?>
                    <div class="ranking-clan-logo-empty" title="GC sem logotipo">
                        <i class="fa-solid fa-image"></i>
                    </div>
                <?php endif; ?>


                <div class="ranking-clan">

                    <div class="ranking-clan-name">
                        <?= ranking_e($grupo['nome']) ?>
                    </div>

                    <div class="ranking-clan-label">
                        GC
                    </div>

                </div>


                <div class="ranking-row-points">
                    <?= ranking_pontos($grupo['total_pontos']) ?>
                    <small class="text-muted fw-normal">pts</small>
                </div>

            </div>

        <?php endforeach; ?>

    </div>


<?php else: ?>

    <div class="ranking-list mb-3">

        <div class="ranking-empty">

            <div class="ranking-empty-icon">
                <i class="fa-solid fa-ranking-star"></i>
            </div>

            <div class="fw-bold text-dark">
                Nenhum clã encontrado
            </div>

            <div class="small mt-1">
                Não existem dados para o período selecionado.
            </div>

        </div>

    </div>

<?php endif; ?>


<!-- RODAPÉ -->
<div class="ranking-footer mb-4">

    <i class="fa-solid fa-circle-check text-success me-1"></i>

    Pontuação baseada somente em atividades aprovadas.

    <span class="ms-1">
        • Atualização automática
    </span>

</div>


<script>
    /*
     * Atualiza a página sem alterar os filtros selecionados.
     * Mantém o comportamento de placar, porém integrado ao mobile.
     */
    window.setTimeout(function () {
        window.location.reload();
    }, 30000);
</script>
