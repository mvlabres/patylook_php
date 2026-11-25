<?php

declare(strict_types=1);

namespace Patylook\Tests;

use DateTimeImmutable;
use Patylook\Dto\ProductTotals;
use Patylook\Exceptions\ValidationException;
use Patylook\Models\Customer;
use Patylook\Models\ImagePaths;
use Patylook\Models\MainSeller;
use Patylook\Models\Money;
use Patylook\Models\Order;
use Patylook\Models\OrderItem;
use Patylook\Models\OrderStatus;
use Patylook\Models\Percent;
use Patylook\Models\Pricebook;
use Patylook\Models\Product;
use Patylook\Models\ProductImage;
use Patylook\Models\User;

/** Business rules of the models, ported from the Go domain tests. */
final class DomainTest extends TestCase
{
    public function testParseMoney(): void
    {
        $cases = [
            ['12', 1200], ['12.5', 1250], ['12,50', 1250], ['1.234,56', 123456], ['1234.56', 123456],
            ['0,05', 5], [',5', 50], ['-3,10', -310], [' 7,00 ', 700],
            ['', null], ['abc', null], ['1,234', null], ['1.2.3', null], ['12,5x', null], ['-', null],
        ];
        foreach ($cases as [$in, $want]) {
            $got = Money::parse($in);
            $this->assertSame($want, $got?->cents, "ParseMoney('$in')");
        }
        $this->assertSame(1250, Percent::parse('12,50')?->hundredths);
    }

    public function testMoneyFormatting(): void
    {
        $this->assertSame('R$ 1.234,56', (string) new Money(123456));
        $this->assertSame('-R$ 0,05', (string) new Money(-5));
        $this->assertSame('R$ 0,00', (string) new Money(0));
        $this->assertSame('R$ 1.000.000,00', (string) new Money(100000000));
        $this->assertSame('1234.56', (new Money(123456))->decimal());
        $this->assertSame('1234,56', (new Money(123456))->input());
        $this->assertSame('12.50', (new Percent(1250))->decimal());
        $this->assertSame('12,50%', (string) new Percent(1250));
    }

    public function testMoneyPercentRoundsHalfAwayFromZero(): void
    {
        $cases = [[10000, 1000, 1000], [1, 5000, 1], [999, 1000, 100], [0, 5000, 0], [-1, 5000, -1]];
        foreach ($cases as [$amount, $pct, $want]) {
            $this->assertSame($want, (new Money($amount))->percent(new Percent($pct))->cents, "$amount * $pct");
        }
    }

    public function testValidCpf(): void
    {
        foreach (['52998224725', '11144477735'] as $cpf) {
            $this->assertTrue(Customer::validCpf($cpf), $cpf);
        }
        foreach (['', '123', '11111111111', '52998224726', '5299822472a', '529982247250'] as $cpf) {
            $this->assertFalse(Customer::validCpf($cpf), "'$cpf'");
        }
    }

    public function testCustomerNormalizeStripsDocumentPunctuation(): void
    {
        $c = new Customer(name: ' Maria ', document: '529.982.247-25', phone: ' 1199 ', email: ' MARIA@Example.COM ');
        $c->normalize();
        $this->assertSame('52998224725', $c->document);
        $this->assertSame('maria@example.com', $c->email);
        $c->validate();
        $this->assertSame('Maria', $c->name);
    }

    private function price(int $final): Pricebook
    {
        return new Pricebook(
            productCost: new Money(5000),
            packagingCost: new Money(500),
            logisticsCost: new Money(300),
            otherCosts: new Money(200), // total 60,00
            finalPrice: new Money($final),
            validFrom: new DateTimeImmutable('2026-01-01'),
        );
    }

    public function testPricebookMarkupIsDerivedFromFinalPrice(): void
    {
        $cases = [[9000, 5000], [6000, 0], [7000, 1667], [6001, 2], [12345, 10575], [600000, 990000]];
        foreach ($cases as [$final, $want]) {
            $p = $this->price($final);
            $p->validate();
            $this->assertSame(6000, $p->totalCost()->cents);
            $this->assertSame($want, $p->markupPercent->hundredths, "final $final");
        }
        $p = $this->price(9000);
        $p->validate();
        $this->assertSame(9000, $p->totalCost()->plus($p->totalCost()->percent($p->markupPercent))->cents);

        $bad = $this->price(9000);
        $bad->validTo = new DateTimeImmutable('2025-12-31');
        $e = $this->assertThrows(ValidationException::class, fn () => $bad->validate());
        $this->assertTrue($e->has('valid_to'));
    }

