<?php

declare(strict_types=1);

/*
 * Plaques signalétiques et étiquettes FICTIVES de la démo « La pièce par une photo » : une photo d'aspect réel par cas.
 *
 *   php bin/yack-demo-plaques.php            → public/yack/demo/plaques/plaque-<nom>-FICTIF.png
 *
 * Demande PHP avec GD et Chrome ou Chromium : chemin dans CHROME_BIN, sinon cherché aux emplacements usuels de macOS
 * et de Linux. Chaque plaque est une page HTML et SVG (étiquette aluminium ou argentée, police Barlow de
 * bin/yack-demo-plaques/ sous licence OFL, pictogrammes réglementaires génériques dessinés ici, code-barres fictif),
 * photographiée par Chrome sans interface : perspective, lumière, grain et carrosserie autour sont des effets CSS et
 * SVG à valeurs fixes (graines de bruit comprises), la même version de Chrome donne la même image. GD réduit
 * ensuite l'image à 256 couleurs tramées (environ 500 Ko au lieu de 1,2 Mo, sans écart visible), puis le script
 * écrit le bloc texte.
 *
 * Aucun logo de marque : la marque imprimée est du texte simple (YACK, ou MHI pour l'étiquette du constructeur du
 * catalogue de démo). Les valeurs électriques et frigorifiques sont fictives et vraisemblables. Chaque image porte
 * en bas la petite ligne « Spécimen de démonstration ».
 *
 * Les plaques lisibles portent, dans un bloc texte PNG (tEXt « YackDemoNameplate »), ce qui y est imprimé (n° de
 * série, référence, désignation, marque, `brand` à null quand aucune marque n'est imprimée) : c'est ce que lit
 * FakeNameplateReader, l'adapter de démonstration (YACK_AI_ADAPTER=fake). L'adapter réel lit l'image elle-même.
 * Chaque PNG reste sous PHOTO_BUDGET octets et PHOTO_SIDE pixels de côté : le navigateur l'envoie tel quel, sans
 * le réencoder (front/src/lib/sav/nameplate.ts, PHOTO_LIGHT_BYTES et PHOTO_MAX_SIDE), et le bloc texte arrive à l'API.
 *
 * Cas de « La pièce par une photo » (/sav) :
 * - numéro de série du parc installé (config/yack/fixtures_after_sales.yaml) : ouvre /sav/<n° de série> ;
 * - référence réelle du catalogue (data/yack/catalog.json, MHI SRK25ZS-W), numéro absent du parc : fiche produit ;
 * - plaque inconnue (numéro et référence absents) : « Appareil non trouvé » ;
 * - photo floue, sans bloc texte : « Plaque illisible ».
 *
 * Cas de la lecture des manuels et notices (écran de validation, sans recherche) :
 * - étiquette MHI SRK25ZS-W : marque lue sur la plaque, référence du catalogue et n° de série ;
 * - étiquette sans marque imprimée (sans-marque, référence SRK35ZS-W du catalogue) : marque déduite de la référence ;
 * - photo floue : tous les champs vides.
 */

if (!extension_loaded('gd')) {
    fwrite(STDERR, "L'extension GD est requise.\n");
    exit(1);
}

const MARKER = 'YackDemoNameplate';
const PHOTO_WIDTH = 1280;
const PHOTO_HEIGHT = 880;
const PHOTO_SIDE = 1600;
const PHOTO_BUDGET = 1_900_000;
const FONTS = __DIR__.'/yack-demo-plaques';

// Prises de vue : rotation (degrés) autour des axes x, y, z, échelle de la plaque, position de la lumière (%), support.
const SHOT_OUTDOOR = ['support' => 'outdoor', 'rx' => 7, 'ry' => -11, 'rz' => -1.4, 'scale' => 0.96, 'light' => [22, 12]];

