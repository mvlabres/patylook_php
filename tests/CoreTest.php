<?php

declare(strict_types=1);

namespace Patylook\Tests;

use Patylook\Core\Config;
use Patylook\Core\Container;
use Patylook\Core\Formatter;
use Patylook\Core\Request;
use Patylook\Core\Router;
use Patylook\Dto\Page;
use Patylook\Exceptions\ConfigurationException;
use Patylook\Exceptions\ConflictException;
use Patylook\Exceptions\InUseException;
use Patylook\Exceptions\NotFoundException;
use Patylook\Http\Form;
use Patylook\Http\Pager;
use Patylook\Infrastructure\Database\Database;
use Patylook\Models\Money;
use Patylook\Support\Fixed2;
use PDOException;

/** Framework pieces: configuration, routing, container, request helpers and database error mapping. */
final class CoreTest extends TestCase
{
    /** @var list<string> */
    private array $touched = [];

    protected function tearDown(): void
    {
        foreach ($this->touched as $key) {
            putenv($key);
        }
        $this->touched = [];
    }

    private function env(string $key, string $value): void
    {
        putenv("$key=$value");
        $this->touched[] = $key;
    }

    public function testDurationParsing(): void
    {
        $cases = [['8h', 28800], ['30m', 1800], ['90s', 90], ['1h30m', 5400], ['1.5h', 5400], ['0', 0], ['', null], ['8', null], ['h', null], ['8x', null], ['-1h', null]];
        foreach ($cases as [$text, $want]) {
            $this->assertSame($want, Config::parseDuration($text), "'$text'");
        }
    }

    public function testConfigDefaultsAndValidation(): void
    {
        foreach (['DB_USER' => 'tester', 'DB_NAME' => '', 'DB_HOST' => '', 'APP_ENV' => '', 'SESSION_TTL' => '', 'COOKIE_SECURE' => '', 'APP_TIMEZONE' => '', 'DB_PORT' => '', 'IMAGES_DIR' => '', 'STORAGE_DIR' => ''] as $k => $v) {
            $this->env($k, $v);
        }
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pl_cfg_missing';
        $c = Config::load($base, $base . '/nao-existe.env');
        $this->assertSame('development', $c->env);
        $this->assertSame('127.0.0.1', $c->dbHost);
        $this->assertSame(3306, $c->dbPort);
        $this->assertSame('patylook', $c->dbName);
        $this->assertSame(28800, $c->sessionTtl);
        $this->assertFalse($c->cookieSecure);
        $this->assertSame('America/Sao_Paulo', $c->timezone->getName());
        $this->assertSame('admin', $c->adminCategory);
        $this->assertSame($base . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'products', $c->imagesDir, 'relativo à raiz do projeto');

        $this->env('APP_ENV', 'PRODUCTION');
        $this->assertTrue(Config::load($base, $base . '/x.env')->cookieSecure, 'Secure por padrão em produção');
        $this->env('COOKIE_SECURE', 'false');
        $this->assertFalse(Config::load($base, $base . '/x.env')->cookieSecure);

        $this->env('DB_PORT', 'abc');
        $this->env('SESSION_TTL', 'forever');
        $this->env('APP_TIMEZONE', 'Mars/Base');
        $this->env('DB_USER', '');
        $e = $this->assertThrows(ConfigurationException::class, fn () => Config::load($base, $base . '/x.env'));
        foreach (['DB_PORT', 'SESSION_TTL', 'APP_TIMEZONE', 'DB_USER'] as $name) {
            $this->assertContains($name, $e->getMessage());
        }
    }

