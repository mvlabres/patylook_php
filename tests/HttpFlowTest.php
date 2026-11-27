<?php

declare(strict_types=1);

namespace Patylook\Tests;

use Patylook\Core\Application;
use Patylook\Core\Config;
use Patylook\Core\Container;
use Patylook\Core\Env;
use Patylook\Core\Request;
use Patylook\Core\Response;
use PDO;
use Throwable;

/**
 * End-to-end test: the real application (router, middleware, controllers, services, MySQL) driven
 * in-process, one request at a time. It uses its own database, TEST_DB_NAME (default
 * patylook_php_test), created from schema.sql, and never touches the configured DB_NAME.
 * Skipped when MySQL is not reachable.
 */
final class HttpFlowTest extends TestCase
{
    private const ADMIN_PASSWORD = 'admin-pass-123';

    private string $tmp;
    private string $database;
    private ?PDO $pdo = null;
    private Application $app;

    /** @var array<string, string> cookies of the current "browser" */
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->database = (string) (getenv('TEST_DB_NAME') ?: 'patylook_php_test');
        if (!str_ends_with($this->database, '_test')) {
            $this->skip('TEST_DB_NAME deve terminar em _test (proteção contra apagar o banco real)');
        }
        $base = dirname(__DIR__);
        // The .env supplies the MySQL credentials; everything else is pinned for the test.
        Env::load($base . '/.env');
        $host = (string) (getenv('DB_HOST') ?: '127.0.0.1');
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $user = (string) getenv('DB_USER');
        $pass = (string) getenv('DB_PASSWORD');
        try {
            $this->pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Throwable $e) {
            $this->skip('MySQL indisponível: ' . $e->getMessage());
        }