$plates = [
    // Parc installé de la démo : un appareil par numéro de série.
    [
        'name' => 'YK25EVO08M001234', 'brand' => 'YACK', 'serial' => 'YK25EVO08M001234', 'kind' => 'Pompe à chaleur air/eau monobloc',
        'model' => 'PAC air/eau RHOSS R32', 'ref' => 'RHOSS-AIREAU-R32', 'service' => 'SC-EVO-M08', 'year' => '2025',
        'supply' => '230 V ~ 50 Hz', 'heat' => '8,0 kW', 'cool' => '7,1 kW', 'input' => '2,4 kW', 'current' => '14,5 A',
        'fluid' => 'R32', 'charge' => '1,30 kg', 'ip' => 'IPX4', 'weight' => '78 kg',
        'shot' => SHOT_OUTDOOR,
    ],
    [
        'name' => 'YK19KAZ35S004567', 'brand' => 'YACK', 'serial' => 'YK19KAZ35S004567', 'kind' => 'Climatiseur mural, unité intérieure',
        'model' => 'Mural Titane Wifi 2,5 kW', 'ref' => 'SRK25ZS-WFT', 'service' => 'SC-KAZ-I35', 'year' => '2019',
        'supply' => '230 V ~ 50 Hz', 'heat' => '3,2 kW', 'cool' => '2,5 kW', 'input' => '0,03 kW', 'current' => '0,2 A',
        'fluid' => 'R32', 'charge' => null, 'ip' => 'IPX0', 'weight' => '9 kg',
        'shot' => ['support' => 'indoor', 'rx' => -5, 'ry' => 9, 'rz' => 1.1, 'scale' => 0.93, 'light' => [78, 8]],
    ],
    [
        'name' => 'YK24HYD11S000321', 'brand' => 'YACK', 'serial' => 'YK24HYD11S000321', 'kind' => 'Pompe à chaleur air/eau split',
        'model' => 'Hydrolution', 'ref' => 'FDCW-VNX-A', 'service' => 'SC-HYD-O11', 'year' => '2024',
        'supply' => '400 V 3N ~ 50 Hz', 'heat' => '11,2 kW', 'cool' => '10,0 kW', 'input' => '3,1 kW', 'current' => '9,8 A',
        'fluid' => 'R32', 'charge' => '1,80 kg', 'ip' => 'IPX4', 'weight' => '96 kg',
        'shot' => ['support' => 'outdoor', 'rx' => 4, 'ry' => 8, 'rz' => 0.9, 'scale' => 0.95, 'light' => [70, 18]],
    ],
    [
        'name' => 'YK22THD200000789', 'brand' => 'YACK', 'serial' => 'YK22THD200000789', 'kind' => 'Chauffe-eau thermodynamique',
        'model' => 'Ballon monobloc 200 L', 'ref' => 'YORABTM200', 'service' => 'SC-THD-200', 'year' => '2022',
        'supply' => '230 V ~ 50 Hz', 'heat' => '1,7 kW', 'cool' => null, 'input' => '0,55 kW', 'current' => '9,5 A',
        'fluid' => 'R134a', 'charge' => '0,90 kg', 'ip' => 'IPX4', 'weight' => '70 kg',
        'shot' => ['support' => 'tank', 'rx' => 3, 'ry' => -7, 'rz' => -0.6, 'scale' => 0.94, 'light' => [30, 30]],
    ],
    // Référence réelle du catalogue, numéro absent du parc : la fiche produit est proposée.
    [
        'name' => 'SRK25ZS-W', 'brand' => 'MHI', 'serial' => 'MH21ZSW0004242', 'kind' => 'Climatiseur mural, unité intérieure',
        'model' => 'Mural 2,5 kW R32', 'ref' => 'SRK25ZS-W', 'service' => null, 'year' => '2021',
        'supply' => '220-240 V ~ 50 Hz', 'heat' => '3,2 kW', 'cool' => '2,5 kW', 'input' => '0,03 kW', 'current' => '0,2 A',
        'fluid' => 'R32', 'charge' => null, 'ip' => 'IPX0', 'weight' => '10 kg',
        'shot' => ['support' => 'indoor', 'rx' => 6, 'ry' => -8, 'rz' => -1.8, 'scale' => 0.94, 'light' => [25, 10]],
    ],
    // Absent du parc et du catalogue : « Appareil non trouvé ».
    [
        'name' => 'YK23EVO06M009999', 'brand' => 'YACK', 'serial' => 'YK23EVO06M009999', 'kind' => 'Pompe à chaleur air/eau monobloc',
        'model' => 'Evolys monobloc 6 kW', 'ref' => 'YK-PAE-M06', 'service' => 'SC-EVO-M06', 'year' => '2023',
        'supply' => '230 V ~ 50 Hz', 'heat' => '6,0 kW', 'cool' => '5,4 kW', 'input' => '1,8 kW', 'current' => '11,0 A',
        'fluid' => 'R32', 'charge' => '1,10 kg', 'ip' => 'IPX4', 'weight' => '62 kg',
        'shot' => ['support' => 'outdoor', 'rx' => -4, 'ry' => -9, 'rz' => 1.6, 'scale' => 0.95, 'light' => [40, 6]],
    ],
    // Étiquette sans marque imprimée (la marque est sur la carrosserie) : la marque est déduite de la référence.
    [
        'name' => 'sans-marque', 'brand' => null, 'serial' => 'MH22ZSW0003535', 'kind' => 'Climatiseur mural, unité intérieure',
        'model' => 'Mural 3,5 kW R32', 'ref' => 'SRK35ZS-W', 'service' => null, 'year' => '2022',
        'supply' => '220-240 V ~ 50 Hz', 'heat' => '4,0 kW', 'cool' => '3,5 kW', 'input' => '0,04 kW', 'current' => '0,3 A',
        'fluid' => 'R32', 'charge' => null, 'ip' => 'IPX0', 'weight' => '10 kg',
        'shot' => ['support' => 'indoor', 'rx' => -3, 'ry' => 11, 'rz' => 0.7, 'scale' => 0.93, 'light' => [65, 14]],
    ],
];

