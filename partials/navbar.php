<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$pages = [
    'dashboard.php' => ['label' => 'Dashboard', 'icon' => 'bi-speedometer2'],
    'live.php'      => ['label' => 'Live TV',   'icon' => 'bi-tv'],
    'vod.php'       => ['label' => 'VOD',       'icon' => 'bi-film'],
    'series.php'    => ['label' => 'Series',    'icon' => 'bi-collection-play'],
];
?>
<div id="page-loader" class="page-loader d-none">
    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
    <span class="mt-2">Cargando...</span>
</div>

<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-tv me-2"></i>xtream-player
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($pages as $file => $page): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === $file ? 'active' : '' ?>"
                           href="<?= $file ?>">
                            <i class="bi <?= $page['icon'] ?> me-1"></i><?= $page['label'] ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form class="d-flex me-2 my-2 my-lg-0" action="search.php" method="get">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" name="q" placeholder="Buscar..."
                           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                           aria-label="Buscar">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i>Salir
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
(function() {
    var loader = document.getElementById('page-loader');
    if (loader) {
        loader.classList.remove('d-none');
    }
    window.addEventListener('DOMContentLoaded', function() {
        if (loader) {
            loader.classList.add('d-none');
        }
    });
    window.addEventListener('beforeunload', function() {
        if (loader) {
            loader.classList.remove('d-none');
        }
    });
})();
</script>
