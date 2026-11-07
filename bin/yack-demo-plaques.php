<?php

declare(strict_types=1);

/*
 * Plaques signalétiques et étiquettes FICTIVES de la démo « La pièce par une photo », dessinées avec GD
 * (polices intégrées, sans fichier de police : même résultat sur toute machine qui a la même version de GD).
 *
 *   php bin/yack-demo-plaques.php            → public/yack/demo/plaques/plaque-<nom>-FICTIF.png
 *
 * Chaque image porte la mention « SPÉCIMEN FICTIF, DÉMO PURJUS ». Les plaques lisibles portent aussi, dans un
 * bloc texte PNG (tEXt « YackDemoNameplate »), ce qui y est imprimé : c'est ce que lit FakeNameplateReader,
 * l'adapter de démonstration (YACK_AI_ADAPTER=fake). L'adapter réel lit l'image elle-même.
 *
 * Les quatre cas de la démo :
 * - numéro de série du parc installé (config/yack/fixtures_after_sales.yaml) : ouvre /sav/<n° de série> ;
 * - référence réelle du catalogue (data/yack/catalog.json, MHI SRK25ZS-W), numéro absent du parc : fiche produit ;
 * - plaque inconnue (numéro et référence absents) : « Appareil non trouvé » ;
 * - photo floue, sans bloc texte : « Plaque illisible ».
 */

if (!extension_loaded('gd')) {
    fwrite(STDERR, "L'extension GD est requise.\n");
    exit(1);
}

const MARKER = 'YackDemoNameplate';
const WIDTH = 480;
const HEIGHT = 300;
const SCALE = 2;

$plates = [
    // Parc installé de la démo : un appareil par numéro de série.
    ['name' => 'YK25EVO08M001234', 'brand' => 'YACK', 'serial' => 'YK25EVO08M001234', 'kind' => 'PAC air/eau monobloc', 'model' => 'PAC air/eau RHOSS R32', 'ref' => 'RHOSS-AIREAU-R32', 'service' => 'SC-EVO-M08', 'specs' => '230 V ~ 50 Hz   R32 : 1,30 kg   IPX4', 'year' => '2025'],
    ['name' => 'YK19KAZ35S004567', 'brand' => 'YACK', 'serial' => 'YK19KAZ35S004567', 'kind' => 'Mural, unité intérieure', 'model' => 'Mural Titane Wifi 2,5 kW', 'ref' => 'SRK25ZS-WFT', 'service' => 'SC-KAZ-I35', 'specs' => '230 V ~ 50 Hz   R32 : 0,75 kg   IPX0', 'year' => '2019'],
    ['name' => 'YK24HYD11S000321', 'brand' => 'YACK', 'serial' => 'YK24HYD11S000321', 'kind' => 'PAC air/eau split', 'model' => 'Hydrolution', 'ref' => 'FDCW-VNX-A', 'service' => 'SC-HYD-O11', 'specs' => '400 V 3N ~ 50 Hz   R32 : 1,80 kg   IPX4', 'year' => '2024'],
    ['name' => 'YK22THD200000789', 'brand' => 'YACK', 'serial' => 'YK22THD200000789', 'kind' => 'Chauffe-eau thermodynamique', 'model' => 'Ballon monobloc 200 L', 'ref' => 'YORABTM200', 'service' => 'SC-THD-200', 'specs' => '230 V ~ 50 Hz   R134a : 0,90 kg   IPX4', 'year' => '2022'],
    // Référence réelle du catalogue, numéro absent du parc : la fiche produit est proposée.
    ['name' => 'SRK25ZS-W', 'brand' => 'MHI', 'serial' => 'MH21ZSW0004242', 'kind' => 'Mural, unité intérieure', 'model' => 'Mural 2,5 kW R32', 'ref' => 'SRK25ZS-W', 'service' => null, 'specs' => '230 V ~ 50 Hz   R32   IPX0', 'year' => '2021'],
    // Absent du parc et du catalogue : « Appareil non trouvé ».
    ['name' => 'YK23EVO06M009999', 'brand' => 'YACK', 'serial' => 'YK23EVO06M009999', 'kind' => 'PAC air/eau monobloc', 'model' => 'Evolys monobloc 6 kW', 'ref' => 'YK-PAE-M06', 'service' => 'SC-EVO-M06', 'specs' => '230 V ~ 50 Hz   R32 : 1,10 kg   IPX4', 'year' => '2023'],
];

$directory = dirname(__DIR__).'/public/yack/demo/plaques';
if (!is_dir($directory) && !mkdir($directory, 0o775, true)) {
    fwrite(STDERR, "Impossible de créer $directory\n");
    exit(1);
}

foreach ($plates as $plate) {
    $printed = ['serial' => $plate['serial'], 'reference' => $plate['ref'], 'model' => $plate['model']];
    save($directory, $plate['name'], withMarker(png(draw($plate)), $printed));
}

// Photo floue (bougé, reflet) : sans bloc texte, illisible pour tous les adapters.
$blurred = draw($plates[2]);
imagefilter($blurred, IMG_FILTER_PIXELATE, 7, true);
for ($i = 0; $i < 40; ++$i) {
    imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
}
imagefilter($blurred, IMG_FILTER_BRIGHTNESS, 40);
notice($blurred);
save($directory, 'illisible', png($blurred));