$chrome = chrome();
$directory = dirname(__DIR__).'/public/yack/demo/plaques';
if (!is_dir($directory) && !mkdir($directory, 0o775, true)) {
    fwrite(STDERR, "Impossible de créer $directory\n");
    exit(1);
}
$work = sys_get_temp_dir().'/yack-demo-plaques-'.getmypid();
if (!mkdir($work, 0o700) && !is_dir($work)) {
    fwrite(STDERR, "Impossible de créer $work\n");
    exit(1);
}

foreach ($plates as $plate) {
    $printed = ['serial' => $plate['serial'], 'reference' => $plate['ref'], 'model' => $plate['model'], 'brand' => $plate['brand']];
    save($directory, $plate['name'], withMarker(photograph($chrome, $work, $plate['name'], scene($plate, false)), $printed));
}

// Photo floue (bougé, reflet du flash) : sans bloc texte, illisible pour tous les adapters.
$blurred = $plates[2];
$blurred['shot'] = ['support' => 'outdoor', 'rx' => 10, 'ry' => 14, 'rz' => 3.5, 'scale' => 1.02, 'light' => [58, 32]];
save($directory, 'illisible', photograph($chrome, $work, 'illisible', scene($blurred, true)));

array_map('unlink', glob($work.'/*') ?: []);
rmdir($work);

function chrome(): string
{
    $candidates = array_filter([
        getenv('CHROME_BIN') ?: null,
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
    ]);
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    fwrite(STDERR, "Chrome ou Chromium introuvable : indiquez son chemin dans CHROME_BIN.\n");
    exit(1);
}

/** Screenshot of the scene by headless Chrome, reduced by GD to 256 dithered colours. */
function photograph(string $chrome, string $work, string $name, string $html): string
{
    $page = "$work/$name.html";
    $shot = "$work/$name.png";
    file_put_contents($page, $html);
    $command = [
        $chrome, '--headless=new', '--disable-gpu', '--hide-scrollbars', '--mute-audio', '--no-first-run',
        '--force-device-scale-factor=1', '--force-color-profile=srgb', '--virtual-time-budget=3000',
        '--window-size='.PHOTO_WIDTH.','.PHOTO_HEIGHT, '--screenshot='.$shot, 'file://'.$page,
    ];
    if (function_exists('posix_getuid') && 0 === posix_getuid()) {
        array_splice($command, 1, 0, ['--no-sandbox']);
    }
    exec(implode(' ', array_map('escapeshellarg', $command)).' 2>/dev/null', $output, $status);
    if (0 !== $status || !is_file($shot)) {
        fwrite(STDERR, "Chrome n'a pas produit $shot\n");
        exit(1);
    }

    $image = imagecreatefrompng($shot);
    if (false === $image) {
        fwrite(STDERR, "GD ne lit pas $shot\n");
        exit(1);
    }
    imagetruecolortopalette($image, true, 256);
    ob_start();
    imagepng($image, null, 9);

    return (string) ob_get_clean();
}

