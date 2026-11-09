<?php

declare(strict_types=1);

/**
 * Proves automàtiques (sense framework de proves).
 * Requereix l'aplicació instal·lada contra una base de dades DE PROVES (se n'esborren totes les dades!).
 * Ús: php tests/run.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Config;
use App\Helpers\Crypto;
use App\Helpers\Db;
use App\Helpers\Session;
use App\Models\Training;
use App\Services\AuthService;
use App\Services\FileService;
use App\Services\Installer;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Services\TokenService;
use App\Services\WebPush;
use App\Services\Notifier;
use App\Validators\Validator;

if (PHP_SAPI !== 'cli') {
    exit('Només CLI.');
}
if (Config::get('env') === 'production' && getenv('ALLOW_TEST_ON_PRODUCTION') !== '1') {
    fwrite(STDERR, "Les proves esborren les dades. Executa-les amb APP_ENV=local sobre una BD de proves.\n");
    exit(2);
}

const ADMIN_PASS = 'TestAdmin2026x';
const NEW_ADMIN_PASS = 'NouAdmin2026xy';
const TRAINER_PASS = 'Entrenador2026x';
$port = 8099;
$base = "http://127.0.0.1:$port";
$passed = 0;
$failed = 0;

function t(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  \033[32m✔\033[0m $name\n";
    } else {
        $failed++;
        echo "  \033[31m✘ $name\033[0m" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function section(string $title): void
{
    echo "\n\033[1m$title\033[0m\n";
}

final class Client
{
    public string $jar;
    public array $headers = [];
    public string $lastBody = '';

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jspm');
    }

    public function request(string $method, string $path, array|string|null $data = null): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERAGENT => 'JSPM-Tests',
        ]);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? self::flatten($data) : $data);
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $hsize);
        $this->lastBody = substr($raw, $hsize);
        $this->headers = [];
        foreach (explode("\r\n", $head) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $this->headers[strtolower(trim($k))] = trim($v);
            }
        }
        return ['status' => $status, 'body' => $this->lastBody, 'location' => $this->headers['location'] ?? '', 'headers' => $this->headers];
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function csrf(string $fromPath = '/acces'): string
    {
        $r = $this->get($fromPath);
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $r['body'], $m);
        return $m[1] ?? '';
    }

    /** POST amb el token CSRF obtingut d'una pàgina que el contingui. */
    public function post(string $path, array $data = [], string $csrfFrom = '/', bool $withCsrf = true): array
    {
        if ($withCsrf) {
            $data['_csrf'] = $this->csrf($csrfFrom);
        }
        return $this->request('POST', $path, $data);
    }

    public static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}[{$k}]";
            if (is_array($v)) {
                $out += self::flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    }

    public function login(string $login, string $password): array
    {
        return $this->post('/acces', ['login' => $login, 'password' => $password], '/acces');
    }
}

