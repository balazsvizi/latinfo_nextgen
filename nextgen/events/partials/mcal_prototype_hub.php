<?php
declare(strict_types=1);

/**
 * Admin hub: mobil naptár prototípusok választója.
 *
 * @var array<string, array{id: string, letter: string, title: string, lead: string, hint: string}> $layouts
 */
$liveMcalUrl = events_public_home_url('hu', ['view' => 'mcal']);
?>
<div class="card mcal-proto-hub">
    <h1 class="mcal-proto-hub__title">Mobil naptár minták</h1>
    <p class="mcal-proto-hub__lead">Három kipróbálható elrendezés a publikus mobil naptárhoz. Csak bejelentkezett adminnak látszanak, a nyilvános oldalt nem módosítják. Érdemes telefonon vagy a böngésző mobilos nézetében (kb. 375×812) nézni.</p>
    <p class="mcal-proto-hub__compare">
        <a href="<?= h($liveMcalUrl) ?>" target="_blank" rel="noopener noreferrer">Jelenlegi élő mobil naptár</a>
        <span aria-hidden="true">·</span>
        kontroll, görgetés nélküli 1–2 esemény
    </p>
    <ol class="mcal-proto-hub__grid">
        <?php foreach ($layouts as $item): ?>
            <li class="mcal-proto-hub__card">
                <p class="mcal-proto-hub__letter"><?= h($item['letter']) ?></p>
                <h2 class="mcal-proto-hub__name"><?= h($item['title']) ?></h2>
                <p class="mcal-proto-hub__text"><?= h($item['lead']) ?></p>
                <p class="mcal-proto-hub__hint"><?= h($item['hint']) ?></p>
                <a class="btn btn-primary" href="<?= h(mcal_prototype_page_url($item['id'])) ?>">Megnyitás</a>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