function save(string $directory, string $name, string $png): void
{
    $size = getimagesizefromstring($png);
    if (false === $size || max($size[0], $size[1]) > PHOTO_SIDE || strlen($png) > PHOTO_BUDGET) {
        fwrite(STDERR, "plaque-$name : image hors limites (".PHOTO_SIDE.' px, '.PHOTO_BUDGET." octets)\n");
        exit(1);
    }
    $path = sprintf('%s/plaque-%s-FICTIF.png', $directory, $name);
    file_put_contents($path, $png);
    printf("%s  %d x %d, %d Ko\n", substr($path, strlen(dirname(__DIR__)) + 1), $size[0], $size[1], intdiv(strlen($png), 1024));
}

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** @param array<string, mixed> $plate */
function scene(array $plate, bool $blurred): string
{
    /** @var array{support: string, rx: float|int, ry: float|int, rz: float|int, scale: float, light: array{int, int}} $shot */
    $shot = $plate['shot'];
    $riveted = 'outdoor' === $shot['support'];
    [$lx, $ly] = $shot['light'];
    $transform = sprintf('perspective(1700px) rotateX(%sdeg) rotateY(%sdeg) rotateZ(%sdeg) scale(%s)', $shot['rx'], $shot['ry'], $shot['rz'], $shot['scale']);

    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>'.fontFaces().css().'</style></head><body>'
        .'<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>'
        .'<filter id="brosse" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency="0.0025 0.9" numOctaves="2" seed="11"/><feColorMatrix type="saturate" values="0"/></filter>'
        .'<filter id="grain" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency="0.95" numOctaves="1" seed="5"/><feColorMatrix type="saturate" values="0"/></filter>'
        .'<filter id="peinture" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency="0.35" numOctaves="3" seed="23"/><feColorMatrix type="saturate" values="0"/></filter>'
        .'<filter id="salissure" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency="0.012" numOctaves="4" seed="31"/><feColorMatrix type="matrix" values="0 0 0 0 .35  0 0 0 0 .32  0 0 0 0 .28  0 0 0 -1.6 1.15"/></filter>'
        .'<filter id="bouge" x="-10%" y="-10%" width="120%" height="120%"><feGaussianBlur stdDeviation="17 6"/></filter>'
        .'</defs></svg>'
        .'<main class="scene scene--'.e($shot['support']).($blurred ? ' scene--bouge' : '').'">'
        .support($shot['support'])
        .'<div class="plaque'.($riveted ? ' plaque--rivetee' : ' plaque--etiquette').'" style="transform:'.$transform.'">'
        .'<svg class="plaque__brosse" aria-hidden="true"><rect width="100%" height="100%" filter="url(#brosse)"/></svg>'
        .'<svg class="plaque__salissure" aria-hidden="true"><rect width="100%" height="100%" filter="url(#salissure)"/></svg>'
        .'<div class="plaque__reflet" style="background-position:'.$lx.'% '.$ly.'%"></div>'
        .label($plate)
        .($riveted ? '<i class="rivet rivet--hg"></i><i class="rivet rivet--hd"></i><i class="rivet rivet--bg"></i><i class="rivet rivet--bd"></i>' : '')
        .'</div>'
        .'<div class="lumiere" style="background:radial-gradient(ellipse 85% 75% at '.$lx.'% '.$ly.'%, rgba(255,247,232,.30), rgba(255,247,232,0) 62%), linear-gradient('.($lx < 50 ? 150 : 210).'deg, rgba(0,0,0,0) 45%, rgba(25,22,18,.16) 100%), radial-gradient(ellipse 125% 105% at 50% 45%, rgba(0,0,0,0) 58%, rgba(20,18,16,.34) 100%)"></div>'
        .($blurred ? '<div class="flash"></div>' : '')
        .'<svg class="grain" aria-hidden="true"><rect width="100%" height="100%" filter="url(#grain)"/></svg>'
        .'</main>'
        .($blurred ? '<p class="legende">Spécimen de démonstration</p>' : '')
        .'</body></html>';
}

function support(string $support): string
{
    return match ($support) {
        // Carrosserie de tôle peinte d'une unité extérieure : grille d'aération à droite, pli de tôle, vis
        'outdoor' => '<div class="tole"><svg class="tole__peinture" aria-hidden="true"><rect width="100%" height="100%" filter="url(#peinture)"/></svg>'
            .'<div class="tole__pli"></div><div class="tole__grille"></div><i class="vis vis--1"></i><i class="vis vis--2"></i></div>',
        // Flanc d'un ballon : cylindre émaillé blanc
        'tank' => '<div class="cuve"><div class="cuve__joint"></div></div>',
        // Flanc d'une unité murale : plastique blanc légèrement bombé
        default => '<div class="coque"><div class="coque__bord"></div></div>',
    };
}

