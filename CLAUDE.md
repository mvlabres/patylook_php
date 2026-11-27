# Patylook/VL Store - Contexto para Claude

## Visão Geral do Projeto

**VL Store** (antes Patylook) é um sistema web de **e-commerce e gestão de pedidos** em PHP 8.2. Versão fidedigna do projeto Go, mantendo regras de negócio, estrutura de banco e interface idênticas.

**Propósito**: Loja online da **Valentina Labres** (roupas). Permite:
- Catálogo público de produtos (mostruário)
- Painel admin para gestão
- Integração com WhatsApp
- Múltiplos métodos de pagamento (VISA, Mastercard, Elo, Hipercard, American Express)

---

## Stack Técnico

| Componente | Escolha | Notas |
|-----------|---------|-------|
| **Linguagem** | PHP 8.2+ | Sem frameworks (MVC puro) |
| **Banco** | MySQL 5.7+ / MariaDB | Banco `patylook` existente |
| **Frontend** | Vanilla JS + CSS | Sem React/Vue/npm |
| **Build** | Sem tooling | PSR-4 autoloader em `bootstrap.php` |
| **Hospedagem** | HostGator (Apache) | `mod_rewrite` ativado |

---

## Arquitetura - Padrão MVC

```
Request → Router (config/routes.php)
        ↓
    Controllers/ (Patylook\Controllers\*)
        ↓
    Services/ (Patylook\Services\*)  ← Lógica de negócio
        ↓
    Repositories/ (Patylook\Repositories\*)  ← Acesso a dados
        ↓
    Models/ (Patylook\Models\*)  ← Entidades
        ↓
    Views/ (app/Views/pages/)  ← Templates PHP
        ↓
    Response
```

### Padrões Implementados

| Padrão | Implementação | Localização |
|--------|---------------|------------|
| **Service** | Implementam `*ServiceInterface` | `app/Services/` + `app/Contracts/` |
| **Repository** | Implementam `*RepositoryInterface` | `app/Repositories/` + `app/Contracts/` |
| **DTO** | Transferência de dados entre camadas | `app/Dto/` |
| **Model** | Entidades persistidas | `app/Models/` |

---

## Estrutura de Diretórios

```
patylook_php/
├── CLAUDE.md                    ⭐ Este arquivo
├── ARCHITECTURE.md              ⭐ Decisões técnicas
├── PROJECT_STATE.md             ⭐ Estado atual
├── README.md                     → Documentação geral
├── app/
│   ├── Controllers/             → Mapeiam requisições para Services
│   ├── Services/                → Lógica de negócio (interface + impl)
│   ├── Repositories/            → Acesso a dados (interface + impl)
│   │   └── Mysql/               → Implementações MySQL
│   ├── Contracts/               → Interfaces (services + repos)
│   ├── Models/                  → Entidades (Product, Order, User, etc)
│   ├── Dto/                     → Objetos de transferência (ShowcaseItem, Page, etc)
│   ├── Exceptions/              → Exceções customizadas
│   ├── Support/                 → Utilitários (Str, Fixed2, etc)
│   ├── Infrastructure/          → Implementações de infra (Clock, Logger, etc)
│   ├── Core/                    → Núcleo (Request, Response, Responder, View, etc)
│   └── Views/pages/             → Templates PHP (layout.php + pages específicas)
├── config/
│   └── routes.php               → Mapeamento de rotas
├── public/
│   ├── index.php                → Entrada da app
│   ├── router.php               → Despachador de rotas
│   └── static/
│       ├── css/app.css          → Único arquivo CSS (tema ouro/preto)
│       ├── js/                  → JavaScript (showcase.js, etc)
│       └── img/                 → Imagens (favicon, logo, card-*.png)
├── bin/
│   └── patylook                 → CLI para comandos úteis
└── migrations/                  → Scripts SQL de migração
```

---

## Padrões de Código

### Estilo Geral
- **Tipos explícitos** em propriedades e parâmetros
- **Funções curtas** (use `static fn` para callbacks)
- **Snake_case** no banco de dados
- **camelCase** no código PHP
- **Evite comentários óbvios** (código bem nomeado é auto-documentado)

### Exemplo: Service com Repository

```php
class ShowcaseService implements ShowcaseServiceInterface {
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductImageRepositoryInterface $images,
    ) {}

    public function list(int $page, string $search, ?Gender $gender = null): Page {
        // Busca produtos ativos
        $result = $this->products->list(
            $params->withOnlyActive()->withGender($gender),
            $this->clock->now()->setTime(0, 0)
        );
        
        // Monta DTOs
        $items = array_map(
            fn (Product $p): ShowcaseItem => new ShowcaseItem($p, $images[$p->id] ?? []),
            $result->items,
        );
        
        // Ordena alfabeticamente, esgotados por último
        usort($items, static function (ShowcaseItem $a, ShowcaseItem $b): int {
            $aInStock = $a->product->stock > 0;
            $bInStock = $b->product->stock > 0;
            if ($aInStock !== $bInStock) return $aInStock ? -1 : 1;
            return strcasecmp($a->product->name, $b->product->name);
        });
        
        return Pagination::page($items, $result->total, $page);
    }
}
```

---

## Endpoints Principais (Rotas)

