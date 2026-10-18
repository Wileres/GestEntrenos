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

$r = $admin->post('/entrenadors', ['first_name' => 'Anna', 'last_name' => 'Puig', 'email' => 'anna@prova.cat', 'is_active' => 1, 'category_ids' => [$catCadet]], '/entrenadors/nou');
t('Entrenador creat', $r['status'] === 303);
$r = $admin->post("/categories/$catCadet/eliminar", [], '/categories');
t('No es pot eliminar una categoria amb entrenadors assignats', (int) Db::value('SELECT COUNT(*) FROM categories WHERE id = ?', [$catCadet]) === 1);
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
foreach (['/entrenadors', '/entrenadors/nou', '/categories', '/categories', '/admin/correu', '/admin/registre', '/admin/correus', '/admin/logotip', '/exercicis/nou', '/entrenaments/nou'] as $path) {
    t("Entrenador no pot accedir a $path", $anna->get($path)['status'] === 403);
}
$r = $anna->post('/categories', ['name' => 'Hackers', 'sort_order' => 1], '/exercicis');
t('Entrenador no pot crear categories (POST amb CSRF vàlid)', $r['status'] === 403 && (int) Db::value("SELECT COUNT(*) FROM categories WHERE name = 'Hackers'") === 0);
$r = $anna->post("/entrenadors/$annaId", ['first_name' => 'Anna', 'last_name' => 'Puig', 'email' => 'anna@prova.cat', 'category_ids' => [$catInfantil]], '/exercicis');
t('Entrenador no pot assignar-se categories', $r['status'] === 403 && User_categories($annaId) === [$catCadet]);
function User_categories(int $id): array
{
    return array_map('intval', Db::column('SELECT category_id FROM user_categories WHERE user_id = ?', [$id]));
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
    'players' => 8, 'duration_minutes' => 15, 'difficulty' => 'easy', 'is_active' => 1, 'extra_categories' => [$catBenjami, $catInfantil],
    'files' => [new CURLFile($pdfPath, 'application/pdf', 'sessio.pdf')],
]);
t('Exercici creat amb PDF adjunt', $r['status'] === 303);
$exBenjami = (int) Db::value("SELECT id FROM exercises WHERE name = 'Rondo 4x2'");
$fileEx = (int) Db::value('SELECT id FROM exercise_files WHERE exercise_id = ?', [$exBenjami]);
t('El fitxer es desa amb nom aleatori sense extensió fora de public', $fileEx > 0 && preg_match('/^[a-f0-9]{40}$/', (string) Db::value('SELECT stored_name FROM exercise_files WHERE id = ?', [$fileEx])) === 1
    && is_file(BASE_PATH . '/storage/uploads/exercises/' . Db::value('SELECT stored_name FROM exercise_files WHERE id = ?', [$fileEx])));
$r = $admin->request('POST', '/exercicis', ['_csrf' => $csrf, 'name' => 'Maliciós', 'description' => 'x', 'extra_categories' => [$catCadet], 'is_active' => 1,
    'files' => [new CURLFile($evilPath, 'application/pdf', 'hack.pdf')]]);
t('Pujada d\'un PHP disfressat de PDF rebutjada', $r['status'] === 422 && (int) Db::value("SELECT COUNT(*) FROM exercises WHERE name = 'Maliciós'") === 0);
$r = $admin->request('POST', '/exercicis', ['_csrf' => $csrf, 'name' => 'Maliciós', 'description' => 'x', 'extra_categories' => [$catCadet], 'is_active' => 1,
    'files' => [new CURLFile($dblPath, 'application/pdf', 'hack.php.pdf')]]);