/** @param array<string, mixed> $plate */
function label(array $plate): string
{
    $brand = $plate['brand'];
    $vendor = null === $plate['service'];
    $head = null === $brand
        ? '<div class="tete__marque tete__marque--titre">Climatiseur</div><div class="tete__droite">Unité intérieure<br>Indoor unit</div>'
        : '<div class="tete__marque">'.e($brand).'</div><div class="tete__droite">'.($vendor ? 'Air conditioner<br>Climatiseur' : 'Génie climatique<br>Heating &amp; cooling').'</div>';

    $ident = $vendor
        ? row('Model No.', $plate['ref'], 'fort').row('Désignation', $plate['model'])
        : row('Désignation / Model', $plate['model'], 'fort').row('Réf. / Ref.', $plate['ref']).row('Code service', (string) $plate['service']);

    $electric = row('Alimentation', $plate['supply'])
        .row('Puissance calorifique', $plate['heat'])
        .(null === $plate['cool'] ? row('Appoint électrique', '1,8 kW') : row('Puissance frigorifique', $plate['cool']))
        .row('Puissance absorbée', $plate['input'])
        .row('Intensité maximale', $plate['current'])
        .row('Indice de protection', $plate['ip']);

    $gwp = 'R134a' === $plate['fluid'] ? 1430 : 675;
    $cold = null === $plate['charge']
        ? row('Fluide frigorigène', $plate['fluid']).row('Charge', 'voir unité ext.').row('PRP (GWP)', (string) $gwp).row('Masse nette', $plate['weight']).row('Fabrication', $plate['year'])
        : row('Fluide frigorigène', $plate['fluid']).row('Charge usine', $plate['charge']).row('PRP (GWP)', (string) $gwp)
            .row('Équivalent CO2', number_format((float) str_replace(',', '.', $plate['charge']) * $gwp / 1000, 2, ',', '').' t')
            .row('Pression max. HP / BP', 'R134a' === $plate['fluid'] ? '2,50 / 1,20 MPa' : '4,15 / 1,60 MPa')
            .row('Masse nette', $plate['weight']).row('Fabrication', $plate['year']);

    $fgas = null === $plate['charge']
        ? 'Raccordé à une unité extérieure. Fluide frigorigène'.('R32' === $plate['fluid'] ? ' A2L, légèrement inflammable.' : '.')
        : 'Contient des gaz à effet de serre fluorés. Équipement hermétiquement scellé.'.('R32' === $plate['fluid'] ? ' Fluide A2L, légèrement inflammable.' : '');

    $pictos = picto('ce').picto('deee').('IPX4' === $plate['ip'] ? picto('ipx4') : '').('R32' === $plate['fluid'] ? picto('a2l') : '').picto('notice');

    return '<div class="etiquette">'
        .'<header class="tete">'.$head.'</header>'
        .'<p class="type">'.e($plate['kind']).'</p>'
        .'<section class="ident"><dl>'.$ident.'</dl></section>'
        .'<section class="serie"><div class="serie__texte"><span class="serie__libelle">N° de série / Serial No.</span><span class="serie__numero">'.e($plate['serial']).'</span></div>'
        .barcode($plate['serial']).'</section>'
        .'<section class="tableau"><dl>'.$electric.'</dl><dl>'.$cold.'</dl></section>'
        .'<p class="fgaz">'.e($fgas).'</p>'
        .'<footer class="pied"><div class="pictos">'.$pictos.'</div><div class="pied__droite">'
        .('YACK' === $brand ? 'Fabriqué en France<br>' : '').'Ne pas retirer cette plaque.</div></footer>'
        .'<p class="specimen">Spécimen de démonstration</p>'
        .'</div>';
}

function row(string $label, string $value, string $class = ''): string
{
    return '<div class="ligne'.('' === $class ? '' : ' ligne--'.$class).'"><dt>'.e($label).'</dt><dd>'.e($value).'</dd></div>';
}