    public function testPricebookFinalPriceRules(): void
    {
        $from = new DateTimeImmutable('2026-01-01');
        $field = function (Pricebook $p): array {
            try {
                $p->validate();
                return [];
            } catch (ValidationException $e) {
                return $e->fields();
            }
        };
        $this->assertTrue(isset($field(new Pricebook(productCost: new Money(5000), finalPrice: new Money(4999), validFrom: $from))['final_price']), 'below cost');
        $this->assertTrue(isset($field(new Pricebook(productCost: new Money(5000), finalPrice: new Money(-1), validFrom: $from))['final_price']), 'negative');
        $this->assertTrue(isset($field(new Pricebook(productCost: new Money(100), finalPrice: new Money(100 * 100001), validFrom: $from))['final_price']), 'markup limit');
        $this->assertTrue(isset($field(new Pricebook(productCost: new Money(100), finalPrice: new Money(Money::MAX + 1), validFrom: $from))['final_price']), 'column limit');
        $this->assertTrue(isset($field(new Pricebook(productCost: new Money(-1), finalPrice: new Money(100), validFrom: $from))['product_cost']), 'negative cost');
        $this->assertTrue(isset($field(new Pricebook(finalPrice: new Money(100)))['valid_from']), 'valid_from required');

        $free = new Pricebook(finalPrice: new Money(2500), validFrom: $from);
        $free->validate();
        $this->assertSame(0, $free->markupPercent->hundredths);
    }

    private function order(): Order
    {
        return new Order(
            customerId: 1,
            paymentTypeId: 1,
            discount: new Money(1000),
            items: [
                new OrderItem(productId: 1, quantity: 2, unitPrice: new Money(5000), discount: new Money(1000)), // 90,00
                new OrderItem(productId: 2, quantity: 1, unitPrice: new Money(2000)), // 20,00
            ],
        );
    }

    public function testMainSellerWhatsappLink(): void
    {
        $this->assertSame('5545920020405', (new MainSeller(1, 'P', '45920020405'))->whatsappDigits(), 'DDD + número ganha o 55');
        $this->assertSame('5511999999999', (new MainSeller(1, 'P', '+55 (11) 99999-9999'))->whatsappDigits());
        $this->assertSame('551415555010', (new MainSeller(1, 'P', '1415555010'))->whatsappDigits(), 'fixo com DDD (10 dígitos) também ganha o 55');
        $this->assertSame(null, (new MainSeller(1, 'P', 'abc'))->whatsappDigits());
        $this->assertSame(null, (new MainSeller(1, 'P', ''))->chatUrl('oi'));
        $this->assertSame(
            'https://wa.me/5511999999999?text=Ol%C3%A1.%20Gostaria%20de%20saber%20mais%20detalhes%20do%20produto%20Perfume%20Ferrari%20Black%20-%20PER-FER-1.',
            (new MainSeller(1, 'P', '11999999999'))->chatUrl(MainSeller::productMessage('Perfume Ferrari Black', 'PER-FER-1')),
        );
    }

    public function testProductRealGainAndTotals(): void
    {
        $p = new Product(currentPrice: new Money(9000), currentCost: new Money(6000));
        $this->assertSame(3000, $p->realGain()?->cents);
        $this->assertSame(null, (new Product())->realGain());
        $this->assertSame(-500, (new Product(currentPrice: new Money(5500), currentCost: new Money(6000)))->realGain()?->cents, 'prejuízo fica negativo');

        $t = new ProductTotals(2, new Money(15000), new Money(10000));
        $this->assertSame(5000, $t->gain()->cents);
        $this->assertSame(5000, $t->markup()?->hundredths);
        $this->assertSame(null, (new ProductTotals(0, Money::zero(), Money::zero()))->markup(), 'sem custos não há markup');
    }

    public function testOrderCalculate(): void
    {
        $o = $this->order();
        $o->calculate(new Percent(500)); // 5% surcharge
        // subtotal 110,00; minus 10,00 = 100,00; +5% = 5,00
        $this->assertSame(11000, $o->subtotal->cents);
        $this->assertSame(500, $o->paymentAddition->cents);
        $this->assertSame(10500, $o->total->cents);

        $mutations = [
            'no items' => function (Order $o): void { $o->items = []; },
            'duplicate product' => function (Order $o): void { $o->items[1]->productId = 1; },
            'zero quantity' => function (Order $o): void { $o->items[0]->quantity = 0; },
            'item discount > gross' => function (Order $o): void { $o->items[1]->discount = new Money(2001); },
            'order discount > subtotal' => function (Order $o): void { $o->discount = new Money(11001); },
            'no customer' => function (Order $o): void { $o->customerId = 0; },
            'negative discount' => function (Order $o): void { $o->discount = new Money(-1); },
        ];
        foreach ($mutations as $name => $mutate) {
            $o = $this->order();
            $mutate($o);
            $this->assertThrows(ValidationException::class, fn () => $o->calculate(Percent::zero()), $name);
        }
    }