t('Pujada amb doble extensió rebutjada', $r['status'] === 422);
$admin->post('/exercicis', ['name' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>', 'extra_categories' => [$catCadet], 'is_active' => 1], '/exercicis/nou');
$admin->post('/exercicis', ['name' => 'Inactiu secret', 'description' => 'x', 'extra_categories' => [$catCadet]], '/exercicis/nou');
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
$r = $anna->get('/exercicis?category=' . $catInfantil);
t('Filtre per categoria addicional funciona', str_contains($r['body'], 'Rondo 4x2'));
$r = $anna->get('/exercicis?players=8&difficulty=easy&material=Cons&objective=Passada');
t('Filtres combinats (jugadors, dificultat, material, objectiu)', str_contains($r['body'], 'Rondo 4x2'));
$r = $anna->get('/exercicis?players=9');
t('Filtre per nombre de jugadors exclou no coincidents', !str_contains($r['body'], 'Rondo 4x2'));

section('Entrenaments: restricció per categoria/equip');
$mkTraining = function (string $title, array $cats, array $extra = []) use ($admin): int {
    $admin->post('/entrenaments', array_merge([
        'title' => $title, 'session_date' => date('Y-m-d', time() + 86400 * 3), 'start_time' => '18:30', 'duration_minutes' => 90,
        'season' => '2026-2027', 'description' => 'Descripció', 'category_ids' => $cats, 'action' => 'save',
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
    'start_time' => '18:30', 'duration_minutes' => 90, 'season' => '2026-2027', 'category_ids' => [$catCadet], 'action' => 'add', 'add_exercise' => $exBenjami]);
t('Afegir exercici mostra el formulari actualitzat sense desar', $r['status'] === 200 && str_contains($r['body'], 'Rondo 4x2'));
$rows = [['exercise_id' => $exBenjami, 'duration' => 10, 'notes' => 'Primer'], ['exercise_id' => $exXss, 'duration' => 20, 'notes' => 'Segon']];
$admin->post("/entrenaments/$trOwn", ['title' => 'Sessió Cadet', 'session_date' => date('Y-m-d', time() + 86400 * 3), 'start_time' => '18:30', 'duration_minutes' => 90,
    'season' => '2026-2027', 'category_ids' => [$catCadet], 'action' => 'save', 'ex' => $rows], '/entrenaments/nou');
t('Exercicis desats amb ordre', Db::column('SELECT exercise_id FROM training_exercises WHERE training_id = ? ORDER BY position', [$trOwn]) === [(string) $exBenjami, (string) $exXss]
    || array_map('intval', Db::column('SELECT exercise_id FROM training_exercises WHERE training_id = ? ORDER BY position', [$trOwn])) === [$exBenjami, $exXss]);
$r = $admin->request('POST', "/entrenaments/$trOwn", ['_csrf' => $admin->csrf('/entrenaments/nou'), 'title' => 'Sessió Cadet', 'session_date' => date('Y-m-d', time() + 86400 * 3),
    'start_time' => '18:30', 'duration_minutes' => 90, 'season' => '2026-2027', 'category_ids' => [$catCadet], 'action' => 'down:0', 'ex' => $rows]);
preg_match_all('/name="ex\[(\d)\]\[exercise_id\]" value="(\d+)"/', $r['body'], $mm);
t('Botó "Baixa" reordena els exercicis', array_map('intval', $mm[2]) === [$exXss, $exBenjami]);
$r = $admin->request('POST', "/entrenaments/$trOwn", ['_csrf' => $admin->csrf('/entrenaments/nou'), 'title' => 'Sessió Cadet', 'session_date' => date('Y-m-d', time() + 86400 * 3),
    'start_time' => '18:30', 'duration_minutes' => 90, 'season' => '2026-2027', 'category_ids' => [$catCadet], 'action' => 'remove:0', 'ex' => $rows]);
t('Treure un exercici de l\'entrenament no l\'esborra del catàleg', substr_count($r['body'], 'name="ex[') >= 1 && (int) Db::value('SELECT COUNT(*) FROM exercises WHERE id = ?', [$exBenjami]) === 1);

// Fitxer adjunt a entrenaments
$admin->request('POST', "/entrenaments/$trOther", ['_csrf' => $admin->csrf('/entrenaments/nou'), 'title' => 'Sessió Infantil', 'session_date' => date('Y-m-d', time() + 86400 * 3),
    'start_time' => '18:30', 'duration_minutes' => 90, 'season' => '2026-2027', 'category_ids' => [$catInfantil], 'action' => 'save',
    'files' => [new CURLFile($pdfPath, 'application/pdf', 'pla-infantil.pdf')]]);
$admin->request('POST', "/entrenaments/$trOwn", ['_csrf' => $admin->csrf('/entrenaments/nou'), 'title' => 'Sessió Cadet', 'session_date' => date('Y-m-d', time() + 86400 * 3),
    'start_time' => '18:30', 'duration_minutes' => 90, 'season' => '2026-2027', 'category_ids' => [$catCadet], 'action' => 'save',
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
    Db::insert('user_categories', ['user_id' => $uid, 'category_id' => $catCadet]);
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
    'season' => '2026-2027', 'category_ids' => [$catCadet], 'action' => 'save'], '/entrenaments/nou');
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
$r = $admin->request('POST', '/admin/logotip', ['_csrf' => $admin->csrf('/admin/logotip'), 'logo' => new CURLFile($logoPath, 'image/png', 'logo.png')]);
t('Logotip pujat', $r['status'] === 303 && \App\Services\SettingsService::logoPath() !== null);
$lg = (new Client($base))->get('/logotip');
t('El logotip es serveix públicament (pantalla d\'accés)', $lg['status'] === 200 && str_starts_with($lg['headers']['content-type'] ?? '', 'image/png'));
$r = $admin->request('POST', '/admin/logotip', ['_csrf' => $admin->csrf('/admin/logotip'), 'logo' => new CURLFile($pdfPath, 'application/pdf', 'logo.pdf')]);
t('Logotip amb tipus no permès rebutjat', $r['status'] === 303 && str_contains($admin->get('/admin/logotip')['body'], 'no està permès'));

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
