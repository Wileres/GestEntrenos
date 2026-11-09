<div class="page-head"><h1>Passa llista</h1><?php if (is_admin()): ?><a class="btn btn-secondary" href="<?= e(url('/calendari')) ?>"><?= icon('calendar') ?>Calendari</a><?php endif; ?></div>
<section class="card">
    <h2>Avui</h2>
    <?php \App\Helpers\View::component('session_list', ['items' => $today, 'empty' => 'Avui no hi ha cap entrenament.']); ?>
</section>
<div class="grid grid-2">
    <section class="card"><h2>Pròxims dies</h2>
        <?php \App\Helpers\View::component('session_list', ['items' => $upcoming, 'empty' => 'No hi ha entrenaments previstos les properes dues setmanes.']); ?>
    </section>
    <section class="card"><h2>Darrers entrenaments</h2>
        <?php \App\Helpers\View::component('session_list', ['items' => $recent, 'empty' => 'Encara no hi ha entrenaments passats.']); ?>
    </section>
</div>
