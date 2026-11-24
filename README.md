# Patylook (PHP 8.2)

Versão em **PHP 8.2** do Patylook, sistema web de gestão de pedidos no estilo e-commerce. É uma
cópia fiel do projeto em Go (pasta pai): **mesmas regras de negócio, mesmas telas, mesmo banco de
dados e mesmas credenciais**. Foi criada porque o servidor de hospedagem não executa binários Go.

Cobre cadastro de produtos (preços e imagens), marcas, categorias, clientes, formas de pagamento e
pedidos, com usuários que gerenciam tudo por login. Não há pagamento online nem logística.

## Sumário

- [Requisitos](#requisitos)
- [Banco de dados (o mesmo do projeto Go)](#banco-de-dados-o-mesmo-do-projeto-go)
- [Executando localmente](#executando-localmente)
- [Configuração](#configuração)
- [Arquitetura](#arquitetura)
- [Regras de negócio (idênticas às do Go)](#regras-de-negócio-idênticas-às-do-go)
- [Imagens dos produtos](#imagens-dos-produtos)
- [Testes](#testes)
- [Publicação em produção (HostGator)](#publicação-em-produção-hostgator)
- [Segurança](#segurança)
- [Diferenças em relação à versão Go](#diferenças-em-relação-à-versão-go)

## Requisitos

- **PHP 8.2** ou superior, com as extensões `pdo_mysql`, `mbstring`, `gd` (com JPEG e PNG),
  `ctype` e `json`. O sistema se recusa a subir, com mensagem clara, se algo faltar.
- MySQL 5.7+ ou MariaDB, com o banco `patylook` já existente.
- Apache com `mod_rewrite` (padrão em hospedagem compartilhada). Sem Composer, sem Node/npm: o
  carregamento de classes (PSR-4) é feito por um autoloader próprio em `bootstrap.php`.

## Banco de dados (o mesmo do projeto Go)

> **O banco já existe e contém dados.** A aplicação **nunca** cria nem altera tabelas, e **não se
> deve executar o `schema.sql` inteiro** (tem `DROP TABLE`). A cópia do arquivo nesta pasta serve
> só como documentação da estrutura e para os testes automatizados (que usam um banco separado).

Os ajustes de estrutura são os mesmos do projeto Go e **já devem estar aplicados** se você usa a
versão Go: coluna `user.password` com `VARCHAR(255)`, tabelas `brand` e `product_images` e a
coluna `product.brand_id` (scripts em `migrations/`).

Para conferir, sem alterar nada:

```bash
php bin/patylook verify-schema
```

Senhas gravadas pelo Go (bcrypt) funcionam sem conversão. Para gerar um hash (por exemplo, para
redefinir uma senha via SQL):

```bash
php bin/patylook hash-password
```

## Executando localmente

```bash
cp .env.example .env        # já traz as credenciais locais (localhost / root / sem senha / patylook)
php bin/patylook serve      # http://localhost:8080
```

(equivale a `php -S localhost:8080 -t public public/router.php`). Sem login, você é redirecionado
para `/login`. `GET /healthz` responde `ok` sem tocar no banco.

Primeiro acesso: se a tabela `user` estiver **vazia**, defina `BOOTSTRAP_ADMIN_USERNAME` e
`BOOTSTRAP_ADMIN_PASSWORD` (mínimo 8 caracteres) e abra qualquer página: o administrador é criado
(junto com a categoria `ADMIN_CATEGORY`, se faltar). Remova essas variáveis depois.

## Configuração

Tudo vem de variáveis de ambiente; o arquivo `.env` na raiz do projeto é opcional e **variáveis
reais sempre prevalecem** sobre ele. Comentários no `.env` devem ficar em linhas próprias.

| Variável | Padrão | Descrição |
|---|---|---|
| `APP_ENV` | `development` | `development` ou `production` (em produção: cookie `Secure`, HSTS e logs em JSON) |
| `APP_TIMEZONE` | `America/Sao_Paulo` | Fuso de datas e horários; também é o fuso da sessão MySQL |
| `SESSION_TTL` | `8h` | Inatividade até a sessão expirar (`30m`, `8h`, `1h30m`...) |
| `COOKIE_SECURE` | `true` em produção | Marca o cookie como `Secure` (só HTTPS) |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | Servidor MySQL |
| `DB_USER` | (obrigatória) | Usuário |
| `DB_PASSWORD` | vazio | Senha |
| `DB_NAME` | `patylook` | Nome do banco |
| `DB_TLS` | vazio | `true`, `skip-verify` ou `preferred`, se o servidor exigir TLS |
| `ADMIN_CATEGORY` | `admin` | Categoria de usuário (`user_category`) com permissão administrativa |
| `IMAGES_DIR` | `images/products` | Pasta com uma subpasta por produto (relativa à raiz do projeto) |
| `STORAGE_DIR` | `storage` | Sessões, controle de login, logs e caches (precisa de escrita) |
| `BOOTSTRAP_ADMIN_USERNAME` / `_NAME` / `_PASSWORD` | vazio | Criação do primeiro administrador |

> ⚠️ **As credenciais do `.env` são apenas locais.** Ao subir para produção, altere `DB_HOST`,
> `DB_USER`, `DB_PASSWORD` e `DB_NAME` e defina `APP_ENV=production`. O `.env` está no
> `.gitignore`; em produção prefira variáveis de ambiente do painel da hospedagem.

## Arquitetura

**MVC com Front Controller e reescrita de URL.** Todo pedido que não é um arquivo estático
(CSS/JS) é reescrito pelo `.htaccess` para `public/index.php`, que monta o contêiner de
dependências e entrega a requisição ao roteador.

```
public/                  raiz web (document root)
├── index.php            FRONT CONTROLLER: único ponto de entrada
├── .htaccess            URL rewriting (tudo que não é arquivo vai para index.php)
├── router.php           script de roteamento do servidor embutido (só desenvolvimento)
└── static/              CSS e JavaScript
app/
├── Core/                mini-framework: Application (kernel), Router, Route, Pipeline, Container,
│                        Request, Response, View, Config, Env, Formatter
├── Controllers/         C: recebem a Request, chamam serviços e devolvem uma Response
├── Models/              M: entidades e regras de domínio (Money, Order, Pricebook, User...)
├── Views/               V: layout, páginas e partials em PHP (escapam tudo com e())
├── Services/            casos de uso (autenticação, pedidos, catálogo, imagens...)
├── Repositories/Mysql/  persistência (PDO, consultas parametrizadas)
├── Contracts/           interfaces: repositórios, serviços, hash, storage, relógio, log...
├── Infrastructure/      adaptadores: sessões e limitador em arquivo, imagens (GD), bcrypt, log
├── Middleware/          RequireAuth (+CSRF), RequireAdmin, SameOrigin, LimitBody, EnsureReady
├── Http/                Responder, Form, Pager, UploadReader, ExceptionHandler, SecurityHeaders
├── Dto/ Exceptions/     objetos de transferência e exceções de domínio
config/
├── routes.php           o mapa de URLs (método + padrão → Controller::método + middlewares)
└── container.php        raiz de composição: qual implementação atende cada interface
bootstrap.php            autoloader PSR-4, configuração, container
bin/patylook             CLI: hash-password, verify-schema, bootstrap, serve
storage/                 sessões, controle de tentativas de login, logs (fora da web)
images/products/         imagens dos produtos (fora da web; servidas só a usuários logados)
tests/                   testes automatizados (sem PHPUnit)
```

Fluxo de uma requisição: `index.php` → `Application::handle()` → middlewares globais
(`SameOrigin`, `EnsureReady`) → rota casada em `config/routes.php` → middlewares do grupo
(`RequireAuth`, `RequireAdmin`...) → método do controller → serviço → repositório → resposta
(página, redirecionamento com mensagem flash ou página de erro).

### SOLID na prática

- **S (responsabilidade única):** cada classe faz uma coisa: `Router` casa URLs, `Pipeline` encadeia
  middlewares, `Responder` monta respostas, `Form` lê campos, `ProductImageStore` cuida das regras
  de arquivos de imagem, `ConflictTranslator` traduz violações de unicidade em erros de campo.
  O catálogo é dividido em `ProductService`, `ProductCategoryService`, `PricebookService`,
  `ProductImageService` e `ShowcaseService`.
- **O (aberto/fechado):** novas telas entram com uma nova linha em `config/routes.php` e um
  controller; novas políticas entram como middlewares, sem alterar o kernel. Os renderizadores e
  filtros de resposta são plugáveis (`ResponseFilterInterface`, `ExceptionHandlerInterface`).
- **L (substituição de Liskov):** qualquer implementação de uma interface pode ser trocada sem o
  chamador perceber. Os testes fazem isso com repositórios, relógio, storage e hash falsos.
- **I (segregação de interfaces):** interfaces pequenas e específicas (`ClockInterface`,
  `PasswordHasherInterface`, `AdminBootstrapInterface`, `ImageStorageInterface`...) em vez de uma
  interface única com tudo; cada controller depende só dos serviços de que precisa.
- **D (inversão de dependência):** controllers dependem de interfaces de serviço, serviços de
  interfaces de repositório e infraestrutura. Quem decide as implementações é só
  `config/container.php`; o `Container` resolve o resto por *autowiring* dos tipos do construtor.

## Regras de negócio (idênticas às do Go)

- **Preço e custo são gravados no item do pedido** na hora da venda; mudar o preço depois não
  altera pedidos antigos. Vale a vigência mais recente que cobre a data do pedido.
- **Estoque** é reservado ao criar o pedido (`UPDATE ... WHERE stock >= ?`, itens em ordem fixa de
  produto) e devolvido no cancelamento, tudo em transação. Produto sem preço vigente, inativo ou
  sem estoque não pode ser vendido.
- **Status do pedido:** `open` → `completed` ou `canceled`; `completed` → `canceled`. Cancelado é final.
- **Valores monetários** em centavos inteiros, nunca em ponto flutuante (`Money`/`Percent`).
- **Total** = subtotal (itens − descontos de item) − desconto do pedido + acréscimo da forma de
  pagamento, que incide sobre o valor já com desconto (arredondamento meio para longe do zero).
- **Preços:** custo + embalagem + logística + outros; o markup é calculado, `(preço final − custo
  total) ÷ custo total`, e não é editável. Preço final menor que o custo é recusado.
- **SKU** gerado no cadastro (3 letras da categoria + 3 da marca + `ddmmaaHHMM`), nunca digitado e
  nunca editado. Dois cadastros da mesma categoria/marca no mesmo minuto colidem (o sistema avisa).
- **CPF** validado; **usuários**: 3 a 16 caracteres (letras, números, `.`, `-`, `_`), senha de 8 a
  72 caracteres, bcrypt custo 12; só administradores gerenciam usuários; ninguém desativa a si mesmo;
  desativar, redefinir ou trocar a senha encerra as sessões do usuário.
- **Login:** 5 falhas em 15 minutos (por IP + usuário) bloqueiam novas tentativas; o tempo de
  resposta não revela se o usuário existe.

## Imagens dos produtos

Uma pasta por produto, nomeada pelo SKU: `images/products/<SKU>/principal.jpg` (obrigatória) e
`imagem-01.jpg`, `imagem-02.jpg`... (até 8 no carrossel). Cada arquivo é registrado em
`product_images`.

- Aceita **JPEG ou PNG**, até **6 MB**, mínimo de 200 px no menor lado, máximo de 25 megapixels;
- valida pelo **conteúdo** (não pelo nome nem pelo tipo informado) e recusa arquivos truncados;
- corrige a orientação EXIF, achata transparência sobre branco, reduz para 1600 px no maior lado e
  grava **sempre como JPEG**, sem metadados;
- se a gravação em disco ou no banco falhar, desfaz as duas coisas;
- a principal só pode ser substituída, nunca removida; o SKU não muda (ele nomeia a pasta);
- são servidas em `/images/products/<pasta>/<arquivo>` **somente a usuários logados** (a pasta fica
  fora da raiz web) e só aceitam nomes que a aplicação gerou (sem *path traversal*).

A pasta precisa de **permissão de escrita** e deve entrar no **backup** junto com o banco.

## Testes

```bash
php tests/run.php            # todos
php tests/run.php Domain     # só os que combinam com o filtro
```

Sem PHPUnit: um executor próprio em `tests/run.php`. A suíte porta os testes do projeto Go
(dinheiro, CPF, preço/markup, pedido, SKU, imagens com EXIF, storage, autenticação, throttling,
serviços com repositórios falsos, rollback) e acrescenta um teste **ponta a ponta**
(`HttpFlowTest`) que dirige a aplicação real (rotas, middlewares, controllers, serviços, MySQL)
em processo: login, CSRF, origem cruzada, cadastros, preço, pedido, estoque, cancelamento,
permissões, usuários e bloqueio de login.

O teste ponta a ponta cria e apaga o seu **próprio banco** (`patylook_php_test`, a partir do
`schema.sql`) usando as credenciais do `.env`, e se recusa a rodar com um nome que não termine em
`_test`. Ele **nunca** toca em `DB_NAME`. Sem MySQL acessível, é ignorado.

## Publicação em produção (HostGator)

1. **Versão do PHP:** no cPanel (*MultiPHP Manager*), selecione PHP 8.2 para o domínio e confirme
   as extensões `pdo_mysql`, `mbstring` e `gd`.
2. **Envie** o conteúdo desta pasta (sem `.env` e sem `storage/*`). Idealmente, aponte o *document
   root* do domínio para a pasta `public/`. Se a conta não permitir, deixe a raiz do projeto como
   raiz web: o `.htaccess` da raiz reescreve tudo para `public/` e bloqueia o acesso a `app/`,
   `config/`, `storage/`, `.env`, `schema.sql` etc.
3. **Credenciais:** altere `DB_HOST`, `DB_USER`, `DB_PASSWORD` e `DB_NAME` para as do MySQL do
   servidor (em hospedagem compartilhada costumam ter o prefixo da conta, ex.: `conta_patylook`) e
   defina `APP_ENV=production`. Prefira variáveis de ambiente do painel a um arquivo `.env`.
4. **Permissões de escrita** em `storage/` e em `images/products/` (ou nos caminhos de
   `STORAGE_DIR`/`IMAGES_DIR`).
5. **Banco:** aplique no banco de produção os ajustes de `migrations/` (sem rodar o `schema.sql`
   completo) e garanta um administrador (veja [Primeiro acesso](#executando-localmente)).
   Confira com `php bin/patylook verify-schema` (por SSH) ou abrindo o site: erros de estrutura
   aparecem em `storage/logs/app.log`.
6. **HTTPS** é necessário: em produção o cookie de sessão só é enviado por conexão segura.
7. **Limites de upload:** `public/.htaccess` (mod_php) e `public/.user.ini` (CGI/FastCGI) pedem
   `upload_max_filesize=8M`, `post_max_size=64M`. Se a hospedagem impuser limites menores, o envio de
   várias imagens grandes será recusado com a mensagem "Arquivos muito grandes". Alterações no
   `.user.ini` podem levar alguns minutos para valer.
8. **Backup:** banco **e** `images/products/` precisam ser copiados juntos.

## Segurança

- Senhas com **bcrypt** (custo 12). Sessão no servidor: o cookie guarda um token aleatório
  (`HttpOnly`, `SameSite=Lax`, `Secure` em produção) e o servidor guarda só o **hash** dele.
  Expiração por inatividade, com renovação deslizante.
- **CSRF:** token por sessão em todo envio autenticado, mais bloqueio de requisições de outra
  origem (`Sec-Fetch-Site`/`Origin`).
- Cabeçalhos: CSP restritiva (sem scripts inline), `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy` e HSTS em produção.
- SQL sempre parametrizado (*prepared statements* nativos); saída HTML escapada em todos os pontos.
- Uploads validados por conteúdo e reencodificados; nomes de arquivo gerados pelo servidor.
- Diretórios sensíveis fora da raiz web (ou bloqueados pelo `.htaccess` da raiz).

## Diferenças em relação à versão Go

As regras de negócio são as mesmas; mudam apenas detalhes impostos pelo modelo de execução do PHP
(um processo novo por requisição):

| Assunto | Go | PHP |
|---|---|---|
| Sessões | em memória (perdem-se ao reiniciar) | arquivos em `storage/sessions` (sobrevivem a reinícios; mesmo modelo de token + hash) |
| Limite de tentativas de login | em memória | arquivos em `storage/throttle` |
| Verificação do schema e criação do 1º admin | ao iniciar o processo | na primeira requisição e a cada 10 minutos (com trava); também via `php bin/patylook verify-schema` / `bootstrap` |
| Limpeza de sessões expiradas | a cada 30 min | em ~1% das requisições, após enviar a resposta |
| Modo `fcgi`, `APP_MODE`, `APP_ADDR`, `PORT` | existem | não se aplicam (quem serve é o Apache/PHP) |
| Log | saída padrão | `storage/logs/app.log`; as requisições (acesso) só em desenvolvimento |
| Campos de arquivos repetidos | `images` | `images[]` (convenção do PHP) |
| Imagens | `Last-Modified` + `Range` | `Last-Modified`/`If-Modified-Since`; sem `Range` |
| E-mail | `net/mail` | `FILTER_VALIDATE_EMAIL` (pode divergir em endereços muito exóticos) |