        $this->tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pl_http_' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/images', 0o777, true);
        mkdir($this->tmp . '/storage', 0o777, true);

        $schema = str_replace('patylook', $this->database, (string) file_get_contents($base . '/schema.sql'));
        $this->pdo->exec('DROP DATABASE IF EXISTS `' . $this->database . '`');
        $this->pdo->exec($schema);
        $this->pdo->exec('ALTER TABLE `' . $this->database . '`.`user` MODIFY COLUMN `password` VARCHAR(255) NOT NULL');

        foreach ([
            'APP_ENV' => 'development', 'DB_NAME' => $this->database, 'IMAGES_DIR' => $this->tmp . '/images',
            'STORAGE_DIR' => $this->tmp . '/storage', 'BOOTSTRAP_ADMIN_USERNAME' => 'admin',
            'BOOTSTRAP_ADMIN_PASSWORD' => self::ADMIN_PASSWORD, 'BOOTSTRAP_ADMIN_NAME' => 'Admin',
        ] as $key => $value) {
            putenv("$key=$value");
        }
        $config = Config::load($base);
        $container = new Container();
        (require $base . '/config/container.php')($container, $config);
        $this->app = $container->get(Application::class);
        $this->cookies = [];
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $this->pdo->exec('DROP DATABASE IF EXISTS `' . $this->database . '`');
        }
        if (isset($this->tmp)) {
            $this->rmTree($this->tmp);
        }
    }

    private function rmTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                is_dir($path) ? $this->rmTree($path) : @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /** @param array<string, mixed> $query */
    private function get(string $path, array $query = [], array $headers = []): Response
    {
        return $this->send('GET', $path, $query, [], $headers);
    }

    /** @param array<string, mixed> $data */
    private function post(string $path, array $data, array $headers = []): Response
    {
        return $this->send('POST', $path, [], $data, $headers);
    }

    /** @param array<string, string> $headers */
    private function send(string $method, string $path, array $query, array $data, array $headers): Response
    {
        $server = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $response = $this->app->handle(new Request($method, $path, $query, $data, [], $this->cookies, $server));
        foreach ($response->cookies() as $cookie) {
            if ($cookie['value'] === '') {
                unset($this->cookies[$cookie['name']]);
            } else {
                $this->cookies[$cookie['name']] = $cookie['value'];
            }
        }
        return $response;
    }

    private function csrf(string $page): string
    {
        $this->assertSame(1, preg_match('/name="csrf_token" value="([^"]+)"/', $this->get($page)->body(), $m), "token CSRF em $page");
        return $m[1];
    }

    /** @param array<string, mixed> $data */
    private function submit(string $page, string $action, array $data): Response
    {
        return $this->post($action, ['csrf_token' => $this->csrf($page)] + $data);
    }

    private function login(string $user = 'admin', string $password = self::ADMIN_PASSWORD): Response
    {
        return $this->post('/login', ['username' => $user, 'password' => $password]);
    }

    private function location(Response $r): string
    {
        return $r->headers()['Location'] ?? '';
    }

    private function scalar(string $sql): mixed
    {
        return $this->pdo?->query($sql)?->fetchColumn();
    }

    public function testPublicRoutesAndAuthentication(): void
    {
        $this->assertSame('ok', $this->get('/healthz')->body());
        $this->assertSame(200, $this->get('/login')->status());
        $r = $this->get('/');
        $this->assertSame([303, '/login'], [$r->status(), $this->location($r)]);
        // Unknown paths and methods never reveal anything to a visitor without a session: login.
        foreach (['/nao-existe', '/login/x', '/static/nada.css'] as $path) {
            $r = $this->get($path);
            $this->assertSame([303, '/login'], [$r->status(), $this->location($r)], $path);
        }
        $r = $this->post('/healthz', []);
        $this->assertSame([303, '/login'], [$r->status(), $this->location($r)]);

        $this->assertSame(401, $this->login('admin', 'errada')->status());
        $ok = $this->login();
        $this->assertSame([303, '/'], [$ok->status(), $this->location($ok)]);
        $cookie = $ok->cookies()[0];
        $this->assertTrue($cookie['options']['httponly'] && $cookie['options']['samesite'] === 'Lax', 'cookie HttpOnly e SameSite=Lax');

        $dash = $this->get('/');
        $this->assertSame(200, $dash->status());
        $this->assertContains('<h1>Painel</h1>', $dash->body());
        $this->assertContains('Content-Security-Policy', implode(',', array_keys($dash->headers())));
        $this->assertSame('/', $this->location($this->get('/login')), 'logado volta ao painel');
        $this->assertSame(404, $this->get('/nao-existe')->status(), 'logado vê o 404');
    }

    public function testPublicShowcaseNeedsNoLoginAndExposesNothingElse(): void
    {
        $this->login();
        $this->submit('/categories/new', '/categories', ['name' => 'Perfumaria']);
        $this->submit('/brands/new', '/brands', ['name' => 'Ferrari']);
        $db = $this->database;
        $this->pdo?->exec("INSERT INTO `$db`.product (name, sku, product_category_id, brand_id, stock, is_active, created_by) VALUES
            ('Perfume Ativo', 'PER-ATIVO', 1, 1, 10, 1, 1), ('Perfume Inativo', 'PER-INATIVO', 1, 1, 10, 0, 1)");
        $this->pdo?->exec("INSERT INTO `$db`.product_images (product_id, file_name, path, sort_order, created_by) VALUES
            (1, 'principal.jpg', 'images/products/PER-ATIVO/principal.jpg', 0, 1), (2, 'principal.jpg', 'images/products/PER-INATIVO/principal.jpg', 0, 1)");
        foreach (['PER-ATIVO', 'PER-INATIVO'] as $folder) {
            mkdir($this->tmp . '/images/' . $folder, 0o777, true);
            file_put_contents($this->tmp . '/images/' . $folder . '/principal.jpg', 'jpg');
        }
        $this->post('/logout', ['csrf_token' => $this->csrf('/')]);
        $this->assertSame('/login', $this->location($this->get('/')), 'deslogado');

        // The page: title and search only, no menu or management actions, active products only.
        $page = $this->get('/mostruario');
        $this->assertSame(200, $page->status());
        $body = $page->body();
        $this->assertContains('<h1>Mostruário</h1>', $body);
        $this->assertContains('Buscar por nome ou SKU', $body);
        $this->assertContains('Perfume Ativo', $body);
        $this->assertFalse(str_contains($body, 'Perfume Inativo'), 'produto inativo fica de fora');
        foreach (['class="nav"', 'Gerenciar produtos', 'href="/orders"', 'href="/products"', '/logout', 'csrf_token'] as $hidden) {
            $this->assertFalse(str_contains($body, $hidden), "não deve aparecer: $hidden");
        }
        $this->assertContains('/mostruario/imagens/PER-ATIVO/principal.jpg', $body);
        $this->assertFalse(str_contains($body, 'wa.me'), 'sem vendedor cadastrado não há link de WhatsApp');

        // With a seller registered, each product links to a pre-filled WhatsApp chat (area code + number gets 55).
        $this->pdo?->exec("INSERT INTO `$db`.main_seller (name, whatsapp) VALUES ('Patricia', '(45) 92002-0405')");
        $text = rawurlencode('Olá. Gostaria de saber mais detalhes do produto Perfume Ativo - PER-ATIVO.');
        $this->assertContains('https://wa.me/5545920020405?text=' . $text, $this->get('/mostruario')->body());
        $this->assertContains('Perfume Ativo', $this->get('/mostruario', ['q' => 'ATIVO'])->body());

        // Gender filter: "Todos" by default, one gender when chosen, unknown values mean "Todos".
        $this->pdo?->exec("UPDATE `$db`.product SET gender = 'male' WHERE id = 1");
        $this->pdo?->exec("INSERT INTO `$db`.product (name, sku, product_category_id, brand_id, gender, stock, is_active, created_by) VALUES ('Perfume Feminino', 'PER-FEM', 1, 1, 'female', 5, 1, 1)");
        $all = $this->get('/mostruario')->body();
        $this->assertContains('Perfume Ativo', $all);
        $this->assertContains('Perfume Feminino', $all);
        $this->assertContains('<option value="">Todos</option>', $all);
        foreach (['Masculino', 'Feminino', 'Unissex'] as $label) {
            $this->assertContains('>' . $label . '</option>', $all);
        }
        $this->assertFalse(str_contains($all, 'selected'), 'Todos é o padrão: nenhuma outra opção marcada');
        $male = $this->get('/mostruario', ['gender' => 'male'])->body();
        $this->assertContains('Perfume Ativo', $male);
        $this->assertFalse(str_contains($male, 'Perfume Feminino'));
        $this->assertContains('value="male" selected', $male, 'o filtro aplicado fica marcado');
        $female = $this->get('/mostruario', ['gender' => 'female', 'q' => 'Perfume'])->body();
        $this->assertContains('Perfume Feminino', $female);
        $this->assertFalse(str_contains($female, 'Perfume Ativo'));
        $this->assertContains('Nenhum produto ativo para exibir.', $this->get('/mostruario', ['gender' => 'unisex'])->body());
        $this->assertContains('Perfume Feminino', $this->get('/mostruario', ['gender' => 'robo'])->body(), 'valor desconhecido = Todos');

        // Images: only through the public route, and only for active products.
        $this->assertSame(200, $this->get('/mostruario/imagens/PER-ATIVO/principal.jpg')->status());
        $this->assertSame(404, $this->get('/mostruario/imagens/PER-INATIVO/principal.jpg')->status());
        $this->assertSame(404, $this->get('/mostruario/imagens/PER-ATIVO/outra.jpg')->status());
        $this->assertSame(404, $this->get('/mostruario/imagens/%2E%2E/principal.jpg')->status());

        // Every other address, existing or not, sends the visitor to the login.
        foreach (['/images/products/PER-ATIVO/principal.jpg', '/showcase', '/products', '/orders', '/users', '/profile',
                  '/mostruario/', '/mostruario/x', '/mostruario/imagens', '/mostruario/imagens/PER-ATIVO', '/mostruario/imagens/PER-ATIVO/principal.jpg/x', '/Mostruario', '/nao-existe'] as $path) {
            $r = $this->get($path);
            $this->assertSame([303, '/login'], [$r->status(), $this->location($r)], $path);
        }
        $r = $this->post('/mostruario', []);
        $this->assertSame([303, '/login'], [$r->status(), $this->location($r)], 'POST /mostruario');
    }

    public function testCsrfAndCrossOriginAreRejected(): void
    {
        $this->login();
        $this->assertSame(403, $this->post('/categories', ['name' => 'X'])->status(), 'sem token CSRF');
        $token = $this->csrf('/categories/new');
        $this->assertSame(403, $this->post('/categories', ['csrf_token' => 'forjado', 'name' => 'X'])->status());
        $this->assertSame(403, $this->post('/categories', ['csrf_token' => $token, 'name' => 'X'], ['Origin' => 'http://evil.example'])->status());
        $this->assertSame(403, $this->post('/categories', ['csrf_token' => $token, 'name' => 'X'], ['Sec-Fetch-Site' => 'cross-site'])->status());
        $this->assertSame(303, $this->post('/categories', ['csrf_token' => $token, 'name' => 'X'], ['Origin' => 'http://localhost'])->status());
    }

    public function testCatalogCustomersAndOrderLifecycle(): void
    {
        $this->login();

        // Master data through the forms.
        $r = $this->submit('/categories/new', '/categories', ['name' => 'Perfumaria', 'description' => 'Perfumes']);
        $this->assertSame('/categories', $this->location($r));
        $r = $this->submit('/categories/new', '/categories', ['name' => 'Perfumaria']);
        $this->assertSame(422, $r->status());
        $this->assertContains('Já existe uma categoria com esse nome', $r->body());
        $this->submit('/brands/new', '/brands', ['name' => 'Ferrari']);
        $r = $this->submit('/customers/new', '/customers', ['name' => 'Maria', 'document' => '529.982.247-25', 'phone' => '1199', 'email' => 'M@X.com']);
        $this->assertSame('/customers', $this->location($r));
        $this->assertSame('52998224725', $this->scalar('SELECT document FROM ' . $this->database . '.customer'));
        $this->assertSame(422, $this->submit('/customers/new', '/customers', ['name' => 'X', 'document' => '123', 'phone' => '1', 'email' => 'bad'])->status());
        $this->assertContains('Débito', $this->get('/payment-types/new')->body(), 'campo de categoria no cadastro');
        $r = $this->submit('/payment-types/new', '/payment-types', ['name' => 'Sem categoria', 'description' => 'x', 'surcharge_percent' => '0']);
        $this->assertSame(422, $r->status());
        $this->assertContains('Categoria é obrigatória', $r->body());
        $this->assertSame(422, $this->submit('/payment-types/new', '/payment-types', ['name' => 'Inválida', 'description' => 'x', 'category' => 'cheque'])->status());
        $r = $this->submit('/payment-types/new', '/payment-types', ['name' => 'Cartão', 'description' => 'Parcelado', 'surcharge_percent' => '5,00', 'category' => 'credit']);
        $this->assertSame('/payment-types', $this->location($r));
        $this->assertSame('credit', $this->scalar("SELECT category FROM `{$this->database}`.payment_type WHERE name = 'Cartão'"));
        $this->assertContains('>Crédito<', $this->get('/payment-types')->body(), 'categoria na lista');

        // A product (created with images through the upload form in production; seeded here).
        $db = $this->database;
        $this->pdo?->exec("INSERT INTO `$db`.product (name, sku, product_category_id, brand_id, stock, is_active, created_by) VALUES ('Perfume', 'PER-FER-2411261602', 1, 1, 10, 1, 1)");

        // Gender: new products come from the form (see the model tests); here the edit form keeps it valid.
        $this->assertContains('Unissex', $this->get('/products/new')->body(), 'campo de gênero no cadastro');
        $form = ['name' => 'Perfume', 'product_category_id' => '1', 'brand_id' => '1', 'stock' => '10'];
        $r = $this->submit('/products/1/edit', '/products/1', $form);
        $this->assertSame(422, $r->status());
        $this->assertContains('Gênero é obrigatório', $r->body());
        $this->assertSame(422, $this->submit('/products/1/edit', '/products/1', $form + ['gender' => 'robo'])->status(), 'valor desconhecido é recusado');
        $r = $this->submit('/products/1/edit', '/products/1', $form + ['gender' => 'female']);
        $this->assertSame('/products', $this->location($r));
        $this->assertSame('female', $this->scalar("SELECT gender FROM `$db`.product WHERE id = 1"));

        // Prices: below cost is refused, a valid one derives the markup.
        $r = $this->submit('/products/1/prices', '/products/1/prices', ['product_cost' => '50,00', 'packaging_cost' => '10', 'final_price' => '59,99', 'valid_from' => '2026-01-01']);
        $this->assertSame(422, $r->status());
        $this->assertContains('Preço final não pode ser menor que o custo total (R$ 60,00)', $r->body());
        $r = $this->submit('/products/1/prices', '/products/1/prices', ['product_cost' => '50,00', 'packaging_cost' => '10', 'final_price' => '90,00', 'valid_from' => '2026-01-01']);
        $this->assertSame('/products/1/prices', $this->location($r));
        $this->assertSame('50.00', $this->scalar("SELECT markup_percent FROM `$db`.pricebook"));
        $this->assertContains('Preço cadastrado.', $this->get('/products/1/prices')->body(), 'mensagem flash exibida uma vez');
        $list = $this->get('/products')->body();
        $this->assertContains('R$ 60,00', $list, 'custos somados na lista');
        $this->assertContains('50,00%', $list, 'markup na lista');
        $this->assertContains('R$ 30,00', $list, 'ganho real = preço - custos');
        $this->assertContains('<tfoot>', $list, 'linha de totais');
        $this->assertFalse(str_contains($this->get('/products/1/prices')->body(), 'Preço cadastrado.'), 'flash consumida');

        // Order: totals, snapshots and stock.
        $order = ['customer_id' => '1', 'payment_type_id' => '1', 'discount' => '5,00', 'notes' => 'teste',
            'product_id' => ['1'], 'quantity' => ['2'], 'item_discount' => ['10,00'], 'installments' => '3'];
        $r = $this->submit('/orders/new', '/orders', ['quantity' => ['99']] + ['customer_id' => '1', 'payment_type_id' => '1', 'product_id' => ['1'], 'installments' => '3']);
        $this->assertSame(422, $r->status());
        $this->assertContains('Estoque insuficiente para Perfume', $r->body());

        // Credit payments need the installments (1 to 24); the form offers the field and keeps what was typed.
        $form = $this->get('/orders/new')->body();
        $this->assertContains('data-category="credit"', $form);
        $this->assertContains('id="installments-row" hidden', $form, 'campo escondido até escolher crédito');
        foreach (['', '0', '25', 'abc'] as $bad) {
            $r = $this->submit('/orders/new', '/orders', ['installments' => $bad] + $order);
            $this->assertSame(422, $r->status(), "parcelas '$bad'");
            $this->assertContains('quantidade de parcelas', $r->body());
        }
        $this->assertContains('value="25"', $this->submit('/orders/new', '/orders', ['installments' => '25'] + $order)->body(), 'valor digitado preservado');
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM `$db`.orders"), 'nada gravado com parcelas inválidas');
        $r = $this->submit('/orders/new', '/orders', $order);
        $this->assertSame(303, $r->status());
        $id = (int) basename($this->location($r));
        $this->assertSame('173.50', $this->scalar("SELECT total FROM `$db`.orders WHERE id = $id"), '170,00 + 5% - 5,00 (desconto depois do acréscimo)');
        $this->assertSame(3, (int) $this->scalar("SELECT installments FROM `$db`.orders WHERE id = $id"), 'parcelas gravadas');
        $this->assertContains('3x de R$ 57,83', $this->get("/orders/$id")->body(), '173,50 / 3, arredondado ao centavo');
        $this->assertSame('90.00', $this->scalar("SELECT unit_price FROM `$db`.order_item WHERE order_id = $id"));
        $this->assertSame('60.00', $this->scalar("SELECT unit_cost FROM `$db`.order_item WHERE order_id = $id"));
        $this->assertSame(8, (int) $this->scalar("SELECT stock FROM `$db`.product"));
        $this->assertContains('R$ 173,50', $this->get("/orders/$id")->body());

        // The WhatsApp summary only exists for completed orders.
        $this->assertSame(409, $this->get("/orders/$id/banner.png")->status(), 'pedido aberto não gera imagem');
        $this->assertSame(409, $this->get("/orders/$id/whatsapp")->status());
        $this->assertFalse(str_contains($this->get("/orders/$id")->body(), 'Enviar por WhatsApp'), 'sem botão enquanto aberto');

        $r = $this->submit("/orders/$id", "/orders/$id/status", ['status' => 'completed']);
        $this->assertSame("/orders/$id?whatsapp=1", $this->location($r), 'concluir leva ao pedido pedindo o popup');
        $this->assertSame('completed', $this->scalar("SELECT status FROM `$db`.orders WHERE id = $id"));
        $detail = $this->get("/orders/$id", ['whatsapp' => '1'])->body();
        $this->assertContains('data-whatsapp-auto="' . $id . '"', $detail, 'popup aberto logo após concluir');
        $this->assertContains('Enviar por WhatsApp', $detail);
        $this->assertFalse(str_contains($this->get("/orders/$id")->body(), 'data-whatsapp-auto'), 'só abre quando pedido na URL');
        $this->assertContains('data-whatsapp-order="' . $id . '"', $this->get('/orders')->body(), 'botão na lista de pedidos');

        $this->pdo?->exec("INSERT INTO `$db`.main_seller (name, whatsapp) VALUES ('Patricia', '45920020405')");
        $share = json_decode($this->get("/orders/$id/whatsapp")->body(), true);
        $this->assertSame('Maria', $share['customerFirstName']);
        $this->assertSame('https://wa.me/551199', $share['chatUrl'], 'telefone do cliente, com DDI 55');
        $this->assertSame('Patricia', $share['sellerName']);

        $png = $this->get("/orders/$id/banner.png");
        $this->assertSame(200, $png->status());
        $this->assertSame('image/png', $png->headers()['Content-Type'] ?? '');
        $this->assertContains('no-store', $png->headers()['Cache-Control'] ?? '', 'imagem temporária: não fica em cache');
        $size = getimagesizefromstring($png->body());
        $this->assertSame([1080, 'image/png'], [$size[0] ?? 0, $size['mime'] ?? ''], 'PNG de 1080 px de largura');
        $this->assertTrue(($size[1] ?? 0) > ($size[0] ?? 0), 'formato retrato');
        $this->assertSame([], glob($this->tmp . '/**/*.png') ?: [], 'nada gravado em disco');

        // Behind the login like the rest of the orders area.
        $this->post('/logout', ['csrf_token' => $this->csrf('/')]);
        foreach (["/orders/$id/banner.png", "/orders/$id/whatsapp"] as $path) {
            $r = $this->get($path);
            $this->assertSame([303, '/login'], [$r->status(), $this->location($r)], $path);
        }
        $this->login();
        $this->submit("/orders/$id", "/orders/$id/status", ['status' => 'open']);
        $this->assertContains('Status inválido.', $this->get("/orders/$id")->body());
        $this->submit("/orders/$id", "/orders/$id/status", ['status' => 'canceled']);
        $this->assertSame(10, (int) $this->scalar("SELECT stock FROM `$db`.product"), 'cancelar devolve o estoque');
        $this->submit("/orders/$id", "/orders/$id/status", ['status' => 'canceled']);
        $this->assertContains('Esta mudança de status não é permitida.', $this->get("/orders/$id")->body());
        $this->assertSame(10, (int) $this->scalar("SELECT stock FROM `$db`.product"));

        // Lists, search and delete protections.
        $this->assertContains('#' . $id, $this->get('/orders', ['q' => '#' . $id, 'status' => 'canceled'])->body());
        $this->assertContains('Maria', $this->get('/customers', ['q' => '529'])->body());
        $this->submit('/categories', '/categories/1/delete', []);
        $this->assertContains('o registro está sendo usado por outros dados', $this->get('/categories')->body());
        $this->assertContains('Perfume', $this->get('/showcase')->body());
        $this->assertContains('Sem imagem', $this->get('/showcase')->body());
        $this->assertContains('Perfume', $this->get('/showcase', ['gender' => 'female'])->body(), 'filtro de gênero no showcase interno');
        $this->assertContains('Nenhum produto ativo para exibir.', $this->get('/showcase', ['gender' => 'male'])->body());
        $this->assertSame(404, $this->get('/images/products/PER-FER-2411261602/principal.jpg')->status());
    }

    public function testUsersAdministrationAndPermissions(): void
    {
        $this->login();
        $this->pdo?->exec("INSERT INTO `{$this->database}`.user_category (name) VALUES ('vendas')");
        $r = $this->submit('/users/new', '/users', ['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@x.com', 'user_category_id' => '2', 'password' => 'senha1234', 'confirm_password' => 'senha1234']);
        $this->assertSame('/users', $this->location($r));
        $this->assertSame(422, $this->submit('/users/new', '/users', ['name' => 'Ana', 'username' => 'ana', 'user_category_id' => '2', 'password' => 'senha1234', 'confirm_password' => 'senha1234'])->status());
        $this->assertSame(422, $this->submit('/users/new', '/users', ['name' => 'Bia', 'username' => 'bia', 'user_category_id' => '2', 'password' => 'senha1234', 'confirm_password' => 'outra'])->status());

        // A regular user cannot reach the administration area.
        $admin = $this->cookies;
        $this->cookies = [];
        $this->login('ana', 'senha1234');
        $this->assertSame(403, $this->get('/users')->status());
        $this->assertFalse(str_contains($this->get('/')->body(), 'href="/users"'), 'menu sem Usuários');
        $this->assertSame(200, $this->get('/profile')->status());

        // Changing the password revokes every session of the user, including this one.
        $token = $this->csrf('/profile');
        $r = $this->post('/profile/password', ['csrf_token' => $token, 'current_password' => 'senha1234', 'new_password' => 'novasenha99', 'confirm_password' => 'novasenha99']);
        $this->assertSame('/login', $this->location($r));
        $this->assertSame('/login', $this->location($this->get('/')));
        $this->assertSame(303, $this->login('ana', 'novasenha99')->status());

        // The admin deactivates her: the open session stops working and she cannot log in.
        $anaSession = $this->cookies;
        $this->cookies = $admin;
        $this->submit('/users', '/users/2/active', ['active' => '0']);
        $this->cookies = $anaSession;
        $this->assertSame('/login', $this->location($this->get('/')));
        $this->cookies = [];
        $this->assertSame(401, $this->login('ana', 'novasenha99')->status());
    }

    public function testLoginIsThrottledAfterRepeatedFailures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(401, $this->login('admin', 'errada')->status());
        }
        $this->assertSame(429, $this->login('admin', self::ADMIN_PASSWORD)->status());
    }
}