    public function testEnvFileDoesNotOverrideRealVariables(): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pl_env_' . bin2hex(random_bytes(3)) . '.env';
        file_put_contents($file, "# comment\nPL_TEST_A=from-file\nPL_TEST_B=\"quoted value\"\nPL_TEST_C='single'\n\nnot a pair\n PL_TEST_D = spaced \n");
        $this->env('PL_TEST_A', 'from-env');
        foreach (['PL_TEST_B', 'PL_TEST_C', 'PL_TEST_D'] as $k) {
            $this->touched[] = $k;
        }
        \Patylook\Core\Env::load($file);
        unlink($file);
        $this->assertSame('from-env', \Patylook\Core\Env::get('PL_TEST_A'));
        $this->assertSame('quoted value', \Patylook\Core\Env::get('PL_TEST_B'));
        $this->assertSame('single', \Patylook\Core\Env::get('PL_TEST_C'));
        $this->assertSame('spaced', \Patylook\Core\Env::get('PL_TEST_D'));
        $this->assertSame('dflt', \Patylook\Core\Env::get('PL_TEST_MISSING', 'dflt'));
    }

    public function testRouterMatching(): void
    {
        $router = new Router();
        $router->get('/', ['A', 'home']);
        $router->get('/products', ['A', 'list']);
        $router->get('/products/new', ['A', 'new']);
        $router->post('/products/{id}', ['A', 'update']);
        $router->get('/products/{id}/edit', ['A', 'edit']);
        $router->get('/images/products/{folder}/{file}', ['A', 'image']);
        $router->group(['MW1', 'MW2'], static function (Router $r): void {
            $r->get('/secret', ['A', 'secret']);
        });
        $router->get('/open', ['A', 'open']);

        $this->assertSame('home', $router->match('GET', '/')[0]->handler[1]);
        $this->assertSame('new', $router->match('GET', '/products/new')[0]->handler[1]);
        $this->assertSame(['id' => '42'], $router->match('GET', '/products/42/edit')[1]);
        $this->assertSame('update', $router->match('POST', '/products/7')[0]->handler[1]);
        $this->assertSame(['folder' => 'CAM 1', 'file' => 'principal.jpg'], $router->match('GET', '/images/products/CAM%201/principal.jpg')[1]);
        $this->assertSame('list', $router->match('HEAD', '/products')[0]->handler[1], 'HEAD usa a rota GET');
        foreach ([['GET', '/products/'], ['GET', '/nada'], ['POST', '/products'], ['DELETE', '/products/1'], ['GET', '/products//edit'], ['GET', '/products/1/edit/x']] as [$m, $p]) {
            $this->assertSame(null, $router->match($m, $p), "$m $p");
        }
        $this->assertSame(['MW1', 'MW2'], $router->match('GET', '/secret')[0]->middleware);
        $this->assertSame([], $router->match('GET', '/open')[0]->middleware, 'o grupo não vaza');
    }

    public function testContainerAutowiringAndBindings(): void
    {
        $c = new Container();
        $c->singleton(ContainerDependencyInterface::class, ContainerDependency::class);
        $first = $c->get(ContainerConsumer::class);
        $this->assertSame('dep', $first->dependency->name());
        $this->assertSame($c->get(ContainerDependencyInterface::class), $c->get(ContainerDependencyInterface::class), 'singleton');
        $c->bind(ContainerConsumer::class);
        $this->assertTrue($c->get(ContainerConsumer::class) !== $c->get(ContainerConsumer::class), 'bind cria novo');
        $this->assertThrows(\InvalidArgumentException::class, fn () => $c->get('Nope\Missing'));
    }

    public function testRequestHelpers(): void
    {
        $r = new Request('POST', '/x', ['page' => '2', 'arr' => ['a']], ['name' => ' Ana ', 'ids' => ['1', '2'], 'bad' => ['x']], [], ['c' => 'v'], [
            'HTTP_SEC_FETCH_SITE' => 'same-origin', 'CONTENT_TYPE' => 'multipart/form-data; boundary=x', 'HTTP_HOST' => 'localhost:8080',
        ]);
        $this->assertSame('2', $r->query('page'));
        $this->assertSame('', $r->query('arr'), 'valores não-texto viram vazio');
        $this->assertSame(' Ana ', $r->post('name'));
        $this->assertSame(['1', '2'], $r->postList('ids'));
        $this->assertSame('', $r->post('bad'));
        $this->assertSame('same-origin', $r->header('Sec-Fetch-Site'));
        $this->assertSame('multipart/form-data; boundary=x', $r->header('Content-Type'));
        $this->assertSame('localhost:8080', $r->host());
        $this->assertSame('v', $r->cookie('c'));
        $this->assertFalse($r->isSafe());
        foreach (['5' => 5, '0' => null, '-3' => null, 'abc' => null, '1.5' => null, '' => null, '007' => 7] as $raw => $want) {
            $r->setRouteParams(['id' => (string) $raw]);
            $this->assertSame($want, $r->routeId(), "routeId('$raw')");
        }
    }

    public function testFormParsing(): void
    {
        $post = ['name' => '  Ana  ', 'qty' => ' 12 ', 'bad_int' => '1x', 'money' => '1.234,56', 'bad_money' => '12,345', 'pct' => '5,5', 'd' => '2026-02-28', 'bad_d' => '2026-02-30', 'fmt_d' => '28/02/2026'];
        $form = new Form(new Request('POST', '/', [], $post));
        $zone = new \DateTimeZone('America/Sao_Paulo');
        $this->assertSame('Ana', $form->str('name'));
        $this->assertSame(12, $form->integer('qty'));
        $this->assertSame(0, $form->integer('bad_int'));
        $this->assertSame(0, $form->intField('bad_int', 'Quantidade'));
        $this->assertSame(123456, $form->money('money', 'Valor', true)->cents);
        $this->assertSame(0, $form->money('missing', 'Opcional', false)->cents);
        $this->assertSame(550, $form->percent('pct', 'Pct', true)->hundredths);
        $this->assertSame('2026-02-28', $form->date('d', 'Data', $zone)?->format('Y-m-d'));
        $this->assertSame(null, $form->date('missing', 'Data', $zone));
        $this->assertSame(null, $form->date('bad_d', 'Data', $zone));
        $this->assertSame(null, $form->date('fmt_d', 'Data', $zone));
        $form->money('bad_money', 'Valor', true);
        $form->money('missing', 'Obrigatório', true);
        $this->assertSame(
            ['bad_int' => 'Quantidade inválido', 'bad_d' => 'Data inválido', 'fmt_d' => 'Data inválido', 'bad_money' => 'Valor inválido', 'missing' => 'Obrigatório inválido'],
            $form->errors->fields(),
        );
    }

    public function testPagerLinksKeepOtherQueryParameters(): void
    {
        $request = new Request('GET', '/products', ['q' => 'a b', 'page' => '2', 'status' => 'open']);
        $pager = Pager::for($request, new Page([], 100, 2, 20));
        $this->assertSame(5, $pager->pages);
        $this->assertSame('/products?page=1&q=a+b&status=open', $pager->prevUrl);
        $this->assertSame('/products?page=3&q=a+b&status=open', $pager->nextUrl);
        $first = Pager::for($request, new Page([], 100, 1, 20));
        $this->assertSame('', $first->prevUrl);
        $this->assertSame(1, Pager::for($request, new Page([], 0, 1, 20))->pages);
        $this->assertSame(3, (new Page([], 41, 1, 20))->pages());
    }

    public function testDatabaseErrorTranslation(): void
    {
        $dup = static fn (string $msg): PDOException => self::pdo(1062, $msg);
        $cases = [
            "Duplicate entry 'x' for key 'user.username_UNIQUE'" => 'username',
            "Duplicate entry 'x' for key 'sku_UNIQUE'" => 'sku',
            "Duplicate entry 'x' for key 'product_images.path_UNIQUE'" => 'path',
            "Duplicate entry '1-2' for key 'order_item.PRIMARY'" => '',
            "Duplicate entry 'x' for key 'weird'" => 'weird',
            'no key information' => '',
        ];
        foreach ($cases as $message => $field) {
            $e = Database::translate($dup($message));
            $this->assertTrue($e instanceof ConflictException, $message);
            $this->assertSame($field, $e->field, $message);
        }
        $this->assertTrue(Database::translate(self::pdo(1451, 'fk'))instanceof InUseException);
        $this->assertTrue(Database::translate(self::pdo(1452, 'fk'))instanceof NotFoundException);
        $other = self::pdo(1064, 'syntax');
        $this->assertSame($other, Database::translate($other));
    }

    private static function pdo(int $code, string $message): PDOException
    {
        $e = new PDOException($message);
        $e->errorInfo = ['23000', $code, $message];
        return $e;
    }

    public function testFormattingAndFixedPoint(): void
    {
        $f = new Formatter(new \DateTimeZone('America/Sao_Paulo'));
        $this->assertSame('123.456.789-01', $f->cpf('12345678901'));
        $this->assertSame('123', $f->cpf('123'));
        $this->assertSame('—', $f->moneyOrDash(null));
        $this->assertSame('R$ 1,50', $f->moneyOrDash(new Money(150)));
        $this->assertSame('—', $f->date(null));
        $this->assertSame('24/11/2026', $f->date(new \DateTimeImmutable('2026-11-24')));
        $this->assertSame('24/11/2026 16:02', $f->datetime(new \DateTimeImmutable('2026-11-24 16:02:09', new \DateTimeZone('America/Sao_Paulo'))));
        $this->assertSame('2026-11-24', $f->dateInput(new \DateTimeImmutable('2026-11-24')));
        $this->assertSame('', $f->dateInput(null));
        $this->assertSame(3, Fixed2::roundDiv(25, 10));
        $this->assertSame(-3, Fixed2::roundDiv(-25, 10));
        $this->assertSame(2, Fixed2::roundDiv(24, 10));
        $this->assertSame('1.234.567', Fixed2::groupThousands(1234567));
        $this->assertSame(null, Money::parse('99999999999999999999'), 'estouro é recusado');
    }
}

interface ContainerDependencyInterface
{
    public function name(): string;
}

final class ContainerDependency implements ContainerDependencyInterface
{
    public function name(): string
    {
        return 'dep';
    }
}

final class ContainerConsumer
{
    public function __construct(public readonly ContainerDependencyInterface $dependency)
    {
    }
}