/** Fictitious barcode: bars drawn from the serial number (deterministic), not a real symbology. */
function barcode(string $serial): string
{
    $modules = [[1, 1], [1, 1], [2, 1]]; // start guard
    foreach (str_split($serial) as $i => $char) {
        $code = crc32($char.$i);
        for ($k = 0; $k < 3; ++$k) {
            $modules[] = [1 + (($code >> (3 * $k)) & 3) % 3, 1 + (($code >> (3 * $k + 2)) & 1)];
        }
    }
    array_push($modules, [2, 1], [1, 1], [1, 2]); // stop guard
    $x = 0;
    $bars = '';
    foreach ($modules as [$bar, $space]) {
        $bars .= sprintf('<rect x="%d" width="%d" height="60"/>', $x, $bar);
        $x += $bar + $space;
    }

    return sprintf('<svg class="code-barres" viewBox="0 0 %d 60" preserveAspectRatio="none" aria-hidden="true"><g fill="currentColor">%s</g></svg>', $x, $bars);
}

/** Generic regulatory pictograms, drawn here (monochrome, printed with the ink of the label). */
function picto(string $name): string
{
    $svg = match ($name) {
        // Marquage CE : deux demi-cercles de même rayon, le E barré au milieu
        'ce' => '<svg viewBox="0 0 64 40"><path d="M27 4a16 16 0 1 0 0 32" fill="none" stroke="currentColor" stroke-width="5"/><path d="M57 4a16 16 0 1 0 0 32M43 20h12" fill="none" stroke="currentColor" stroke-width="5"/></svg>',
        // Poubelle barrée sur roues et trait plein dessous (collecte séparée des équipements électriques)
        'deee' => '<svg viewBox="0 0 40 48"><g fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"><path d="M10 11h20l-2 25H12z"/><path d="M8 11h24M16 11V8h8v3M15 17v14M20 17v14M25 17v14"/></g><circle cx="13" cy="38.5" r="2.2" fill="currentColor"/><circle cx="27" cy="38.5" r="2.2" fill="currentColor"/><path d="M5 5l30 37M35 5L5 42" stroke="currentColor" stroke-width="2.6"/><rect x="5" y="44" width="30" height="4" fill="currentColor"/></svg>',
        // Protection contre les projections d'eau : goutte dans un triangle
        'ipx4' => '<svg viewBox="0 0 48 44"><path d="M24 3L45 41H3z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M24 16c-3 5-6 9-6 13a6 6 0 0 0 12 0c0-4-3-8-6-13z" fill="currentColor"/></svg>',
        // Avertissement fluide inflammable (A2L) : flamme dans un triangle
        'a2l' => '<svg viewBox="0 0 48 44"><path d="M24 3L45 41H3z" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linejoin="round"/><path d="M24 15c1 5 7 8 7 15a7 7 0 0 1-14 0c0-3 2-5 3-7 0 3 1 4 2 4-1-4 0-8 2-12z" fill="currentColor"/><rect x="15" y="37" width="18" height="2" fill="currentColor"/></svg>',
        // Lire la notice : livre ouvert et « i »
        default => '<svg viewBox="0 0 44 44"><rect x="2" y="2" width="40" height="40" rx="5" fill="currentColor"/><path d="M8 13c5-2 10-1 14 2 4-3 9-4 14-2v19c-5-2-10-1-14 2-4-3-9-4-14-2z" fill="none" stroke="#fff" stroke-width="2.4" stroke-linejoin="round"/><path d="M22 15v19" stroke="#fff" stroke-width="2.4"/></svg>',
    };

    return '<span class="picto picto--'.$name.'">'.$svg.'</span>';
}

function fontFaces(): string
{
    $faces = '';
    foreach (['Barlow' => 'barlow', 'Barlow Condensed' => 'barlow-condensed'] as $family => $file) {
        foreach ([500, 600, 700] as $weight) {
            $data = base64_encode((string) file_get_contents(sprintf('%s/%s-latin-%d-normal.woff2', FONTS, $file, $weight)));
            $faces .= sprintf("@font-face{font-family:'%s';font-weight:%d;src:url(data:font/woff2;base64,%s) format('woff2')}", $family, $weight, $data);
        }
    }

    return $faces;
}