function save(string $directory, string $name, string $png): void
{
    $path = sprintf('%s/plaque-%s-FICTIF.png', $directory, $name);
    file_put_contents($path, $png);
    echo substr($path, strlen(dirname(__DIR__)) + 1), "\n";
}

/** @param array{name: string, brand: string, serial: string, kind: string, model: string, ref: string, service: ?string, specs: string, year: string} $plate */
function draw(array $plate): GdImage
{
    $image = imagecreatetruecolor(WIDTH, HEIGHT);
    $metal = imagecolorallocate($image, 0xE4, 0xE6, 0xE9);
    $ink = imagecolorallocate($image, 0x1B, 0x1C, 0x2A);
    $muted = imagecolorallocate($image, 0x55, 0x58, 0x66);
    $white = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);

    imagefill($image, 0, 0, $metal);
    imagerectangle($image, 0, 0, WIDTH - 1, HEIGHT - 1, $ink);
    imagerectangle($image, 3, 3, WIDTH - 4, HEIGHT - 4, $muted);

    // En-tête : marque
    imagefilledrectangle($image, 4, 4, WIDTH - 5, 41, $ink);
    big($image, 14, 9, $plate['brand'], 5, 2, $white);
    text($image, WIDTH - 14 - 8 * strlen(latin('Génie climatique')), 15, 'Génie climatique', 4, $white);

    text($image, 14, 50, $plate['kind'], 4, $ink);
    if (null === $plate['service']) {
        // Étiquette constructeur : la référence du modèle en évidence, sans code service Yack.
        text($image, 14, 70, 'Désignation : '.$plate['model'], 4, $ink);
        text($image, 14, 90, 'Model No. : '.$plate['ref'], 5, $ink);
    } else {
        text($image, 14, 70, 'Désignation / Model : '.$plate['model'], 5, $ink);
        text($image, 14, 92, sprintf('Réf. : %s      Code service : %s', $plate['ref'], $plate['service']), 3, $ink);
    }

    // Numéro de série, en grand, dans un cadre
    text($image, 14, 116, 'N° de série / Serial No.', 3, $muted);
    imagerectangle($image, 12, 132, WIDTH - 13, 170, $ink);
    big($image, 22, 136, $plate['serial'], 5, 2, $ink);

    text($image, 14, 180, $plate['specs'], 3, $ink);
    text($image, 14, 198, 'Fabrication : '.$plate['year'].'      Fabriqué en France (fictif)', 3, $ink);
    text($image, 14, 216, 'Ne pas retirer cette plaque. Conserver pour le SAV.', 2, $muted);

    notice($image);

    return $image;
}

/** Mention of the demonstration data, on every image (a blurred photo too: written after the blur). */
function notice(GdImage $image): void
{
    $red = imagecolorallocate($image, 0xC6, 0x1F, 0x2E);
    $white = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);
    imagefilledrectangle($image, 4, HEIGHT - 50, WIDTH - 5, HEIGHT - 5, $red);
    $notice = 'SPÉCIMEN FICTIF, DÉMO PURJUS';
    big($image, (int) ((WIDTH - imagefontwidth(4) * 2 * strlen(latin($notice))) / 2), HEIGHT - 43, $notice, 4, 2, $white);
}

function png(GdImage $image): string
{
    $scaled = imagescale($image, WIDTH * SCALE, HEIGHT * SCALE, IMG_NEAREST_NEIGHBOUR);
    ob_start();
    imagepng(false === $scaled ? $image : $scaled, null, 9);

    return (string) ob_get_clean();
}

/** GD built-in fonts are ISO-8859-2: É, é, ° are in it, à and è are not (texts avoid them). */
function latin(string $text): string
{
    return (string) iconv('UTF-8', 'ISO-8859-2//TRANSLIT', $text);
}

function text(GdImage $image, int $x, int $y, string $text, int $font, int $color): void
{
    imagestring($image, $font, $x, $y, latin($text), $color);
}

/** Text enlarged `factor` times (nearest neighbour: sharp pixels, readable on a photo of the screen). */
function big(GdImage $image, int $x, int $y, string $text, int $font, int $factor, int $color): void
{
    $text = latin($text);
    $width = imagefontwidth($font) * strlen($text);
    $height = imagefontheight($font);
    $layer = imagecreatetruecolor($width, $height);
    $transparent = imagecolorallocate($layer, 0x00, 0xFF, 0x00);
    imagefill($layer, 0, 0, $transparent);
    imagecolortransparent($layer, $transparent);
    $rgb = imagecolorsforindex($image, $color);
    imagestring($layer, $font, 0, 0, $text, imagecolorallocate($layer, $rgb['red'], $rgb['green'], $rgb['blue']));
    imagecopyresized($image, $layer, $x, $y, 0, 0, $width * $factor, $height * $factor, $width, $height);
}

/**
 * Inserts the tEXt chunk MARKER (JSON of what is printed) right after IHDR, before the image data.
 *
 * @param array{serial: string, reference: string, model: string} $printed
 */
function withMarker(string $png, array $printed): string
{
    $data = MARKER."\0".json_encode($printed, JSON_THROW_ON_ERROR);
    $chunk = pack('N', strlen($data)).'tEXt'.$data.pack('N', crc32('tEXt'.$data));
    $afterHeader = 8 + 8 + 13 + 4; // signature, then IHDR: length, type, 13 bytes of data, CRC

    return substr($png, 0, $afterHeader).$chunk.substr($png, $afterHeader);
}