### Públicos (sem login)
| Rota | Método | Descrição |
|------|--------|-----------|
| `/mostruario` | GET | Vitrine pública de produtos (com filtros) |
| `/mostruario/imagens/{folder}/{file}` | GET | Imagens de produtos (públicas) |
| `/` | GET | Redireciona para `/mostruario` |

### Protegidos (requerem login)
| Rota | Método | Descrição |
|------|--------|-----------|
| `/showcase` | GET | Vitrine para admin (gerenciamento) |
| `/products` | GET/POST | Lista e cria produtos |
| `/products/{id}/edit` | GET | Edita produto |
| `/orders` | GET/POST | Lista e cria pedidos |
| `/categories` | GET/POST | Gestão de categorias |
| `/brands` | GET/POST | Gestão de marcas |
| `/customers` | GET/POST | Gestão de clientes |
| `/payment-types` | GET/POST | Gestão de formas de pagamento |
| `/users` | GET/POST | Gestão de usuários (admin only) |
| `/profile` | GET/POST | Perfil do usuário logado |

---

## Identidade Visual

| Elemento | Cor | Código |
|----------|-----|--------|
| **Primária** | Dourado Champanhe | `#d4af6a` |
| **Primária Light** | Ouro Claro | `#f0d9a8` |
| **Primária Dark** | Ouro Escuro | `#a47a3a` |
| **Fundo** | Preto Quente | `#0b0a09` |
| **Superfície** | Cinza Muito Escuro | `#15120f` |
| **Texto** | Bege Claro | `#f1e9dc` |
| **Muted** | Bege Médio | `#a39682` |
| **Danger** | Vermelho | `#ef7d73` |

**Tipografia**: Montserrat (font-weight 100-900)

---

## Modelos de Dados Principais

### Product
```php
{
    id: int,
    name: string,
    description: string,
    sku: string,
    categoryId: int,
    brandId: ?int,
    gender: Gender (M/F/U),
    stock: int,
    currentPrice: Money,
    isActive: bool,
    createdAt: DateTimeImmutable,
}
```

### Order
```php
{
    id: int,
    customerId: int,
    items: OrderItem[],
    paymentType: PaymentType,
    status: OrderStatus (PENDING/APPROVED/SHIPPED/DELIVERED/CANCELLED),
    total: Money,
    createdAt: DateTimeImmutable,
}
```

### User
```php
{
    id: int,
    name: string,
    email: string,
    password: string (bcrypt),
    admin: bool,
    createdAt: DateTimeImmutable,
}
```

---

## Funcionalidades por Área

### Mostruário (/mostruario)
- Listagem pública de produtos ativos
- Filtro por gênero (M/F/U)
- Busca por nome/SKU
- Ordenação: **alfabética** com **esgotados por último**
- Integração WhatsApp (botão para contato)
- Galeria de imagens por produto
- **Footer com bandeiras de cartão** (responsivo)

### Gestão de Produtos
- CRUD completo
- Upload de imagens (carousel)
- Definição de preço em pricebooks
- Associação com categorias e marcas

### Gestão de Pedidos
- Criação manual de pedidos
- Status workflow (PENDING → APPROVED → SHIPPED → DELIVERED)
- Cálculo de totais
- Histórico de pedidos

---

## Fluxo de Validação e Erros

```
Request → Validation (throws ValidationException)
        ↓
    Catch em Controller
        ↓
    Responder::view(422, $errors)
```

**Exceções customizadas**:
- `NotFoundException` → 404
- `ValidationException` → 422 (com campos de erro)
- `ForbiddenException` → 403
- `UnauthenticatedException` → 401
- `ConflictException` → 409
- Outras `HttpException` → 500

---

## Checklist para Novas Features

Ao implementar uma nova feature, siga:

1. ✅ Criar/atualizar **Service** com `ServiceInterface`
2. ✅ Criar/atualizar **Repository** com `RepositoryInterface`
3. ✅ Criar/atualizar **Controller** que use o service
4. ✅ Adicionar **DTO** se for transferir dados entre camadas
5. ✅ Adicionar **rota** em `config/routes.php`
6. ✅ Criar/atualizar **view** em `app/Views/pages/`
7. ✅ Adicionar **CSS** ao `public/static/css/app.css`
8. ✅ Adicionar **JS** se necessário em `public/static/js/`
9. ✅ **Testar** em ambos os fluxos (público e admin)
10. ✅ **Atualizar** `PROJECT_STATE.md`

---

## Git Workflow

```bash
# Sempre trabalhe na branch develop
git checkout develop

# Faça commits semânticos
git commit -m "Add alphabetical sorting to showcase items"

# Commits sempre incluem atribuição
# (veja template de commit no git config)
```

---

## Performance

- **CSS**: Monolítico (`app.css`) para 1 request
- **JS**: Modular por página, carregado dinamicamente
- **Imagens**: JPEG otimizadas, lazy loading em showcase
- **BD**: Índices em colunas de busca (name, sku, gender)

---

## Contatos e Referências

- **Projeto Go** (pasta pai): Versão canônica com mesmas regras
- **Banco**: `patylook` existente (nunca criar tabelas)
- **Hosting**: HostGator (Apache + mod_rewrite)

---

**Atualizado em**: 2026-11-27  
**Versão do Projeto**: PHP 8.2  
**Branch Principal**: `develop`