function css(): string
{
    return <<<'CSS'
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { width: 1280px; height: 880px; overflow: hidden; background: #8a8a86; }
.scene { position: relative; width: 1280px; height: 880px; overflow: hidden; display: grid; place-items: center; }

/* Supports */
.scene--outdoor .plaque { margin-right: 190px; width: 900px; }
.tole { position: absolute; inset: 0; background: linear-gradient(100deg, #cfcdc6 0%, #dddbd4 38%, #d3d1ca 70%, #c3c1ba 100%); }
.tole__peinture { position: absolute; inset: 0; width: 100%; height: 100%; opacity: .07; mix-blend-mode: multiply; }
.tole__pli { position: absolute; top: 0; bottom: 0; left: 1084px; width: 14px; background: linear-gradient(90deg, rgba(255,255,255,.55), rgba(0,0,0,.18) 60%, rgba(0,0,0,0)); }
.tole__grille { position: absolute; top: 0; bottom: 0; left: 1110px; right: 0; box-shadow: inset 6px 0 8px rgba(0,0,0,.35);
  background: repeating-linear-gradient(180deg, #2c2b29 0 5px, #5f5d58 5px 7px, #ebe9e2 7px 9px, #d2d0c9 9px 15px, #b7b5ae 15px 20px, #8d8b85 20px 22px); }
.vis { position: absolute; width: 20px; height: 20px; border-radius: 50%; background: radial-gradient(circle at 35% 35%, #f2f1ec, #9b9a95 60%, #6c6b67); box-shadow: 0 1px 2px rgba(0,0,0,.35); }
.vis::after { content: ''; position: absolute; left: 4px; right: 4px; top: 9px; height: 2px; background: #55544f; transform: rotate(28deg); }
.vis--1 { left: 1052px; top: 40px; } .vis--2 { left: 1052px; top: 818px; }
.coque { position: absolute; inset: 0; background: linear-gradient(180deg, #d5d8d7 0%, #e9ebea 14%, #f1f2f1 34%, #e8eae9 62%, #cfd2d1 100%); }
.coque::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 26px; background: linear-gradient(180deg, #a9adac, rgba(169,173,172,0)); }
.coque__bord { position: absolute; left: 0; right: 0; bottom: 0; height: 70px; background: linear-gradient(180deg, rgba(0,0,0,0), rgba(0,0,0,.10) 55%, rgba(255,255,255,.4) 56%, rgba(0,0,0,.12) 100%); }
.cuve { position: absolute; inset: 0; background: linear-gradient(90deg, #b9bcbc 0%, #e6e8e7 18%, #f6f7f6 40%, #eceeed 62%, #c9cccb 88%, #a9acab 100%); }
.cuve__joint { position: absolute; left: 0; right: 0; top: 42px; height: 6px; background: linear-gradient(180deg, rgba(0,0,0,.18), rgba(255,255,255,.6)); }

/* Plaque */
.plaque { position: relative; width: 940px; border-radius: 7px; overflow: hidden; color: #1c1d20;
  background: linear-gradient(115deg, #b9bcbf 0%, #d7d9db 22%, #c6c9cc 46%, #dfe1e3 64%, #c0c3c6 100%);
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.7), 4px 7px 12px rgba(30,28,24,.34), 1px 2px 2px rgba(30,28,24,.3); }
.plaque--etiquette { background: linear-gradient(115deg, #c9ccce 0%, #e2e4e5 26%, #d1d4d6 50%, #e6e7e8 70%, #cbced0 100%); border-radius: 4px;
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.16), 0 1px 1px rgba(30,28,24,.22), 1px 2px 4px rgba(30,28,24,.12); }
.plaque__brosse { position: absolute; inset: 0; width: 100%; height: 100%; opacity: .22; mix-blend-mode: multiply; }
.plaque--etiquette .plaque__brosse { opacity: .10; }
.plaque__salissure { position: absolute; inset: 0; width: 100%; height: 100%; opacity: .09; mix-blend-mode: multiply; }
.plaque--etiquette .plaque__salissure { opacity: .05; }
.plaque::after { content: ''; position: absolute; inset: 0; border-radius: inherit; box-shadow: inset 0 0 18px rgba(60,55,45,.22); pointer-events: none; }
.plaque__reflet { position: absolute; inset: -20%; background: linear-gradient(105deg, rgba(255,255,255,0) 30%, rgba(255,255,255,.38) 44%, rgba(255,255,255,0) 58%); background-size: 160% 160%; mix-blend-mode: screen; }
.rivet { position: absolute; width: 18px; height: 18px; border-radius: 50%; background: radial-gradient(circle at 38% 34%, #f7f7f5, #a4a6a8 55%, #6e7072); box-shadow: 0 1px 1px rgba(0,0,0,.45), inset 0 -1px 1px rgba(0,0,0,.25); }
.rivet--hg { left: 14px; top: 14px; } .rivet--hd { right: 14px; top: 14px; } .rivet--bg { left: 14px; bottom: 14px; } .rivet--bd { right: 14px; bottom: 14px; }

/* Impression */
.etiquette { position: relative; padding: 30px 44px 22px; font-family: 'Barlow', sans-serif; font-weight: 500; mix-blend-mode: multiply; color: rgba(22, 23, 26, .93); display: flex; flex-direction: column; }
.tete { display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 8px; border-bottom: 3px solid currentColor; }
.tete__marque { font-family: 'Barlow Condensed', sans-serif; font-weight: 700; font-size: 58px; line-height: .9; letter-spacing: .04em; }
.tete__marque--titre { font-size: 40px; letter-spacing: .06em; text-transform: uppercase; }
.tete__droite { font-family: 'Barlow Condensed', sans-serif; font-weight: 600; font-size: 17px; line-height: 1.15; letter-spacing: .08em; text-transform: uppercase; text-align: right; }
.type { font-family: 'Barlow Condensed', sans-serif; font-weight: 600; font-size: 21px; letter-spacing: .03em; padding: 6px 0 6px; border-bottom: 1px solid currentColor; }
dl { display: grid; }
.ligne { display: grid; grid-template-columns: 210px 1fr; align-items: baseline; padding: 2px 0; }
dt { font-size: 15px; letter-spacing: .02em; }
dd { font-family: 'Barlow Condensed', sans-serif; font-weight: 600; font-size: 20px; letter-spacing: .02em; }
.ligne--fort dd { font-weight: 700; font-size: 25px; }
.ident { padding: 6px 0 4px; }
.serie { display: flex; align-items: center; gap: 22px; padding: 6px 0 10px; border-bottom: 1px solid currentColor; }
.serie__texte { display: flex; flex-direction: column; border: 2px solid currentColor; padding: 3px 14px 4px; border-radius: 3px; }
.serie__libelle { font-size: 13px; letter-spacing: .06em; text-transform: uppercase; }
.serie__numero { font-family: 'Barlow Condensed', sans-serif; font-weight: 700; font-size: 40px; line-height: 1.05; letter-spacing: .07em; }
.code-barres { flex: 1; height: 58px; color: inherit; }
.tableau { display: grid; grid-template-columns: 1fr 1fr; column-gap: 26px; padding: 6px 0 4px; border-bottom: 1px solid currentColor; }
.tableau .ligne { grid-template-columns: 1fr auto; border-bottom: 1px solid rgba(22,23,26,.25); padding: 1px 0; }
.tableau dt { font-size: 14px; } .tableau dd { font-size: 17px; text-align: right; }
.fgaz { font-size: 13px; padding: 5px 0 0; letter-spacing: .01em; }
.pied { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 22px; }
.pictos { display: flex; align-items: flex-end; gap: 14px; }
.picto svg { display: block; height: 40px; width: auto; }
.picto--ce svg { height: 30px; } .picto--notice svg { height: 34px; }
.pied__droite { font-size: 13px; text-align: right; line-height: 1.3; }
.specimen { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; opacity: .7; padding-top: 4px; }

/* Photo */
.lumiere, .flash, .grain { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
.grain { opacity: .11; mix-blend-mode: overlay; }
.scene { filter: blur(.45px) contrast(1.03) saturate(.92) sepia(.06); }
.scene--bouge { filter: url(#bouge) contrast(.92) brightness(1.04); transform: scale(1.06); }
.flash { background: radial-gradient(ellipse 38% 34% at 56% 40%, rgba(255,255,255,.92), rgba(255,255,255,.55) 40%, rgba(255,255,255,0) 75%); mix-blend-mode: screen; }
.legende { position: absolute; left: 28px; bottom: 18px; font: 600 13px 'Barlow', sans-serif; letter-spacing: .08em; text-transform: uppercase; color: rgba(30,30,30,.7); }
CSS;
}

/**
 * Inserts the tEXt chunk MARKER (JSON of what is printed) right after IHDR, before the image data.
 *
 * @param array{serial: string, reference: string, model: string, brand: ?string} $printed
 */
function withMarker(string $png, array $printed): string
{
    $data = MARKER."\0".json_encode($printed, JSON_THROW_ON_ERROR);
    $chunk = pack('N', strlen($data)).'tEXt'.$data.pack('N', crc32('tEXt'.$data));
    $afterHeader = 8 + 8 + 13 + 4; // signature, then IHDR: length, type, 13 bytes of data, CRC

    return substr($png, 0, $afterHeader).$chunk.substr($png, $afterHeader);
}
