# Decisões Arquiteturais - Patylook/VL Store

## 1. Por que PHP Puro (Sem Frameworks)?

**Decisão**: Manter a mesma arquitetura do projeto Go - MVC puro sem dependências externas.

**Razões**:
- ✅ Compatibilidade com hospedagem compartilhada (HostGator)
- ✅ Sem `composer.lock` - deployments mais rápidos
- ✅ PSR-4 autoloader customizado (`bootstrap.php`) - controle total
- ✅ Reduz curva de aprendizado (MVC é universal)
- ✅ Performance previsível (sem layers de abstrações desnecessárias)

**Trade-offs**:
- ❌ Mais código manual (validação, paginação, etc)
- ❌ Sem convenções automáticas
- ⚖️ Compensado pela simplicidade e velocidade

---

## 2. Por que CSS Monolítico?

**Decisão**: Único arquivo `public/static/css/app.css` em vez de módulos/SCSS.

**Razões**:
- ✅ 1 request HTTP vs múltiplas (performance)
- ✅ Sem build step (npm, webpack, etc)
- ✅ Variáveis CSS nativas (`--gold`, `--bg`, etc) para tema
- ✅ Fácil manutenção para tema único
- ✅ Sem problema de cascata (organizado por seção com comentários)

**Organização interna**:
```css
/* Variables & resets */
/* Topbar & navigation */
/* Forms & inputs */
/* Product cards & showcase */
/* Payment footer */
/* Media queries */
```

**Trade-offs**:
- ❌ Sem modularidade se projeto crescer muito
- ⚖️ Mantém-se porque projeto é de escopo fixo (loja única)

---

## 3. Por que Service + Repository Duplo?

**Decisão**: Separação explícita entre lógica de negócio (Service) e acesso a dados (Repository).

**Exemplo: Showcase**
```
Request → ShowcaseController
       ↓
       ShowcaseService (lógica: filtros, ordenação, paginação)
       ↓
       ProductRepository (busca produtos)
       ProductImageRepository (busca imagens)
       ↓
       Database
```

**Razões**:
- ✅ Regras de negócio isoladas (fácil testar, reusar)
- ✅ Repository é injetável (pode trocar implementação)
- ✅ Service não conhece SQL (abstração clara)
- ✅ Paginação fica em Service, não em Repository

**Exemplo real**:
```php
// ShowcaseService sabe que "esgotados vão por último"
usort($items, static function (ShowcaseItem $a, ShowcaseItem $b): int {
    if ($a->product->stock > 0 !== $b->product->stock > 0) {
        return $a->product->stock > 0 ? -1 : 1;
    }
    return strcasecmp($a->product->name, $b->product->name);
});

// ProductRepository não sabe disso - só retorna linhas do DB
```

**Trade-offs**:
- ❌ Mais camadas que MVC tradicional
- ✅ Compensado pela clareza e testabilidade

---

## 4. Por que DTOs?

**Decisão**: Usar DTOs (`ShowcaseItem`, `Page`, `OrderSummary`) para transferência entre camadas.

**Exemplo**:
```php
// View recebe ShowcaseItem (DTO)
// - Contém Product (model) + images (array)
// - View não precisa conhecer como dados foram recuperados
foreach ($items as $item) {
    echo $item->product->name;
    echo $item->main()?->publicUrl();
}
```

**Razões**:
- ✅ Contrato explícito entre camadas
- ✅ View não precisa de repository (clean separation)
- ✅ Fácil testar (mock DTOs)
- ✅ Evolução: adicionar campo a DTO sem quebrar service

**Trade-offs**:
- ❌ "Boilerplate" (criar classe vs usar array)
- ✅ Compensado pela segurança de tipos

---

## 5. Por que Validação em Controller + Service?

**Decisão**: 
- **Controller**: Valida entrada de requisição (tipos básicos)
- **Service**: Valida regras de negócio (conflitos, lógica)

**Exemplo**:
```php
// Controller: Valida que preço é número
if (!is_numeric($price)) throw ValidationException('price', 'Deve ser número');

// Service: Valida que preço não é zero (regra de negócio)
if ($price <= 0) throw ValidationException('price', 'Não pode ser zero');
```

**Razões**:
- ✅ Controller é rápido de falhar (input ruim)
- ✅ Service contém lógica complexa (reualizável)
- ✅ Testes podem chamar Service diretamente

**Trade-offs**:
- ❌ Duas camadas de validação
- ✅ Cada uma tem responsabilidade clara

---