function resetDatabase(): void
{
    $pdo = Db::pdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (Db::column('SHOW TABLES') as $table) {
        $pdo->exec("TRUNCATE TABLE `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    Installer::runSqlFile($pdo, BASE_PATH . '/database/seeds/001_initial_data.sql');
    // Un equip per categoria (mateix id i nom), com fa la migració 004 en instal·lacions existents.
    $pdo->exec('INSERT IGNORE INTO teams (id, category_id, name, is_active, created_at, updated_at) SELECT id, id, name, is_active, NOW(), NOW() FROM categories');
    Installer::createAdmin($pdo, 'Gestor', ADMIN_PASS);
    foreach (glob(BASE_PATH . '/storage/uploads/{exercises,trainings}/*', GLOB_BRACE) ?: [] as $f) {
        @unlink($f);
    }
}

function ids(string $html, string $prefix): array
{
    preg_match_all('#href="[^"]*' . preg_quote($prefix, '#') . '(\d+)"#', $html, $m);
    return array_map('intval', $m[1]);
}

// ---------------------------------------------------------------------------------------
resetDatabase();
$cmd = sprintf('php -S 127.0.0.1:%d -t %s %s', $port, escapeshellarg(BASE_PATH . '/public'), escapeshellarg(BASE_PATH . '/public/index.php'));
$server = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(function () use ($server) {
    proc_terminate($server);
});
for ($i = 0; $i < 50; $i++) {
    if (@fsockopen('127.0.0.1', $port)) {
        break;
    }
    usleep(100000);
}

section('Unitats: validacions, xifrat i tokens');
$v = (new Validator(['e' => 'no-es-correu', 'p' => 'curta1', 'p2' => 'curta1']))->email('e', 'Correu')->password('p', 'p2');
t('Validator rebutja correu invàlid', isset($v->errors()['e']));
t('Validator rebutja contrasenya curta', isset($v->errors()['p']));
$v = (new Validator(['p' => 'LongaContrasenya1', 'p2' => 'LongaContrasenya2']))->password('p', 'p2');
t('Validator detecta contrasenyes diferents', isset($v->errors()['p2']));
$v = (new Validator(['d' => '2026-02-30', 't' => '25:00']))->date('d', 'Data')->time('t', 'Hora');
t('Validator rebutja data i hora impossibles', count($v->errors()) === 2);
$enc = Crypto::encrypt('Secret!123');
t('Crypto: xifra sense deixar el text en clar', !str_contains($enc, 'Secret') && Crypto::decrypt($enc) === 'Secret!123');
t('Crypto: rebutja dades manipulades', Crypto::decrypt(substr($enc, 0, -4) . 'AAAA') === null);
$tok = TokenService::generate();
t('Token: 64 caràcters hexadecimals i hash SHA-256 diferent', strlen($tok) === 64 && TokenService::hash($tok) !== $tok && strlen(TokenService::hash($tok)) === 64);

section('Fitxers: validació de pujades');
FileService::$testing = true;
$tmp = sys_get_temp_dir() . '/jspm-t';
@mkdir($tmp);
$mk = function (string $name, string $content) use ($tmp): array {
    $p = $tmp . '/' . bin2hex(random_bytes(4));
    file_put_contents($p, $content);
    return ['name' => $name, 'tmp_name' => $p, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK, 'type' => 'x'];
};
$pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
ob_start();
$im = imagecreatetruecolor(4, 4);
imagepng($im);
$png = (string) ob_get_clean();
t('PDF vàlid acceptat', FileService::validate($mk('sessio.pdf', $pdf))[1] === null);
t('PNG vàlid acceptat', FileService::validate($mk('imatge.png', $png))[1] === null);
t('PHP renombrat a .pdf rebutjat (MIME real)', FileService::validate($mk('hack.pdf', "<?php system(\$_GET['c']); ?>"))[1] !== null);
t('Extensió .php rebutjada', FileService::validate($mk('hack.php', '<?php echo 1;'))[1] !== null);
t('Doble extensió .php.pdf rebutjada', FileService::validate($mk('hack.php.pdf', $pdf))[1] !== null);
t('Doble extensió .pdf.exe rebutjada', FileService::validate($mk('doc.pdf.exe', 'MZ'))[1] !== null);
t('Executable rebutjat', FileService::validate($mk('programa.exe', "MZ\x90\x00"))[1] !== null);
t('PNG amb contingut fals rebutjat', FileService::validate($mk('fals.png', 'no sóc una imatge'))[1] !== null);
t('MP4 rebutjat si no està habilitat', FileService::validate($mk('v.mp4', "\x00\x00\x00\x18ftypmp42"))[1] !== null || Config::get('upload.allow_mp4'));
$big = $mk('gran.pdf', $pdf);
$big['size'] = Config::get('upload.max_bytes') + 1;
t('Fitxer massa gran rebutjat', FileService::validate($big)[1] !== null);
FileService::$testing = false;

section('Accés: inici de sessió, bloqueig i canvi obligatori de contrasenya');
$anon = new Client($base);
$r = $anon->get('/acces');
t('Pantalla d\'accés carrega', $r['status'] === 200 && str_contains($r['body'], 'Inicia la sessió'));
t('Cabeceres de seguretat (CSP, nosniff, frame)', isset($r['headers']['content-security-policy'], $r['headers']['x-frame-options']) && $r['headers']['x-content-type-options'] === 'nosniff');
t('Cache desactivada en pàgines', str_contains($r['headers']['cache-control'] ?? '', 'no-store'));
$session = $r['headers']['set-cookie'] ?? '';
$r = $anon->get('/');
t('Ruta protegida redirigeix a /acces', $r['status'] === 303 && str_ends_with($r['location'], '/acces'));
$r = $anon->post('/acces', ['login' => 'Gestor', 'password' => 'x'], '/acces', false);
t('Protecció CSRF: POST sense token → 419', $r['status'] === 419);
$r = $anon->request('POST', '/acces', ['login' => 'Gestor', 'password' => 'x', '_csrf' => 'invalid']);
t('Protecció CSRF: token invàlid → 419', $r['status'] === 419);

$r = $anon->login('Gestor', 'incorrecta');
t('Inici de sessió incorrecte: missatge genèric', $r['status'] === 422 && str_contains($r['body'], 'Usuari o contrasenya incorrectes'));
$r = $anon->login("' OR '1'='1", "' OR '1'='1");
t('Injecció SQL al login no autentica', $r['status'] === 422);
for ($i = 0; $i < 5; $i++) {
    $r = $anon->login('bloqueig@prova.cat', 'incorrecta' . $i);
}
$r = $anon->login('bloqueig@prova.cat', 'incorrecta');
t('Bloqueig temporal després d\'intents fallits', str_contains($r['body'], 'Massa intents'));
t('Intents fallits registrats', (int) Db::value("SELECT COUNT(*) FROM login_attempts WHERE identifier = 'bloqueig@prova.cat'") >= 5);
t('Auditoria registra login_failed', (int) Db::value("SELECT COUNT(*) FROM audit_logs WHERE action = 'login_failed'") >= 1);
Db::run('DELETE FROM login_attempts');

$admin = new Client($base);
$r = $admin->login('Gestor', ADMIN_PASS);
t('Inici de sessió correcte de l\'administrador', $r['status'] === 303);
t('Regeneració de sessió en iniciar sessió', ($admin->headers['set-cookie'] ?? '') !== '');
$r = $admin->get('/');
t('Canvi obligatori: / redirigeix a canvi de contrasenya', $r['status'] === 303 && str_contains($r['location'], '/compte/contrasenya'));
$r = $admin->get('/entrenadors');
t('Canvi obligatori: l\'administració està bloquejada', $r['status'] === 303 && str_contains($r['location'], '/compte/contrasenya'));
$r = $admin->post('/compte/contrasenya', ['current_password' => 'malament', 'password' => NEW_ADMIN_PASS, 'password_confirmation' => NEW_ADMIN_PASS], '/compte/contrasenya');
t('Canvi de contrasenya amb l\'actual incorrecta falla', $r['status'] === 422);
$r = $admin->post('/compte/contrasenya', ['current_password' => ADMIN_PASS, 'password' => 'curta', 'password_confirmation' => 'curta'], '/compte/contrasenya');
t('Contrasenya nova feble rebutjada', $r['status'] === 422);
$r = $admin->post('/compte/contrasenya', ['current_password' => ADMIN_PASS, 'password' => NEW_ADMIN_PASS, 'password_confirmation' => NEW_ADMIN_PASS], '/compte/contrasenya');
t('Canvi de contrasenya correcte', $r['status'] === 303);
t('must_change_password desactivat i hash no és text pla', (int) Db::value("SELECT must_change_password FROM users WHERE username = 'gestor'") === 0
    && str_starts_with((string) Db::value("SELECT password_hash FROM users WHERE username = 'gestor'"), '$2y$') || str_starts_with((string) Db::value("SELECT password_hash FROM users WHERE username = 'gestor'"), '$argon'));
t('Panell d\'administrador accessible', $admin->get('/')['status'] === 200);

section('Administració: categories i entrenadors');
$r = $admin->post('/categories', ['name' => 'Cadet', 'sort_order' => 9, 'is_active' => 1], '/categories/nova');
t('Categoria duplicada rebutjada', $r['status'] === 422);
$r = $admin->post('/categories', ['name' => 'Sènior', 'sort_order' => 9, 'is_active' => 1], '/categories/nova');
t('Categoria nova creada', $r['status'] === 303 && (int) Db::value("SELECT COUNT(*) FROM categories WHERE name = 'Sènior'") === 1);
$catCadet = (int) Db::value("SELECT id FROM categories WHERE name = 'Cadet'");
$catInfantil = (int) Db::value("SELECT id FROM categories WHERE name = 'Infantil'");
$catBenjami = (int) Db::value("SELECT id FROM categories WHERE name = 'Benjamí'");
$catSenior = (int) Db::value("SELECT id FROM categories WHERE name = 'Sènior'");
$r = $admin->post("/categories/$catSenior/eliminar", [], '/categories');
t('Es pot eliminar una categoria sense relacions', (int) Db::value('SELECT COUNT(*) FROM categories WHERE id = ?', [$catSenior]) === 0);

$r = $admin->post('/entrenadors', ['first_name' => 'Anna', 'last_name' => 'Puig', 'email' => 'anna@prova.cat', 'is_active' => 1, 'team_ids' => [$catCadet]], '/entrenadors/nou');
t('Entrenador creat', $r['status'] === 303);
$r = $admin->post("/categories/$catCadet/eliminar", [], '/categories');
t('No es pot eliminar una categoria amb equips', (int) Db::value('SELECT COUNT(*) FROM categories WHERE id = ?', [$catCadet]) === 1);
$r = $admin->post('/entrenadors', ['first_name' => 'Altra', 'last_name' => 'Puig', 'email' => 'ANNA@prova.cat'], '/entrenadors/nou');
t('Correu d\'entrenador únic (sense distingir majúscules)', $r['status'] === 422);
$r = $admin->post('/entrenadors', ['first_name' => '', 'last_name' => 'X', 'email' => 'mal'], '/entrenadors/nou');
t('Validació d\'entrenador amb errors al costat del camp', $r['status'] === 422 && str_contains($r['body'], 'field-error'));
$annaId = (int) Db::value("SELECT id FROM users WHERE email = 'anna@prova.cat'");
t('L\'entrenador es crea sense contrasenya', Db::value('SELECT password_hash FROM users WHERE id = ?', [$annaId]) === null);
t('Alta d\'entrenador registra correu (omès: SMTP desactivat)', (int) Db::value("SELECT COUNT(*) FROM email_logs WHERE type = 'activation' AND recipient = 'anna@prova.cat'") === 1);
t('Només hi ha un token d\'activació vàlid i es guarda com a hash', (int) Db::value('SELECT COUNT(*) FROM activation_tokens WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL AND CHAR_LENGTH(token_hash) = 64', [$annaId]) === 1);

section('Activació d\'entrenador i recuperació de contrasenya');
$tokenOld = TokenService::createActivation($annaId);
$tokenNew = TokenService::createActivation($annaId);
$anna = new Client($base);
t('Un enllaç substituït per un de nou deixa de funcionar', $anna->get("/activar/$tokenOld")['status'] === 410);
t('L\'enllaç nou mostra el formulari', $anna->get("/activar/$tokenNew")['status'] === 200);
t('Cap token en text pla a la BD', (int) Db::value('SELECT COUNT(*) FROM activation_tokens WHERE token_hash IN (?, ?)', [$tokenOld, $tokenNew]) === 0);
$r = $anna->post("/activar/$tokenNew", ['password' => TRAINER_PASS, 'password_confirmation' => 'diferent1234'], "/activar/$tokenNew");
t('Activació amb contrasenyes diferents falla', $r['status'] === 422);
$r = $anna->post("/activar/$tokenNew", ['password' => TRAINER_PASS, 'password_confirmation' => TRAINER_PASS], "/activar/$tokenNew");
t('Activació correcta', $r['status'] === 303);
t('Token d\'activació d\'un sol ús (invalidat)', $anna->get("/activar/$tokenNew")['status'] === 410);
$r = $anna->request('POST', "/activar/$tokenNew", ['password' => 'AltraContra2026', 'password_confirmation' => 'AltraContra2026', '_csrf' => $anna->csrf('/acces')]);
t('No es pot reutilitzar el token per canviar la contrasenya', $r['status'] === 303 && str_ends_with($r['location'], '/acces') && !AuthService::verifyPassword($annaId, 'AltraContra2026'));
$r = $anna->login('anna@prova.cat', TRAINER_PASS);
t('L\'entrenador inicia sessió amb el correu', $r['status'] === 303);
t('L\'entrenador no ha de canviar la contrasenya', !str_contains($anna->get('/')['location'], 'contrasenya'));

$tokRevoke = TokenService::createActivation($annaId);
TokenService::revokeActivation($annaId);
t('Un enllaç revocat per l\'administrador no funciona', (new Client($base))->get("/activar/$tokRevoke")['status'] === 410);

$guest = new Client($base);
$r1 = $guest->post('/contrasenya/oblidada', ['email' => 'anna@prova.cat'], '/contrasenya/oblidada');
$r2 = $guest->post('/contrasenya/oblidada', ['email' => 'noexisteix@prova.cat'], '/contrasenya/oblidada');
t('Recuperació: mateixa resposta tant si el correu existeix com si no', $r1['status'] === 303 && $r2['status'] === 303 && $r1['location'] === $r2['location']);
t('Recuperació: només es crea token per al correu existent', (int) Db::value('SELECT COUNT(*) FROM password_reset_tokens') === 1);
$reset = TokenService::createReset($annaId);
$c = new Client($base);
t('Enllaç de recuperació vàlid', $c->get("/contrasenya/restablir/$reset")['status'] === 200);
$r = $c->post("/contrasenya/restablir/$reset", ['password' => 'Recuperada2026x', 'password_confirmation' => 'Recuperada2026x'], "/contrasenya/restablir/$reset");
t('Recuperació de contrasenya correcta', $r['status'] === 303 && AuthService::verifyPassword($annaId, 'Recuperada2026x'));
t('Token de recuperació d\'un sol ús', (new Client($base))->get("/contrasenya/restablir/$reset")['status'] === 303);
$expired = TokenService::createReset($annaId);
Db::run('UPDATE password_reset_tokens SET expires_at = ? WHERE token_hash = ?', [date('Y-m-d H:i:s', time() - 60), TokenService::hash($expired)]);
t('Token de recuperació caducat rebutjat', TokenService::findReset($expired) === null && (new Client($base))->get("/contrasenya/restablir/$expired")['status'] === 303);
$anna = new Client($base);
$anna->login('anna@prova.cat', 'Recuperada2026x');

section('Permisos: administrador vs entrenador');
foreach (['/entrenadors', '/entrenadors/nou', '/categories', '/categories', '/admin/correu', '/admin/registre', '/admin/correus', '/admin/identitat', '/administradors', '/administradors/nou', '/exercicis/nou', '/entrenaments/nou'] as $path) {
    t("Entrenador no pot accedir a $path", $anna->get($path)['status'] === 403);
}
$r = $anna->post('/categories', ['name' => 'Hackers', 'sort_order' => 1], '/exercicis');
t('Entrenador no pot crear categories (POST amb CSRF vàlid)', $r['status'] === 403 && (int) Db::value("SELECT COUNT(*) FROM categories WHERE name = 'Hackers'") === 0);
$r = $anna->post("/entrenadors/$annaId", ['first_name' => 'Anna', 'last_name' => 'Puig', 'email' => 'anna@prova.cat', 'team_ids' => [$catInfantil]], '/exercicis');
t('Entrenador no pot assignar-se categories', $r['status'] === 403 && User_categories($annaId) === [$catCadet]);
function User_categories(int $id): array
{
    return array_map('intval', Db::column('SELECT team_id FROM user_teams WHERE user_id = ?', [$id]));
}
t('Entrenador no pot crear entrenaments', $anna->post('/entrenaments', ['title' => 'X'], '/exercicis')['status'] === 403);
t('Administrador accedeix a l\'administració', $admin->get('/admin/registre')['status'] === 200);

section('Exercicis: consulta global, filtres, XSS i injecció SQL');
$pdfPath = $tmp . '/sessio.pdf';
file_put_contents($pdfPath, $pdf);
$evilPath = $tmp . '/hack.pdf';
file_put_contents($evilPath, "<?php system('id');");
$dblPath = $tmp . '/hack.php.pdf';
file_put_contents($dblPath, $pdf);
$csrf = $admin->csrf('/exercicis/nou');
$r = $admin->request('POST', '/exercicis', [
    '_csrf' => $csrf, 'name' => 'Rondo 4x2', 'description' => 'Rondo de passades', 'objective' => 'Passada', 'material' => 'Cons',
    'players' => 8, 'duration_minutes' => 15, 'difficulty' => 'easy', 'is_active' => 1, 'team_ids' => [$catBenjami, $catInfantil],
    'files' => [new CURLFile($pdfPath, 'application/pdf', 'sessio.pdf')],
]);
t('Exercici creat amb PDF adjunt', $r['status'] === 303);
$exBenjami = (int) Db::value("SELECT id FROM exercises WHERE name = 'Rondo 4x2'");
$fileEx = (int) Db::value('SELECT id FROM exercise_files WHERE exercise_id = ?', [$exBenjami]);
t('El fitxer es desa amb nom aleatori sense extensió fora de public', $fileEx > 0 && preg_match('/^[a-f0-9]{40}$/', (string) Db::value('SELECT stored_name FROM exercise_files WHERE id = ?', [$fileEx])) === 1
    && is_file(BASE_PATH . '/storage/uploads/exercises/' . Db::value('SELECT stored_name FROM exercise_files WHERE id = ?', [$fileEx])));
$r = $admin->request('POST', '/exercicis', ['_csrf' => $csrf, 'name' => 'Maliciós', 'description' => 'x', 'team_ids' => [$catCadet], 'is_active' => 1,
    'files' => [new CURLFile($evilPath, 'application/pdf', 'hack.pdf')]]);
t('Pujada d\'un PHP disfressat de PDF rebutjada', $r['status'] === 422 && (int) Db::value("SELECT COUNT(*) FROM exercises WHERE name = 'Maliciós'") === 0);
$r = $admin->request('POST', '/exercicis', ['_csrf' => $csrf, 'name' => 'Maliciós', 'description' => 'x', 'team_ids' => [$catCadet], 'is_active' => 1,
    'files' => [new CURLFile($dblPath, 'application/pdf', 'hack.php.pdf')]]);
t('Pujada amb doble extensió rebutjada', $r['status'] === 422);
$admin->post('/exercicis', ['name' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>', 'team_ids' => [$catCadet], 'is_active' => 1], '/exercicis/nou');
$admin->post('/exercicis', ['name' => 'Inactiu secret', 'description' => 'x', 'team_ids' => [$catCadet]], '/exercicis/nou');
$exXss = (int) Db::value("SELECT id FROM exercises WHERE name LIKE '<script>%'");
$exInactive = (int) Db::value("SELECT id FROM exercises WHERE name = 'Inactiu secret'");
t('Exercici inactiu desat com a inactiu', (int) Db::value('SELECT is_active FROM exercises WHERE id = ?', [$exInactive]) === 0);

$r = $anna->get('/exercicis');
t('L\'entrenador veu exercicis de categories que no té assignades', str_contains($r['body'], 'Rondo 4x2'));
t('L\'entrenador no veu exercicis inactius al llistat', !str_contains($r['body'], 'Inactiu secret'));
t('L\'entrenador rep 404 en un exercici inactiu', $anna->get("/exercicis/$exInactive")['status'] === 404);
t('XSS: el nom de l\'exercici s\'escapa al llistat', !str_contains($r['body'], '<script>alert(1)</script>') && str_contains($r['body'], '&lt;script&gt;'));
$r = $anna->get("/exercicis/$exXss");
t('XSS: la descripció s\'escapa al detall', !str_contains($r['body'], '<img src=x') && str_contains($r['body'], '&lt;img'));
$r = $anna->get('/exercicis?name=' . urlencode("' OR 1=1 -- "));
t('Injecció SQL al filtre no retorna dades', $r['status'] === 200 && !str_contains($r['body'], 'Rondo 4x2'));
$r = $anna->get('/exercicis?team=' . $catInfantil);
t('Filtre per categoria addicional funciona', str_contains($r['body'], 'Rondo 4x2'));
$r = $anna->get('/exercicis?players=8&difficulty=easy&material=Cons&objective=Passada');
t('Filtres combinats (jugadors, dificultat, material, objectiu)', str_contains($r['body'], 'Rondo 4x2'));
$r = $anna->get('/exercicis?players=9');
t('Filtre per nombre de jugadors exclou no coincidents', !str_contains($r['body'], 'Rondo 4x2'));

section('Entrenaments: restricció per categoria/equip');
$mkTraining = function (string $title, array $cats, array $extra = []) use ($admin): int {
    $admin->post('/entrenaments', array_merge([
        'title' => $title, 'session_date' => date('Y-m-d', time() + 86400 * 3), 'start_time' => '18:30', 'duration_minutes' => 90,
        'season' => '2026-2027', 'description' => 'Descripció', 'team_ids' => $cats, 'action' => 'save',
        'ex' => [['exercise_id' => 0]],
    ], $extra), '/entrenaments/nou');
    return (int) Db::value('SELECT id FROM training_sessions WHERE title = ?', [$title]);
};
$trOwn = $mkTraining('Sessió Cadet', [$catCadet]);
$trOther = $mkTraining('Sessió Infantil', [$catInfantil]);
$trDraft = $mkTraining('Sessió Esborrany Cadet', [$catCadet]);
t('Entrenaments creats com a esborrany', $trOwn > 0 && Db::value('SELECT status FROM training_sessions WHERE id = ?', [$trOwn]) === 'draft');
$r = $admin->post('/entrenaments', ['title' => '', 'session_date' => 'mal', 'start_time' => '99:99', 'duration_minutes' => 'x', 'season' => 'x', 'action' => 'save'], '/entrenaments/nou');
t('Validació d\'entrenament', $r['status'] === 422 && str_contains($r['body'], 'field-error'));

// Exercicis dins de l'entrenament: afegir, ordenar, treure (sense JavaScript)
$r = $admin->request('POST', "/entrenaments/$trOwn", ['_csrf' => $admin->csrf('/entrenaments/nou'), 'title' => 'Sessió Cadet', 'session_date' => date('Y-m-d', time() + 86400 * 3),
    'start_time' => '18:30', 'duration_minutes' => 90, 'season' => '2026-2027', 'team_ids' => [$catCadet], 'action' => 'add', 'add_exercise' => $exBenjami]);
t('Afegir exercici mostra el formulari actualitzat sense desar', $r['status'] === 200 && str_contains($r['body'], 'Rondo 4x2'));
$rows = [['exercise_id' => $exBenjami, 'duration' => 10, 'notes' => 'Primer'], ['exercise_id' => $exXss, 'duration' => 20, 'notes' => 'Segon']];
$admin->post("/entrenaments/$trOwn", ['title' => 'Sessió Cadet', 'session_date' => date('Y-m-d', time() + 86400 * 3), 'start_time' => '18:30', 'duration_minutes' => 90,
    'season' => '2026-2027', 'team_ids' => [$catCadet], 'action' => 'save', 'ex' => $rows], '/entrenaments/nou');
t('Exercicis desats amb ordre', Db::column('SELECT exercise_id FROM training_exercises WHERE training_id = ? ORDER BY position', [$trOwn]) === [(string) $exBenjami, (string) $exXss]
    || array_map('intval', Db::column('SELECT exercise_id FROM training_exercises WHERE training_id = ? ORDER BY position', [$trOwn])) === [$exBenjami, $exXss]);
$r = $admin->request('POST', "/entrenaments/$trOwn", ['_csrf' => $admin->csrf('/entrenaments/nou'), 'title' => 'Sessió Cadet', 'session_date' => date('Y-m-d', time() + 86400 * 3),
    'start_time' => '18:30', 'duration_minutes' => 90, 'season' => '2026-2027', 'team_ids' => [$catCadet], 'action' => 'down:0', 'ex' => $rows]);
preg_match_all('/name="ex\[(\d)\]\[exercise_id\]" value="(\d+)"/', $r['body'], $mm);
t('Botó "Baixa" reordena els exercicis', array_map('intval', $mm[2]) === [$exXss, $exBenjami]);
$r = $admin->request('POST', "/entrenaments/$trOwn", ['_csrf' => $admin->csrf('/entrenaments/nou'), 'title' => 'Sessió Cadet', 'session_date' => date('Y-m-d', time() + 86400 * 3),
    'start_time' => '18:30', 'duration_minutes' => 90, 'season' => '2026-2027', 'team_ids' => [$catCadet], 'action' => 'remove:0', 'ex' => $rows]);
t('Treure un exercici de l\'entrenament no l\'esborra del catàleg', substr_count($r['body'], 'name="ex[') >= 1 && (int) Db::value('SELECT COUNT(*) FROM exercises WHERE id = ?', [$exBenjami]) === 1);

// Fitxer adjunt a entrenaments
$admin->request('POST', "/entrenaments/$trOther", ['_csrf' => $admin->csrf('/entrenaments/nou'), 'title' => 'Sessió Infantil', 'session_date' => date('Y-m-d', time() + 86400 * 3),
    'start_time' => '18:30', 'duration_minutes' => 90, 'season' => '2026-2027', 'team_ids' => [$catInfantil], 'action' => 'save',
    'files' => [new CURLFile($pdfPath, 'application/pdf', 'pla-infantil.pdf')]]);
$admin->request('POST', "/entrenaments/$trOwn", ['_csrf' => $admin->csrf('/entrenaments/nou'), 'title' => 'Sessió Cadet', 'session_date' => date('Y-m-d', time() + 86400 * 3),
    'start_time' => '18:30', 'duration_minutes' => 90, 'season' => '2026-2027', 'team_ids' => [$catCadet], 'action' => 'save',
    'files' => [new CURLFile($pdfPath, 'application/pdf', 'pla-cadet.pdf')]]);
$fileOwn = (int) Db::value('SELECT id FROM training_files WHERE training_id = ?', [$trOwn]);
$fileOther = (int) Db::value('SELECT id FROM training_files WHERE training_id = ?', [$trOther]);
t('Adjunts d\'entrenament desats', $fileOwn > 0 && $fileOther > 0);

MailService::$fake = null;
// Publicació (SMTP desactivat → correus omesos però registrats)
foreach ([$trOwn, $trOther] as $id) {
    $admin->post("/entrenaments/$id/estat", ['status' => 'published'], "/entrenaments/$id");
}
$r = $anna->get('/entrenaments');
t('L\'entrenador veu entrenaments publicats de la seva categoria', str_contains($r['body'], 'Sessió Cadet'));
t('L\'entrenador no veu entrenaments d\'altres categories', !str_contains($r['body'], 'Sessió Infantil'));
t('L\'entrenador no veu esborranys', !str_contains($r['body'], 'Esborrany Cadet'));
t('Detall d\'entrenament propi accessible', $anna->get("/entrenaments/$trOwn")['status'] === 200);
t('Detall d\'entrenament d\'una altra categoria → 404', $anna->get("/entrenaments/$trOther")['status'] === 404);
t('Detall d\'un esborrany → 404', $anna->get("/entrenaments/$trDraft")['status'] === 404);
t('Administrador veu tots els entrenaments', str_contains($admin->get('/entrenaments?when=all')['body'], 'Sessió Infantil'));

section('Descàrrega d\'arxius protegida');
$r = $anna->get("/fitxers/exercici/$fileEx");
t('Descàrrega autoritzada d\'adjunt d\'exercici', $r['status'] === 200 && str_starts_with($r['body'], '%PDF'));
t('Capçaleres Content-Disposition i nosniff', str_contains($r['headers']['content-disposition'] ?? '', 'attachment') && ($r['headers']['x-content-type-options'] ?? '') === 'nosniff');
t('Descàrrega sense sessió redirigida a l\'accés', (new Client($base))->get("/fitxers/exercici/$fileEx")['status'] === 303);
t('Descàrrega d\'adjunt d\'entrenament propi', $anna->get("/fitxers/entrenament/$fileOwn")['status'] === 200);
t('Descàrrega d\'adjunt d\'entrenament no assignat → 404', $anna->get("/fitxers/entrenament/$fileOther")['status'] === 404);
t('Accés directe a storage/ no és possible des del web', (new Client($base))->get('/storage/uploads/exercises/' . Db::value('SELECT stored_name FROM exercise_files WHERE id = ?', [$fileEx]))['status'] === 404);

section('Correus: contingut, enviament i duplicats');
$sent = [];
MailService::$fake = function (array $m) use (&$sent): bool {
    $sent[] = $m;
    return true;
};
$bernat = (int) Db::insert('users', ['first_name' => 'Bernat', 'last_name' => 'Soler', 'email' => 'bernat@prova.cat', 'password_hash' => password_hash('x', PASSWORD_DEFAULT),
    'is_active' => 1, 'must_change_password' => 0, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
Db::insert('user_roles', ['user_id' => $bernat, 'role_id' => 2]);
$inactiu = (int) Db::insert('users', ['first_name' => 'Inactiu', 'last_name' => 'X', 'email' => 'inactiu@prova.cat', 'password_hash' => password_hash('x', PASSWORD_DEFAULT),
    'is_active' => 0, 'must_change_password' => 0, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
Db::insert('user_roles', ['user_id' => $inactiu, 'role_id' => 2]);
foreach ([$inactiu, $bernat] as $uid) {
    Db::insert('user_teams', ['user_id' => $uid, 'team_id' => $catCadet]);
}
$rec = NotificationService::recipients($trOwn);
t('Destinataris sense duplicats (una sola fila per entrenador)', count($rec) === count(array_unique(array_column($rec, 'id'))) && count($rec) === 2);
t('Entrenadors inactius no reben correus', !in_array('inactiu@prova.cat', array_column($rec, 'email'), true));
$res = NotificationService::notifyTraining($trOwn, 'published');
$to = array_column($sent, 'to');
t('Publicació: un únic correu per entrenador', $res['sent'] === 2 && count($to) === count(array_unique($to)));
t('Correu de publicació inclou títol, data, hora, categories i enllaç', str_contains($sent[0]['html'], 'Sessió Cadet') && str_contains($sent[0]['html'], '18:30')
    && str_contains($sent[0]['html'], 'Cadet') && str_contains($sent[0]['html'], '/entrenaments/' . $trOwn));
t('Correu en català', str_contains($sent[0]['html'], 'Nou entrenament publicat'));
$sent = [];
$res = NotificationService::notifyTraining($trOwn, 'cancelled');
t('Cancel·lació notifica amb el missatge adequat', $res['sent'] === 2 && str_contains($sent[0]['html'], 'cancel·lat'));
$sent = [];
$newTok = TokenService::createActivation($annaId);
NotificationService::sendActivation(['id' => $annaId, 'first_name' => 'Anna', 'last_name' => 'Puig', 'email' => 'anna@prova.cat'], $newTok);
t('Correu d\'activació inclou nom, usuari i enllaç', str_contains($sent[0]['html'], 'Anna') && str_contains($sent[0]['html'], 'anna@prova.cat') && str_contains($sent[0]['html'], "/activar/$newTok"));
t('El correu enviat queda registrat', (int) Db::value("SELECT COUNT(*) FROM email_logs WHERE status = 'sent' AND type = 'activation'") >= 1);
MailService::$fake = fn () => false;
$ok = MailService::send('prova', 'x@prova.cat', 'X', 'Assumpte', 'test', []);
$log = Db::one("SELECT status, attempts FROM email_logs WHERE type = 'prova'");
t('Error d\'enviament registrat amb nombre d\'intents', !$ok && $log['status'] === 'failed' && (int) $log['attempts'] === 2);
MailService::$fake = null;

// Canvi de data d'un entrenament publicat → notificació
$sent = [];
MailService::$fake = function (array $m) use (&$sent): bool {
    $sent[] = $m;
    return true;
};
Db::run("UPDATE training_sessions SET status = 'published' WHERE id = ?", [$trOwn]);
$admin->post("/entrenaments/$trOwn", ['title' => 'Sessió Cadet', 'session_date' => date('Y-m-d', time() + 86400 * 5), 'start_time' => '19:00', 'duration_minutes' => 90,
    'season' => '2026-2027', 'team_ids' => [$catCadet], 'action' => 'save'], '/entrenaments/nou');
MailService::$fake = null;
t('Canvi de data/hora (servidor HTTP sense simulació: omès però registrat)', (int) Db::value("SELECT COUNT(*) FROM email_logs WHERE type = 'training_updated'") >= 1);

section('Configuració SMTP i logotip');
$r = $admin->post('/admin/correu', ['host' => 'smtp.prova.cat', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'smtp_password' => 'SecretSMTP!77', 'from_email' => 'club@prova.cat', 'from_name' => 'Club', 'reply_to' => '', 'enabled' => ''], '/admin/correu');
t('Configuració SMTP desada', $r['status'] === 303);
$enc = (string) Db::value('SELECT password_enc FROM smtp_settings WHERE id = 1');
t('Contrasenya SMTP xifrada a la BD', $enc !== '' && !str_contains($enc, 'SecretSMTP') && Crypto::decrypt($enc) === 'SecretSMTP!77');
$page = $admin->get('/admin/correu')['body'];
t('La contrasenya SMTP no es retorna al navegador', !str_contains($page, 'SecretSMTP') && !str_contains($page, $enc));
$admin->post('/admin/correu', ['host' => 'smtp.prova.cat', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'smtp_password' => '', 'from_email' => 'club@prova.cat', 'from_name' => 'Club', 'reply_to' => ''], '/admin/correu');
t('Deixar la contrasenya en blanc la conserva', Crypto::decrypt((string) Db::value('SELECT password_enc FROM smtp_settings WHERE id = 1')) === 'SecretSMTP!77');
t('Contrasenya SMTP absent dels registres', (int) Db::value("SELECT COUNT(*) FROM audit_logs WHERE details LIKE '%SecretSMTP%'") === 0 && (int) Db::value("SELECT COUNT(*) FROM email_logs WHERE error_message LIKE '%SecretSMTP%'") === 0);
$logoPath = $tmp . '/logo.png';
file_put_contents($logoPath, $png);
$r = $admin->request('POST', '/admin/identitat/logotip', ['_csrf' => $admin->csrf('/admin/identitat'), 'logo' => new CURLFile($logoPath, 'image/png', 'logo.png')]);
t('Logotip pujat', $r['status'] === 303 && \App\Services\SettingsService::logoPath() !== null);
$lg = (new Client($base))->get('/logotip');
t('El logotip es serveix públicament (pantalla d\'accés)', $lg['status'] === 200 && str_starts_with($lg['headers']['content-type'] ?? '', 'image/png'));
$r = $admin->request('POST', '/admin/identitat/logotip', ['_csrf' => $admin->csrf('/admin/identitat'), 'logo' => new CURLFile($pdfPath, 'application/pdf', 'logo.pdf')]);
t('Logotip amb tipus no permès rebutjat', $r['status'] === 303 && str_contains($admin->get('/admin/identitat')['body'], 'no està permès'));

section('Pàgina d\'equip, camps buits i contrasenya mínima');
$r = $anna->get('/');
t('El panell de l\'entrenador té un botó per equip', str_contains($r['body'], '/equips/' . $catCadet . '"'));
$r = $anna->get('/equips/' . $catCadet);
t('La pàgina de l\'equip mostra els seus entrenaments publicats', $r['status'] === 200 && str_contains($r['body'], 'Sessió Cadet') && !str_contains($r['body'], 'Esborrany Cadet'));
$exCadet = (int) Db::value("SELECT id FROM exercises WHERE name = 'Inactiu secret'");
Db::run('UPDATE exercises SET is_active = 1 WHERE id = ?', [$exCadet]);
$r = $anna->get('/equips/' . $catCadet);
t('La pàgina de l\'equip mostra els exercicis de l\'equip', str_contains($r['body'], 'Inactiu secret'));
t('No mostra exercicis d\'altres equips', !str_contains($r['body'], 'Rondo 4x2'));
t('Un equip no assignat → 404', $anna->get('/equips/' . $catInfantil)['status'] === 404);
$r = $anna->get("/exercicis/$exCadet");
t('Camps buits de l\'exercici no mostren el títol', !str_contains($r['body'], 'Objectiu') && !str_contains($r['body'], 'Material') && !str_contains($r['body'], 'Dificultat') && !str_contains($r['body'], 'Jugadors'));
$r = $anna->get("/exercicis/$exBenjami");
t('Camps plens de l\'exercici sí que es mostren', str_contains($r['body'], 'Objectiu') && str_contains($r['body'], 'Material') && str_contains($r['body'], 'Dificultat'));
$v = (new Validator(['p' => 'abcdefg1', 'p2' => 'abcdefg1']))->password('p', 'p2');
t('Contrasenya de 8 caràcters amb lletres i números acceptada', !$v->fails());
$v = (new Validator(['p' => 'abcdef1', 'p2' => 'abcdef1']))->password('p', 'p2');
t('Contrasenya de 7 caràcters rebutjada', $v->fails());
$v = (new Validator(['p' => 'abcdefgh', 'p2' => 'abcdefgh']))->password('p', 'p2');
t('Contrasenya sense números rebutjada', $v->fails());

section('Administradors, eliminació d\'entrenadors i avisos');
$r = $admin->post('/administradors', ['first_name' => 'Laia', 'last_name' => 'Admin', 'email' => 'laia@prova.cat', 'is_active' => 1], '/administradors/nou');
t('L\'administrador pot crear un altre administrador', $r['status'] === 303);
$laiaId = (int) Db::value("SELECT id FROM users WHERE email = 'laia@prova.cat'");
t('El nou usuari té el rol d\'administrador', Db::value('SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?', [$laiaId]) === 'admin');
t('Es registra l\'enviament del correu d\'activació', (int) Db::value("SELECT COUNT(*) FROM email_logs WHERE recipient = 'laia@prova.cat'") === 1);
$r = $admin->post('/administradors', ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'LAIA@prova.cat'], '/administradors/nou');
t('Correu d\'administrador únic', $r['status'] === 422);
t('Un entrenador no pot veure ni crear administradors', $anna->post('/administradors', ['first_name' => 'H', 'last_name' => 'H', 'email' => 'h@prova.cat'], '/exercicis')['status'] === 403
    && (int) Db::value("SELECT COUNT(*) FROM users WHERE email = 'h@prova.cat'") === 0);
$laiaToken = TokenService::createActivation($laiaId);
$laia = new Client($base);
$laia->post("/activar/$laiaToken", ['password' => 'LaiaAdmin2026', 'password_confirmation' => 'LaiaAdmin2026'], "/activar/$laiaToken");
$laia->login('laia@prova.cat', 'LaiaAdmin2026');
t('El nou administrador pot iniciar sessió i accedir a l\'administració', $laia->get('/entrenadors')['status'] === 200 && $laia->get('/administradors')['status'] === 200);
$r = $laia->post("/administradors/$laiaId/estat", [], '/administradors');
t('No es pot desactivar un mateix', $r['status'] === 303 && (int) Db::value('SELECT is_active FROM users WHERE id = ?', [$laiaId]) === 1);
$admin->post("/administradors/$laiaId/estat", [], '/administradors');
t('Un administrador pot desactivar-ne un altre', (int) Db::value('SELECT is_active FROM users WHERE id = ?', [$laiaId]) === 0 && $laia->get('/entrenadors')['status'] === 303);
$gestorId = (int) Db::value("SELECT id FROM users WHERE username = 'gestor'");
Db::run('UPDATE users SET is_active = 0 WHERE id = ?', [$laiaId]);
$admin->post("/administradors/$gestorId", ['first_name' => 'Gestor', 'last_name' => '', 'email' => 'gestor@prova.cat', 'is_active' => ''], '/administradors');
t('L\'últim administrador actiu no es pot desactivar', (int) Db::value('SELECT is_active FROM users WHERE id = ?', [$gestorId]) === 1);

$admin->post('/entrenadors', ['first_name' => 'Temp', 'last_name' => 'Esborrar', 'email' => 'temp@prova.cat', 'is_active' => 1, 'team_ids' => [$catCadet]], '/entrenadors/nou');
$tempId = (int) Db::value("SELECT id FROM users WHERE email = 'temp@prova.cat'");
t('Entrenador temporal creat amb categories i token', $tempId > 0 && (int) Db::value('SELECT COUNT(*) FROM user_teams WHERE user_id = ?', [$tempId]) === 1);
$r = $anna->post("/entrenadors/$tempId/eliminar", [], '/exercicis');
t('Un entrenador no pot eliminar entrenadors', $r['status'] === 403 && (int) Db::value('SELECT COUNT(*) FROM users WHERE id = ?', [$tempId]) === 1);
$r = $admin->post("/entrenadors/$tempId/eliminar", [], '/entrenadors');
t('L\'administrador elimina un entrenador', $r['status'] === 303 && (int) Db::value('SELECT COUNT(*) FROM users WHERE id = ?', [$tempId]) === 0);
t('S\'eliminen també les relacions i els tokens', (int) Db::value('SELECT COUNT(*) FROM user_teams WHERE user_id = ?', [$tempId]) === 0
    && (int) Db::value('SELECT COUNT(*) FROM user_roles WHERE user_id = ?', [$tempId]) === 0 && (int) Db::value('SELECT COUNT(*) FROM activation_tokens WHERE user_id = ?', [$tempId]) === 0);
t('L\'eliminació queda auditada', (int) Db::value("SELECT COUNT(*) FROM audit_logs WHERE action = 'trainer_deleted' AND entity_id = ?", [$tempId]) === 1);
t('Eliminar un entrenador inexistent → 404', $admin->post("/entrenadors/$tempId/eliminar", [], '/entrenadors')['status'] === 404);

Db::run("INSERT INTO email_logs (type, recipient, subject, status, attempts, error_message, created_at) VALUES ('prova', 'a@b.cat', 's', 'failed', 2, 'x', ?)", [Db::now()]);
t('L\'avís de correus fallits es mostra', str_contains($admin->get('/')['body'], 'fallit(s)'));
$r = $admin->post('/admin/avisos/correus', [], '/');
t('Es pot descartar l\'avís', $r['status'] === 303 && !str_contains($admin->get('/')['body'], 'fallit(s)'));
sleep(1);
Db::run("INSERT INTO email_logs (type, recipient, subject, status, attempts, error_message, created_at) VALUES ('prova', 'a@b.cat', 's', 'failed', 2, 'x', ?)", [Db::now()]);
t('Un error nou torna a mostrar l\'avís', str_contains($admin->get('/')['body'], 'fallit(s)'));
t('Un entrenador no pot descartar avisos', $anna->post('/admin/avisos/correus', [], '/exercicis')['status'] === 403);

section('Notificacions: aplicació, correu i navegador (Web Push)');
$pushLog = [];
WebPush::$transport = function (string $endpoint, array $headers, string $body) use (&$pushLog): int {
    $pushLog[] = compact('endpoint', 'headers', 'body');
    return str_contains($endpoint, 'gone') ? 410 : 201;
};
$client = WebPush::generateKeyPair();
$authSecret = random_bytes(16);
$csrf = $anna->csrf('/notificacions');
$sub = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123', 'keys' => ['p256dh' => WebPush::b64uEncode($client['public']), 'auth' => WebPush::b64uEncode($authSecret)]];
$r = $anna->request('POST', '/push/subscriure', json_encode($sub));
t('Subscripció sense CSRF rebutjada', $r['status'] === 419);
$ch = curl_init($base . '/push/subscriure');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($sub), CURLOPT_COOKIEFILE => $anna->jar, CURLOPT_COOKIEJAR => $anna->jar,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]]);
curl_exec($ch);
t('Subscripció Web Push acceptada (JSON + capçalera CSRF)', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 && Notifier::hasSubscription($annaId));
$bad = $sub;
$bad['endpoint'] = 'http://127.0.0.1/interna';
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($bad));
curl_exec($ch);
t('Endpoint no permès (SSRF) rebutjat', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 422);
curl_close($ch);
t('Només es permeten serveis de push coneguts', WebPush::isAllowedEndpoint('https://updates.push.services.mozilla.com/wpush/v2/x') && !WebPush::isAllowedEndpoint('https://evil.example.com/x') && !WebPush::isAllowedEndpoint('http://fcm.googleapis.com/x'));
Notifier::subscribe($annaId, 'https://fcm.googleapis.com/fcm/send/gone-endpoint', WebPush::b64uEncode($client['public']), WebPush::b64uEncode($authSecret));

$sent = [];
MailService::$fake = function (array $m) use (&$sent): bool {
    $sent[] = $m;
    return true;
};
$exNew = (int) Db::insert('exercises', ['name' => 'Exercici notificat', 'description' => 'x', 'team_id' => $catCadet, 'is_active' => 1, 'created_by' => 1, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
Db::insert('exercise_teams', ['exercise_id' => $exNew, 'team_id' => $catCadet]);
$res = NotificationService::notifyNewExercise($exNew);
t('Nou exercici: correu als entrenadors de l\'equip (sense duplicats)', $res['sent'] === count(array_unique(array_column($sent, 'to'))) && $res['sent'] >= 1 && str_contains($sent[0]['html'], 'Nou exercici'));
t('Nou exercici: notificació dins l\'aplicació', (int) Db::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = 'Nou exercici' AND body LIKE 'Exercici notificat%'", [$annaId]) === 1);
t('Nou exercici: Web Push enviat i subscripció caducada (410) eliminada', count($pushLog) === 2 && !(bool) Db::value("SELECT 1 FROM push_subscriptions WHERE endpoint LIKE '%gone-endpoint'"));
t('Cada entrenador de l\'equip rep la seva notificació', (int) Db::value("SELECT COUNT(*) FROM notifications WHERE body LIKE 'Exercici notificat%' AND user_id = ?", [$bernat]) === 1);
t('Un entrenador sense l\'equip no rep res', (int) Db::value("SELECT COUNT(*) FROM notifications WHERE body LIKE 'Exercici notificat%' AND user_id NOT IN (?, ?)", [$annaId, $bernat]) === 0);
Db::run('UPDATE users SET notify_email = 0 WHERE id = ?', [$bernat]);
$sent = [];
NotificationService::notifyNewExercise($exNew);
t('Amb els correus desactivats només rep notificació a l\'aplicació', !in_array('bernat@prova.cat', array_column($sent, 'to'), true) && (int) Db::value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND body LIKE 'Exercici notificat%'", [$bernat]) === 2);
Db::run('UPDATE users SET notify_email = 1 WHERE id = ?', [$bernat]);

// Verificació de la notificació xifrada: la desxifrem com ho faria el navegador
$msg = $pushLog[0];
$body = $msg['body'];
$salt = substr($body, 0, 16);
$asPub = substr($body, 21, 65);
$cipher = substr($body, 86);
$der = hex2bin('30770201010420') . $client['private'] . hex2bin('a00a06082a8648ce3d030107a144034200') . $client['public'];
$uaPem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
$peerPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $asPub), 64, "\n") . "-----END PUBLIC KEY-----\n";
$secret = openssl_pkey_derive($peerPem, $uaPem, 32);
$ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $client['public'] . $asPub, $authSecret);
$cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
$nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
$plain = openssl_decrypt(substr($cipher, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipher, -16));
$payload = json_decode((string) rtrim((string) $plain, "\x02"), true);
t('El missatge Web Push es desxifra correctament al dispositiu', is_array($payload) && $payload['title'] === 'Nou exercici' && str_contains($payload['url'], '/exercicis/' . $exNew));
$auth = '';
foreach ($msg['headers'] as $h) {
    if (str_starts_with($h, 'Authorization: ')) {
        $auth = substr($h, 15);
    }
}
preg_match('/^vapid t=([^,]+), k=(.+)$/', $auth, $m);
[$h64, $c64, $s64] = explode('.', $m[1]);
$raw = WebPush::b64uDecode($s64);
$toDer = function (string $rs): string {
    $enc = fn (string $i) => (ord($i[0]) & 0x80 ? "\0" : '') . ltrim($i, "\0") ?: "\0";
    $r = $enc(substr($rs, 0, 32));
    $s = $enc(substr($rs, 32));
    return "\x30" . chr(strlen($r) + strlen($s) + 4) . "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;
};
$vapidPub = WebPush::b64uDecode($m[2]);
$vp = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $vapidPub), 64, "\n") . "-----END PUBLIC KEY-----\n";
t('La signatura VAPID (JWT ES256) és vàlida', openssl_verify("$h64.$c64", $toDer($raw), $vp, OPENSSL_ALGO_SHA256) === 1 && $m[2] === Notifier::publicKey());
t('La clau privada VAPID es guarda xifrada', !str_contains((string) Db::value("SELECT setting_value FROM application_settings WHERE setting_key = 'vapid_private'"), WebPush::b64uEncode(Notifier::vapid()['private'])));
// Vector de prova de l'RFC 8291
$rfc = WebPush::encrypt(WebPush::b64uDecode('V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24'),
    WebPush::b64uDecode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'), WebPush::b64uDecode('BTBZMqHH6r4Tts7J_aSIgg'),
    ['private' => WebPush::b64uDecode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'), 'public' => WebPush::b64uDecode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8')],
    WebPush::b64uDecode('DGv6ra1nlYgDCS1FRnbzlw'));
t('El xifrat compleix el vector de prova de l\'RFC 8291', WebPush::b64uEncode(substr($rfc, 86)) === '8pfeW0KbunFT06SuDKoJH9Ql87S1QUrdirN6GcG7sFz1y1sqLgVi1VhjVkHsUoEsbI_0LpXMuGvnzQ');

$r = $anna->get('/notificacions');
t('La pàgina de notificacions mostra les novetats i marca-les com a llegides', $r['status'] === 200 && str_contains($r['body'], 'Exercici notificat') && Notifier::unreadCount($annaId) === 0);
$r = $anna->post('/notificacions/preferencies', [], '/notificacions');
t('L\'entrenador pot desactivar els correus', (int) Db::value('SELECT notify_email FROM users WHERE id = ?', [$annaId]) === 0);
$anna->post('/notificacions/preferencies', ['notify_email' => 1], '/notificacions');
Db::run('DELETE FROM push_subscriptions');
WebPush::$transport = null;
MailService::$fake = null;

section('PWA: manifest, service worker, icones i botó d\'instal·lació');
$pub = new Client($base);
$r = $pub->get('/manifest.webmanifest');
$mf = json_decode($r['body'], true);
t('Manifest públic amb tipus application/manifest+json', $r['status'] === 200 && str_starts_with($r['headers']['content-type'] ?? '', 'application/manifest+json') && is_array($mf));
t('Manifest: standalone, inici i àmbit relatius (arrel o subdirectori)', ($mf['display'] ?? '') === 'standalone' && str_starts_with($mf['start_url'] ?? '', './') && ($mf['scope'] ?? '') === './' && ($mf['lang'] ?? '') === 'ca');
$sizes = array_column($mf['icons'] ?? [], 'sizes');
t('Manifest: icones 192, 512 i maskable', in_array('192x192', $sizes, true) && in_array('512x512', $sizes, true) && in_array('maskable', array_column($mf['icons'], 'purpose'), true)
    && !str_starts_with($mf['icons'][0]['src'], '/'));
t('El manifest no estableix galetes de sessió', !isset($r['headers']['set-cookie']));
$r = $pub->get('/service-worker.js');
t('Service worker servit com a JavaScript i sense marcadors sense substituir', $r['status'] === 200 && str_contains($r['headers']['content-type'] ?? '', 'javascript') && !str_contains($r['body'], '__VERSION__') && !str_contains($r['body'], '__PRECACHE__'));
t('Service worker: versionat i neteja de caches antigues', str_contains($r['body'], "const VERSION = '") && str_contains($r['body'], 'caches.delete'));
t('Service worker: no desa POST ni pàgines (navegació només per xarxa)', str_contains($r['body'], "req.method !== 'GET'") && str_contains($r['body'], "req.mode === 'navigate'") && !preg_match('/cache\.put\([^)]*navigate/', $r['body']));
foreach (['icon-192' => 192, 'icon-512' => 512, 'icon-maskable-512' => 512, 'apple-touch-icon' => 180, 'favicon-32' => 32, 'favicon-16' => 16] as $name => $px) {
    $r = $pub->get("/icones/$name");
    $info = @getimagesizefromstring($r['body']);
    t("Icona $name ({$px}x{$px}) servida", $r['status'] === 200 && ($r['headers']['content-type'] ?? '') === 'image/png' && $info && $info[0] === $px && $info[1] === $px);
}
t('Una icona inexistent retorna 404', $pub->get('/icones/../../.env')['status'] === 404 && $pub->get('/icones/inventada')['status'] === 404);
t('/favicon.ico respon amb una imatge', ($f = $pub->get('/favicon.ico'))['status'] === 200 && ($f['headers']['content-type'] ?? '') === 'image/png');
t('Pàgina sense connexió pública', ($o = $pub->get('/offline'))['status'] === 200 && str_contains($o['body'], 'Sense connexió'));
$login = $pub->get('/acces')['body'];
t('<head>: manifest, theme-color, favicons i icona Apple', str_contains($login, 'rel="manifest"') && str_contains($login, 'name="theme-color"') && str_contains($login, 'sizes="32x32"')
    && str_contains($login, 'sizes="16x16"') && str_contains($login, 'rel="apple-touch-icon"') && str_contains($login, 'name="viewport"') && str_contains($login, 'apple-mobile-web-app-capable'));
t('Botó «Instal·la l\'aplicació» present però ocult per defecte (el mostra JavaScript)', preg_match('/data-install hidden/', $login) === 1 && str_contains($login, 'ios-install-dialog'));
t('Les icones no es serveixen amb la CSP bloquejada (sense scripts inline)', !preg_match('/<script(?![^>]*src)[^>]*>/', $login));

$r = $admin->get('/admin/identitat');
t('Pàgina de configuració d\'icones i logotips', $r['status'] === 200 && str_contains($r['body'], 'icon-maskable-512') && str_contains($r['body'], 'Genera totes les icones'));
$r = $admin->post('/admin/identitat/generar', [], '/admin/identitat');
$gen = $pub->get('/icones/icon-192');
t('Generació automàtica d\'icones a partir del logotip', $r['status'] === 303 && \App\Services\IconService::isCustom('icon-192') && \App\Services\IconService::isCustom('icon-maskable-512')
    && ($i = getimagesizefromstring($gen['body'])) && $i[0] === 192 && $i[2] === IMAGETYPE_PNG);
$notSquare = $tmp . '/nosquare.png';
$im = imagecreatetruecolor(20, 10);
imagepng($im, $notSquare);
$r = $admin->request('POST', '/admin/identitat/icona/icon-192', ['_csrf' => $admin->csrf('/admin/identitat'), 'icon' => new CURLFile($notSquare, 'image/png', 'a.png')]);
t('Icona no quadrada rebutjada', $r['status'] === 303 && str_contains($admin->get('/admin/identitat')['body'], 'quadrada'));
$r = $admin->request('POST', '/admin/identitat/icona/icon-192', ['_csrf' => $admin->csrf('/admin/identitat'), 'icon' => new CURLFile($pdfPath, 'application/pdf', 'a.png')]);
t('Icona amb contingut que no és PNG rebutjada', str_contains($admin->get('/admin/identitat')['body'], 'no coincideix') || str_contains($admin->get('/admin/identitat')['body'], 'no està permès'));
$sq = $tmp . '/sq.png';
imagepng(imagecreatetruecolor(300, 300), $sq);
$admin->request('POST', '/admin/identitat/icona/icon-512', ['_csrf' => $admin->csrf('/admin/identitat'), 'icon' => new CURLFile($sq, 'image/png', 'sq.png')]);
$i = getimagesizefromstring($pub->get('/icones/icon-512')['body']);
t('Una icona pujada es redimensiona a la mida correcta', $i && $i[0] === 512 && $i[1] === 512);
$admin->post('/admin/identitat/icona/icon-512/restaura', [], '/admin/identitat');
t('Es pot restaurar la icona per defecte', !\App\Services\IconService::isCustom('icon-512'));
t('Una icona amb nom invàlid és rebutjada', $admin->post('/admin/identitat/icona/..%2Fx', [], '/admin/identitat')['status'] === 404 || !is_file(BASE_PATH . '/storage/uploads/icons/..'));
$r = $admin->post('/admin/identitat/pwa', ['pwa_name' => 'Club', 'pwa_short_name' => 'JSPM', 'pwa_theme_color' => 'vermell', 'pwa_bg_color' => '#ffffff'], '/admin/identitat');
t('Color invàlid rebutjat', $r['status'] === 422);
$admin->post('/admin/identitat/pwa', ['pwa_name' => 'Entrenaments del Club', 'pwa_short_name' => 'Club', 'pwa_theme_color' => '#112233', 'pwa_bg_color' => '#ffeedd'], '/admin/identitat');
$mf = json_decode($pub->get('/manifest.webmanifest')['body'], true);
t('Els ajustos de la PWA es reflecteixen al manifest i al theme-color', $mf['name'] === 'Entrenaments del Club' && $mf['short_name'] === 'Club' && $mf['theme_color'] === '#112233'
    && str_contains($pub->get('/acces')['body'], '#112233'));
t('Un entrenador no pot canviar les icones', $anna->post('/admin/identitat/generar', [], '/exercicis')['status'] === 403);
$admin->post('/admin/identitat/pwa', ['pwa_name' => 'Entrenaments JSPM', 'pwa_short_name' => 'JSPM', 'pwa_theme_color' => '#0b3d91', 'pwa_bg_color' => '#ffffff'], '/admin/identitat');
$admin->post('/admin/identitat/generar', [], '/admin/identitat');

section('Equips, jugadors, horaris i calendari');
$monday = date('Y-m-d', strtotime('next monday'));
$sch = ['1' => ['on' => 1, 'time' => '18:00', 'dur' => 90], '3' => ['on' => 1, 'time' => '19:30', 'dur' => 75]];
$r = $admin->post("/categories/$catCadet", ['name' => 'Cadet', 'sort_order' => 6, 'is_active' => 1, 'sch' => ['1' => ['on' => 1, 'time' => '99:00', 'dur' => 90]]], '/categories');
t('Horari de categoria amb hora invàlida rebutjat', $r['status'] === 422);
$r = $admin->post("/categories/$catCadet", ['name' => 'Cadet', 'sort_order' => 6, 'is_active' => 1, 'sch' => $sch], '/categories');
t('Horari de la categoria desat (dilluns i dimecres)', $r['status'] === 303 && (int) Db::value('SELECT COUNT(*) FROM category_schedules WHERE category_id = ?', [$catCadet]) === 2);
t('L\'equip hereta l\'horari de la categoria', count(\App\Models\Schedule::effectiveForTeam(\App\Models\Team::find($catCadet))) === 2);
$n = (int) Db::value('SELECT COUNT(*) FROM attendance_sessions WHERE team_id = ? AND session_date >= CURDATE()', [$catCadet]);
t('Es creen automàticament les fitxes dels pròxims dies', $n >= 10, "n=$n");
t('No hi ha fitxes duplicades (índex únic)', (int) Db::value('SELECT COUNT(*) FROM (SELECT 1 FROM attendance_sessions GROUP BY team_id, session_date, start_time HAVING COUNT(*) > 1) x') === 0);
t('Hi ha una fitxa el dilluns', (int) Db::value('SELECT COUNT(*) FROM attendance_sessions WHERE team_id = ? AND session_date = ?', [$catCadet, $monday]) === 1);
$r = $admin->post('/calendari/festius', ['date_from' => $monday, 'name' => 'Festiu de prova'], '/calendari');
t('Un festiu elimina la fitxa d\'aquell dia', $r['status'] === 303 && (int) Db::value('SELECT COUNT(*) FROM attendance_sessions WHERE team_id = ? AND session_date = ?', [$catCadet, $monday]) === 0);
t('El calendari mostra el festiu', str_contains($admin->get('/calendari?month=' . substr($monday, 0, 7))['body'], 'Festiu de prova'));
$hid = (int) Db::value('SELECT id FROM holidays WHERE holiday_date = ?', [$monday]);
$admin->post("/calendari/festius/$hid/elimina", [], '/calendari');
t('En eliminar el festiu la fitxa torna a existir', (int) Db::value('SELECT COUNT(*) FROM attendance_sessions WHERE team_id = ? AND session_date = ?', [$catCadet, $monday]) === 1);
$r = $admin->post('/calendari/festius', ['date_from' => $monday, 'date_to' => date('Y-m-d', strtotime($monday . ' +6 days')), 'name' => 'Vacances'], '/calendari');
t('Un període de festius crea un registre per dia', (int) Db::value('SELECT COUNT(*) FROM holidays WHERE name = ?', ['Vacances']) === 7);
$admin->post('/calendari/festius', ['date_from' => $monday, 'date_to' => '2000-01-01', 'name' => 'x'], '/calendari');
t('Un període invers no es desa', (int) Db::value('SELECT COUNT(*) FROM holidays WHERE name = ?', ['x']) === 0);
Db::run("DELETE FROM holidays");
\App\Services\AttendanceService::rebuild();

$r = $admin->post('/equips', ['name' => 'Cadet', 'category_id' => $catCadet, 'is_active' => 1], '/equips/nou');
t('Nom d\'equip duplicat rebutjat', $r['status'] === 422);
$r = $admin->post('/equips', ['name' => 'Cadet B', 'category_id' => $catCadet, 'is_active' => 1, 'trainer_ids' => [$annaId], 'own_schedule' => 1, 'sch' => ['2' => ['on' => 1, 'time' => '20:00', 'dur' => 60]]], '/equips/nou');
$cadetB = (int) Db::value("SELECT id FROM teams WHERE name = 'Cadet B'");
t('Equip creat amb categoria, entrenador i horari propi', $r['status'] === 303 && $cadetB > 0
    && (int) Db::value('SELECT COUNT(*) FROM user_teams WHERE team_id = ? AND user_id = ?', [$cadetB, $annaId]) === 1
    && (int) Db::value('SELECT COUNT(*) FROM team_schedules WHERE team_id = ?', [$cadetB]) === 1);
t('L\'horari propi substitueix el de la categoria', \App\Models\Schedule::effectiveForTeam(\App\Models\Team::find($cadetB))[0]['weekday'] == 2);
t('Un entrenador pot tenir més d\'un equip', count(\App\Models\User::teamIds($annaId)) === 2);
t('Un equip pot tenir més d\'un entrenador', (function () use ($admin, $cadetB, $annaId) {
    $admin->post('/entrenadors', ['first_name' => 'Pau', 'last_name' => 'Segon', 'email' => 'pau@prova.cat', 'is_active' => 1, 'team_ids' => [$cadetB]], '/entrenadors/nou');
    return (int) Db::value('SELECT COUNT(*) FROM user_teams WHERE team_id = ?', [$cadetB]) === 2
        && ($admin->post("/equips/$cadetB", ['name' => 'Cadet B', 'category_id' => 0, 'is_active' => 1, 'trainer_ids' => [$annaId, (int) Db::value("SELECT id FROM users WHERE email = 'pau@prova.cat'")]], '/equips/nou')['status'] === 303)
        && (int) Db::value('SELECT COUNT(*) FROM user_teams WHERE team_id = ?', [$cadetB]) === 2;
})());
$admin->post("/equips/$cadetB", ['name' => 'Cadet B', 'category_id' => $catCadet, 'is_active' => 1, 'trainer_ids' => [$annaId]], '/equips/nou');
t('Sense horari propi l\'equip torna a heretar', count(\App\Models\Schedule::effectiveForTeam(\App\Models\Team::find($cadetB))) === 2);
foreach (['/equips', '/equips/nou', "/equips/$cadetB/editar", '/jugadors', '/jugadors/nou', '/jugadors/importa', '/calendari', '/assistencia/resum', "/assistencia/equip/$cadetB"] as $path) {
    t("Administrador veu $path", $admin->get($path)['status'] === 200);
    t("Entrenador no veu $path", $anna->get($path)['status'] === 403);
}
t('Pàgina d\'equip per a l\'entrenador assignat', $anna->get("/equips/$cadetB")['status'] === 200);
t('Pàgina d\'equip d\'un equip aliè → 404', $anna->get("/equips/$catInfantil")['status'] === 404);

// Jugadors
$r = $admin->post('/jugadors', ['full_name' => 'Marc Prova', 'birth_date' => '2010-05-04', 'team_id' => $catCadet, 'is_active' => 1], '/jugadors/nou');
t('Data de neixament en format incorrecte rebutjada', $r['status'] === 422);
$r = $admin->post('/jugadors', ['full_name' => 'Marc Prova', 'birth_date' => '31/02/2010', 'team_id' => $catCadet, 'is_active' => 1], '/jugadors/nou');
t('Data impossible rebutjada', $r['status'] === 422);
$r = $admin->post('/jugadors', ['full_name' => 'Marc Prova', 'birth_date' => '04/05/2010', 'team_id' => 99999, 'is_active' => 1], '/jugadors/nou');
t('Equip inexistent rebutjat', $r['status'] === 422);
$r = $admin->post('/jugadors', ['full_name' => '<b>Marc</b> Prova', 'birth_date' => '04/05/2010', 'team_id' => $catCadet, 'is_active' => 1], '/jugadors/nou');
$mark = (int) Db::value("SELECT id FROM players WHERE full_name LIKE '%Prova'");
t('Jugador creat amb data DD/MM/AAAA', $r['status'] === 303 && Db::value('SELECT birth_date FROM players WHERE id = ?', [$mark]) === '2010-05-04');
$list = $admin->get('/jugadors?q=Prova')['body'];
t('El llistat mostra la data com a DD/MM/AAAA i escapa HTML', str_contains($list, '04/05/2010') && !str_contains($list, '<b>Marc</b>'));
t('El formulari d\'edició mostra la data com a DD/MM/AAAA', str_contains($admin->get("/jugadors/$mark/editar")['body'], 'value="04/05/2010"'));
Db::insert('players', ['full_name' => 'Laia Segona', 'birth_date' => '2010-01-01', 'team_id' => $catCadet, 'is_active' => 1, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
Db::insert('players', ['full_name' => 'Nil Tercer', 'birth_date' => null, 'team_id' => $catCadet, 'is_active' => 1, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
Db::insert('players', ['full_name' => 'Inactiu Quart', 'birth_date' => null, 'team_id' => $catCadet, 'is_active' => 0, 'created_at' => Db::now(), 'updated_at' => Db::now()]);

// Importació
$csvPath = $tmp . '/jug.csv';
file_put_contents($csvPath, "\xEF\xBB\xBFNom i cognoms;Data de neixament;Equip\nJoan Pons;05/03/2015;Benjamí A\nMarta Sala;31/02/2014;Aleví B\nPere Vila;10/10/2013;Aleví B\n;;\nAnna Soler;01/06/2013;aleví b\nSense Equip;01/01/2012;\n");
$csrf = $admin->csrf('/jugadors/importa');
$r = $admin->request('POST', '/jugadors/importa', ['_csrf' => $csrf, 'file' => new CURLFile($csvPath, 'text/csv', 'jug.csv')]);
t('La previsualització de la importació mostra equips nous i errors', $r['status'] === 200 && str_contains($r['body'], 'Es crearà') && str_contains($r['body'], 'Data de neixament no vàlida') && str_contains($r['body'], 'Falta l&#039;equip'));
t('La previsualització no escriu res', (int) Db::value("SELECT COUNT(*) FROM players WHERE full_name = 'Joan Pons'") === 0 && (int) Db::value("SELECT COUNT(*) FROM teams WHERE name = 'Benjamí A'") === 0);
$r = $admin->post('/jugadors/importa/confirma', [], '/jugadors/importa');
t('Confirmar la importació crea jugadors i equips', $r['status'] === 303 && (int) Db::value("SELECT COUNT(*) FROM players WHERE full_name IN ('Joan Pons','Pere Vila','Anna Soler')") === 3
    && (int) Db::value("SELECT COUNT(*) FROM teams WHERE name IN ('Benjamí A','Aleví B')") === 2);
t('Els equips nous s\'assignen a la categoria pel nom', Db::value("SELECT c.name FROM teams t JOIN categories c ON c.id = t.category_id WHERE t.name = 'Aleví B'") === 'Aleví');
t('Els equips amb el mateix nom (majúscules) no es dupliquen', (int) Db::value("SELECT COUNT(*) FROM teams WHERE name LIKE 'Alev%'") === 2 /* Aleví + Aleví B */);
t('Dates de la importació desades correctament', Db::value("SELECT birth_date FROM players WHERE full_name = 'Joan Pons'") === '2015-03-05');
$r = $admin->post('/jugadors/importa/confirma', [], '/jugadors/importa');
t('La importació no es pot confirmar dues vegades', $r['status'] === 303 && (int) Db::value("SELECT COUNT(*) FROM players WHERE full_name = 'Joan Pons'") === 1);
$csrf = $admin->csrf('/jugadors/importa');
file_put_contents($csvPath, "Nom;Naixement;Equip\nJoan Pons;05/03/2015;Aleví B\nNou Jugador;02/02/2012;Aleví B\n");
$admin->request('POST', '/jugadors/importa', ['_csrf' => $csrf, 'file' => new CURLFile($csvPath, 'text/csv', 'jug.csv')]);
$admin->post('/jugadors/importa/confirma', [], '/jugadors/importa');
t('Reimportar no duplica i actualitza l\'equip', (int) Db::value("SELECT COUNT(*) FROM players WHERE full_name = 'Joan Pons'") === 1
    && Db::value("SELECT t.name FROM players p JOIN teams t ON t.id = p.team_id WHERE p.full_name = 'Joan Pons'") === 'Aleví B'
    && (int) Db::value("SELECT COUNT(*) FROM players WHERE full_name = 'Nou Jugador'") === 1);

// Importació des d'Excel (.xlsx) construït a mà
$xlsx = $tmp . '/jug.xlsx';
$z = new ZipArchive();
$z->open($xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$z->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
$z->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Fulla" sheetId="1" r:id="rId7"/></sheets></workbook>');
$z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId7" Type="x" Target="worksheets/sheet1.xml"/></Relationships>');
$z->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Nom i cognoms</t></si><si><t>Data de neixament</t></si><si><t>Equip</t></si><si><t>Èric Ferrer Gil</t></si><si><t>Infantil A</t></si><si><t>07/08/2011</t></si></sst>');
$z->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
    . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>'
    . '<row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2"><v>40762</v></c><c r="C2" t="s"><v>4</v></c></row>'
    . '<row r="4"><c r="A4" t="inlineStr"><is><t>Júlia Roca</t></is></c><c r="B4" t="s"><v>5</v></c><c r="C4" t="inlineStr"><is><t>Infantil A</t></is></c></row>'
    . '</sheetData></worksheet>');
$z->close();
$parsed = \App\Services\PlayerImport::parseFile($xlsx, 'jug.xlsx');
t('Lector d\'.xlsx: cel·les de text, dates en sèrie i dates en text', count($parsed['rows']) === 2 && $parsed['rows'][0]['name'] === 'Èric Ferrer Gil'
    && $parsed['rows'][0]['birth'] === '2011-08-07' && $parsed['rows'][1]['birth'] === '2011-08-07' && $parsed['rows'][1]['line'] === 4 && !$parsed['errors']);
$csrf = $admin->csrf('/jugadors/importa');
$r = $admin->request('POST', '/jugadors/importa', ['_csrf' => $csrf, 'file' => new CURLFile($xlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'jug.xlsx')]);
$admin->post('/jugadors/importa/confirma', [], '/jugadors/importa');
t('Importació d\'un .xlsx crea l\'equip Infantil A', $r['status'] === 200 && (int) Db::value("SELECT COUNT(*) FROM players p JOIN teams t ON t.id = p.team_id WHERE t.name = 'Infantil A'") === 2);
$phpPath = $tmp . '/mal.php';
file_put_contents($phpPath, '<?php echo 1;');
$csrf = $admin->csrf('/jugadors/importa');
$r = $admin->request('POST', '/jugadors/importa', ['_csrf' => $csrf, 'file' => new CURLFile($phpPath, 'text/plain', 'mal.php')]);
t('Un fitxer amb extensió no admesa es rebutja', $r['status'] === 422);
$badZip = $tmp . '/trencat.xlsx';
file_put_contents($badZip, 'no sóc un zip');
$csrf = $admin->csrf('/jugadors/importa');
$r = $admin->request('POST', '/jugadors/importa', ['_csrf' => $csrf, 'file' => new CURLFile($badZip, 'application/octet-stream', 'trencat.xlsx')]);
t('Un .xlsx corrupte dóna un error comprensible', $r['status'] === 422 && !str_contains($r['body'], 'Stack'));
t('L\'entrenador no pot importar jugadors', $anna->post('/jugadors/importa/confirma', [], '/exercicis')['status'] === 403);
t('Es pot descarregar la plantilla CSV', str_contains($admin->get('/jugadors/plantilla')['body'], 'Nom i cognoms;Data de neixament;Equip'));

// Assistència
$today = date('Y-m-d');
\App\Models\Attendance::addManual($catCadet, $today, '09:00:00', 90);
\App\Models\Attendance::addManual($cadetB, date('Y-m-d', strtotime('+3 days')), '09:00:00', 90);
\App\Models\Attendance::addManual($catInfantil, $today, '09:00:00', 90);
$sToday = (int) Db::value('SELECT id FROM attendance_sessions WHERE team_id = ? AND session_date = ? AND start_time = "09:00:00"', [$catCadet, $today]);
$sFuture = (int) Db::value('SELECT id FROM attendance_sessions WHERE team_id = ? AND start_time = "09:00:00" AND session_date > CURDATE()', [$cadetB]);
$sOther = (int) Db::value('SELECT id FROM attendance_sessions WHERE team_id = ? AND session_date = ?', [$catInfantil, $today]);
$laia = (int) Db::value("SELECT id FROM players WHERE full_name = 'Laia Segona'");
$nil = (int) Db::value("SELECT id FROM players WHERE full_name = 'Nil Tercer'");
$inactiu = (int) Db::value("SELECT id FROM players WHERE full_name = 'Inactiu Quart'");
t('Dos entrenadors del mateix equip no es barallen: fitxa visible', ($sheet = $anna->get("/assistencia/$sToday"))['status'] === 200);
t('La fitxa mostra els jugadors actius però no els inactius', str_contains($sheet['body'], 'Laia Segona') && str_contains($sheet['body'], 'Nil Tercer') && !str_contains($sheet['body'], 'Inactiu Quart'));
t('El panell de l\'entrenador mostra l\'entrenament d\'avui', str_contains($anna->get('/')['body'], 'Cadet'));
t('Un entrenador no veu la fitxa d\'un equip que no porta', $anna->get("/assistencia/$sOther")['status'] === 404);
t('Un entrenador no pot marcar assistència d\'un altre equip', $anna->post("/assistencia/$sOther", ['status' => [$laia => 'present']], "/assistencia/$sToday")['status'] === 404
    && (int) Db::value('SELECT COUNT(*) FROM attendance_records WHERE session_id = ?', [$sOther]) === 0);
$r = $anna->post("/assistencia/$sToday", ['status' => [$mark => 'present', $laia => 'late', $nil => 'absent', $inactiu => 'absent', $catInfantil => 'hack']], "/assistencia/$sToday");
$rec = \App\Models\Attendance::records($sToday);
t('L\'entrenador marca assistència, retard i falta', $r['status'] === 303 && ($rec[$mark] ?? '') === 'present' && ($rec[$laia] ?? '') === 'late' && ($rec[$nil] ?? '') === 'absent');
t('No es marquen jugadors inactius ni estats invàlids', !isset($rec[$inactiu]) && count($rec) === 3);
$anna->post("/assistencia/$sToday", ['status' => [$mark => '', $laia => 'present', $nil => 'absent']], "/assistencia/$sToday");
$rec = \App\Models\Attendance::records($sToday);
t('Es pot corregir i desmarcar un jugador', !isset($rec[$mark]) && $rec[$laia] === 'present');
$anna->post("/assistencia/$sToday", ['status' => [$laia => 'late'], 'action' => 'all_present'], "/assistencia/$sToday");
$rec = \App\Models\Attendance::records($sToday);
t('«Tots presents» només omple els que no tenen marca', $rec[$mark] === 'present' && $rec[$laia] === 'late' && $rec[$nil] === 'absent');
$r = $anna->post("/assistencia/$sFuture", ['status' => [$laia => 'present']], "/assistencia/$sToday");
t('No es pot passar llista d\'un entrenament futur', $r['status'] === 303 && (int) Db::value('SELECT COUNT(*) FROM attendance_records WHERE session_id = ?', [$sFuture]) === 0);
t('La fitxa d\'un entrenament futur és de només lectura', !str_contains($anna->get("/assistencia/$sFuture")['body'], 'Desa l&#039;assistència'));
t('L\'administrador no pot cancel·lar sense CSRF vàlid', $admin->post("/assistencia/$sToday/cancela", [], '/', false)['status'] === 419);
t('L\'entrenador no pot cancel·lar entrenaments', $anna->post("/assistencia/$sToday/cancela", [], "/assistencia/$sToday")['status'] === 403);
t('Les marques de la CSRF falsa no s\'accepten', $anna->post("/assistencia/$sToday", ['status' => [$laia => 'absent']], '/', false)['status'] === 419 && \App\Models\Attendance::records($sToday)[$laia] === 'late');

// Resums
$w = $admin->get('/assistencia/resum')['body'];
t('El resum setmanal mostra el jugador que ha faltat i el que ha arribat tard', str_contains($w, 'Nil Tercer') && str_contains($w, 'Laia Segona') && str_contains($w, 'Cadet'));
t('El resum setmanal no mostra qui ha vingut', !str_contains($w, 'Marc') || !str_contains($w, 'Marc</b> Prova'));
$oldWeek = $admin->get('/assistencia/resum?week=2000-01-03')['body'];
t('Una setmana sense dades ho indica', str_contains($oldWeek, 'Cap jugador ha faltat'));
t('Una setmana amb format invàlid no trenca la pàgina', $admin->get('/assistencia/resum?week=xx')['status'] === 200);
$st = $admin->get("/assistencia/equip/$catCadet")['body'];
t('L\'estadística per equip mostra faltes i percentatge', str_contains($st, 'Nil Tercer') && str_contains($st, '0 %') && str_contains($st, 'Laia Segona'));
t('Dashboard de l\'administrador mostra les faltes de la setmana', str_contains($admin->get('/')['body'], 'Faltes aquesta setmana'));
$admin->post("/assistencia/$sToday/cancela", [], '/assistencia');
t('L\'administrador cancel·la un entrenament i queda de només lectura', Db::value('SELECT status FROM attendance_sessions WHERE id = ?', [$sToday]) === 'cancelled'
    && $anna->post("/assistencia/$sToday", ['status' => [$laia => 'absent']], "/assistencia/$sToday")['status'] === 303 && \App\Models\Attendance::records($sToday)[$laia] === 'late');
t('Un entrenament cancel·lat no compta a les faltes', !str_contains($admin->get('/assistencia/resum')['body'], 'Nil Tercer'));
$admin->post("/assistencia/$sToday/cancela", [], '/assistencia');
t('Es pot restaurar un entrenament cancel·lat', Db::value('SELECT status FROM attendance_sessions WHERE id = ?', [$sToday]) === 'scheduled');
$r = $admin->post('/assistencia/extra', ['team_id' => $catCadet, 'date' => $today, 'start_time' => '21:00', 'duration' => 60], '/calendari');
t('Entrenament extra afegit al calendari', $r['status'] === 303 && (int) Db::value("SELECT COUNT(*) FROM attendance_sessions WHERE team_id = ? AND session_date = ? AND source = 'manual' AND start_time = '21:00:00'", [$catCadet, $today]) === 1);
t('Un jugador eliminat s\'emporta el seu historial', (function () use ($admin, $nil) {
    $admin->post("/jugadors/$nil/eliminar", [], '/jugadors');
    return (int) Db::value('SELECT COUNT(*) FROM attendance_records WHERE player_id = ?', [$nil]) === 0 && (int) Db::value('SELECT COUNT(*) FROM players WHERE id = ?', [$nil]) === 0;
})());
t('Un equip amb jugadors no es pot eliminar', (function () use ($admin, $catCadet) {
    $admin->post("/equips/$catCadet/eliminar", [], '/equips');
    return (int) Db::value('SELECT COUNT(*) FROM teams WHERE id = ?', [$catCadet]) === 1;
})());
t('Sessió anònima no accedeix a l\'assistència', (new Client($base))->get('/assistencia')['status'] === 303);

section('Sessions, desactivació i tancament');
Db::run('UPDATE users SET is_active = 0 WHERE id = ?', [$annaId]);
t('Un entrenador desactivat perd l\'accés immediatament', $anna->get('/')['status'] === 303);
t('Un entrenador desactivat no pot iniciar sessió', (new Client($base))->login('anna@prova.cat', 'Recuperada2026x')['status'] === 422);
Db::run('UPDATE users SET is_active = 1 WHERE id = ?', [$annaId]);
$c = new Client($base);
$c->login('anna@prova.cat', 'Recuperada2026x');
$r = $c->post('/sortir', [], '/');
t('Tancament de sessió', $r['status'] === 303 && $c->get('/')['status'] === 303);
t('Registre d\'auditoria: accions clau', (int) Db::value("SELECT COUNT(DISTINCT action) FROM audit_logs WHERE action IN ('login','logout','password_changed','trainer_created','exercise_created','training_created','smtp_settings_changed')") === 7);
t('Auditoria sense tokens ni contrasenyes', (int) Db::value("SELECT COUNT(*) FROM audit_logs WHERE details REGEXP '[a-f0-9]{32}'") === 0);
t('Rutes inexistents retornen 404 sense detalls tècnics', ($x = $admin->get('/no-existeix'))['status'] === 404 && !str_contains($x['body'], 'Stack') && !str_contains($x['body'], BASE_PATH));

echo "\n" . ($failed === 0 ? "\033[32m" : "\033[31m") . "Resultat: $passed correctes, $failed fallides\033[0m\n";
exit($failed === 0 ? 0 : 1);