    public function testOrderStatusTransitions(): void
    {
        $cases = [
            [OrderStatus::Open, OrderStatus::Completed, true], [OrderStatus::Open, OrderStatus::Canceled, true],
            [OrderStatus::Completed, OrderStatus::Canceled, true], [OrderStatus::Completed, OrderStatus::Open, false],
            [OrderStatus::Canceled, OrderStatus::Open, false], [OrderStatus::Canceled, OrderStatus::Completed, false],
            [OrderStatus::Open, OrderStatus::Open, false],
        ];
        foreach ($cases as [$from, $to, $ok]) {
            $this->assertSame($ok, $from->canTransitionTo($to), $from->value . ' -> ' . $to->value);
        }
        $this->assertSame('Concluído', OrderStatus::Completed->label());
        $this->assertSame(null, OrderStatus::tryFrom('bogus'));
    }

    public function testUserValidate(): void
    {
        (new User(username: 'maria.silva', name: 'Maria', email: 'maria@example.com', categoryId: 1))->validate();
        $invalid = [
            'short username' => new User(username: 'ab', name: 'x', categoryId: 1),
            'long username' => new User(username: 'abcdefghijklmnopq', name: 'x', categoryId: 1),
            'bad chars' => new User(username: 'a b c', name: 'x', categoryId: 1),
            'no name' => new User(username: 'abc', categoryId: 1),
            'bad email' => new User(username: 'abc', name: 'x', email: 'nope', categoryId: 1),
            'missing category' => new User(username: 'abc', name: 'x'),
        ];
        foreach ($invalid as $name => $u) {
            $this->assertThrows(ValidationException::class, fn () => $u->validate(), $name);
        }
    }

    public function testPasswordPolicy(): void
    {
        $this->assertThrows(ValidationException::class, fn () => User::validatePassword('password', 'short'));
        User::validatePassword('password', 'long-enough-password');
        $this->assertThrows(ValidationException::class, fn () => User::validatePassword('password', str_repeat('a', User::MAX_PASSWORD_LENGTH + 1)));
        User::validatePassword('password', str_repeat('a', User::MAX_PASSWORD_LENGTH));
    }

    public function testValidationExceptionKeepsFirstMessageAndSortsSummary(): void
    {
        $v = new ValidationException();
        $v->add('b', 'second field');
        $v->add('a', 'first');
        $v->add('a', 'ignored');
        $this->assertSame('first; second field', $v->getMessage());
        $this->assertSame(['b' => 'second field', 'a' => 'first'], $v->fields());
    }

    public function testImageFolder(): void
    {
        $cases = [
            ['CAM-1', 'CAM-1'], ['cam 1', 'cam-1'], ['A/B\\C', 'A-B-C'], ['  X.Y_Z  ', 'X.Y_Z'],
            ['Camiseta Ação', 'Camiseta-A-o'], ['../etc', 'etc'],
            ['..', null], ['///', null], ['', null], ['...', null],
        ];
        foreach ($cases as [$sku, $want]) {
            $this->assertSame($want, ImagePaths::folderFor($sku), "folderFor('$sku')");
        }
    }

    public function testImagePaths(): void
    {
        $path = ImagePaths::path('CAM-1', ImagePaths::MAIN_NAME);
        $this->assertSame('images/products/CAM-1/principal.jpg', $path);
        $this->assertSame('CAM-1', ImagePaths::folderFromPath($path));
        $image = new ProductImage(fileName: ImagePaths::MAIN_NAME, path: $path);
        $this->assertTrue($image->isMain());
        $this->assertSame('/images/products/CAM-1/principal.jpg', $image->url());
        $this->assertFalse((new ProductImage(fileName: 'imagem-01.jpg'))->isMain());
    }

    public function testGenerateSku(): void
    {
        $at = new DateTimeImmutable('2026-11-24 16:02:00');
        $cases = [
            ['Perfumaria', 'Ferrari', 'PER-FER-2411261602'],
            ['perfumaria', 'ferrari', 'PER-FER-2411261602'],
            ['Água', 'Ação & Cia', 'AGU-ACA-2411261602'],
            ['Hidratante', 'O Boticário', 'HID-OBO-2411261602'],
            ['Kit', '3M', 'KIT-3M-2411261602'],
            ['!!!', 'Natura', 'XXX-NAT-2411261602'],
        ];
        foreach ($cases as [$category, $brand, $want]) {
            $this->assertSame($want, Product::generateSku($category, $brand, $at), "$category/$brand");
        }
        // 05/01/2027 09:07 keeps its leading zeros.
        $this->assertSame('PER-FER-0501270907', Product::generateSku('Perfumaria', 'Ferrari', new DateTimeImmutable('2027-01-05 09:07:59')));
        $sku = Product::generateSku('!!!', '???', $at);
        $this->assertSame($sku, ImagePaths::folderFor($sku));
    }
}