## 6. Por que Responder (Responder Pattern)?

**Decisão**: `Responder` é classe central que constrói `Response` (HTTP) e `PageData` (view).

```php
class Responder {
    public function view(string $page, PageData $data): Response
    public function json($data): Response
    public function success($redirectTo, $message): Response
}
```

**Razões**:
- ✅ Reduz duplicação em Controllers (todos precisam de view + status + headers)
- ✅ Ponto único para mudar formato de resposta
- ✅ Integração com flash messages, CSRF

**Trade-offs**:
- ❌ Abstração extra
- ✅ Compensado pela consistência

---

## 7. Ordenação Alfabética com Esgotados por Último

**Decisão**: Implementada em `ShowcaseService::list()` usando `usort()`.

**Por que não no SQL?**
- ❌ SQL `ORDER BY stock DESC, name ASC` é literal
- ✅ PHP permite lógica: "esgotados vão por último DEPOIS de ordenar alfabeticamente"
- ✅ Funciona com filtros aplicados

**Implementação**:
```php
usort($items, static function (ShowcaseItem $a, ShowcaseItem $b): int {
    $aInStock = $a->product->stock > 0;
    $bInStock = $b->product->stock > 0;
    
    // Se um tem stock e outro não, em-stock vem primeiro
    if ($aInStock !== $bInStock) {
        return $aInStock ? -1 : 1;
    }
    
    // Ambos têm stock (ou ambos não): ordena alfabético
    return strcasecmp($a->product->name, $b->product->name);
});
```

**Por que `strcasecmp()` e não `strcmp()`?**
- ✅ Case-insensitive (Anel = anel = ANEL ordenam juntos)
- ✅ Mais amigável para usuário

---

## 8. Destaque Visual para Esgotado

**Decisão**: Elemento HTML `<span class="out-of-stock-label">Esgotado</span>` com CSS dedicado.

**CSS**:
```css
.stock.out-of-stock {
  border-color: rgba(239, 125, 115, .6);
  background: linear-gradient(180deg, rgba(239, 125, 115, .15), rgba(239, 125, 115, .08));
  color: #ef7d73; /* Vermelho */
}
.out-of-stock-label {
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
}
```

**Razões**:
- ✅ Cor vermelha (danger) = visualmente óbvio
- ✅ Uppercase + bold = impacto
- ✅ Background gradiente = consistente com design

---

## 9. Footer de Cartões Responsivo

**Decisão**: 
- Desktop/Tablet: Todas as imagens em 1 linha
- Mobile: Scroll horizontal (não quebra linha)

**Por que não quebra linha em mobile?**
- ✅ Mantém integridade visual (bandeiras lado-a-lado)
- ✅ Scroll horizontal é familiar em mobile
- ✅ Permite tamanhos consistentes

**Implementação**:
```css
.payment-cards {
  display: flex;
  flex-wrap: nowrap;      /* Sem quebra */
  overflow-x: auto;       /* Scroll se necessário */
  gap: 1rem;              /* Espaçamento consistente */
}

.card-logo {
  height: 45px;           /* Desktop */
  flex-shrink: 0;         /* Não encolhe */
}

@media (max-width: 768px) {
  .card-logo { height: 38px; }  /* Tablet */
}

@media (max-width: 480px) {
  .card-logo { height: 35px; }  /* Mobile */
}
```

---

## 10. Por que Imagens de Cartão Extraídas (PNG)?

**Decisão**: Extrair imagens reais da imagem de referência em vez de criar SVGs.

**Razões**:
- ✅ Fidelidade ao design original
- ✅ Sem distorção (SVG renderizado pode não ser exato)
- ✅ Carregamento mais rápido (PNG otimizado < SVG renderizado)

**Processo**:
```python
# Script Python extrai as 5 bandeiras como PNG
img.crop((30, 125, 320, 245))  # VISA
img.crop((340, 125, 630, 245)) # Mastercard
# ... etc
```

---

## Decisões Futuras

| Decisão | Status | Critério de Revisão |
|---------|--------|-------------------|
| Manter CSS monolítico | ✅ Mantém | Se projeto 3x maior, considerar SCSS |
| Repository duplo | ✅ Mantém | Se Service ficar > 500 linhas |
| DTO pattern | ✅ Mantém | Se mais de 10 DTOs, considerar generators |
| PHP puro | ✅ Mantém | Se preciso de autenticação OAuth, considerar framework |

---

**Atualizado em**: 2026-11-27  
**Decisões Implementadas**: 10  
**Decisões em Revisão**: 0
