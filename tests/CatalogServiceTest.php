<?php

declare(strict_types=1);

namespace Patylook\Tests;

use DateTimeImmutable;
use Patylook\Dto\ImageUpload;
use Patylook\Dto\ProductImages;
use Patylook\Exceptions\NotFoundException;
use Patylook\Exceptions\ValidationException;
use Patylook\Infrastructure\GdImageProcessor;
use Patylook\Models\Brand;
use Patylook\Models\Gender;
use Patylook\Models\Money;
use Patylook\Models\Pricebook;
use Patylook\Models\Product;
use Patylook\Models\ProductCategory;
use Patylook\Models\User;
use Patylook\Services\BrandService;
use Patylook\Services\PricebookService;
use Patylook\Services\ProductImageService;
use Patylook\Services\ProductImageStore;
use Patylook\Services\ProductService;
use Patylook\Tests\Fakes\FakeBrands;
use Patylook\Tests\Fakes\FakeCategories;
use Patylook\Tests\Fakes\FakeClock;
use Patylook\Tests\Fakes\FakeImageRows;
use Patylook\Tests\Fakes\FakePrices;
use Patylook\Tests\Fakes\FakeProducts;
use Patylook\Tests\Fakes\FakeStorage;
use Patylook\Tests\Fakes\FakeTransactions;
use Patylook\Tests\Fakes\NullLogger;

/** Product creation with images, image management, prices and brands (ported from the Go service tests). */
final class CatalogServiceTest extends TestCase
{
    private FakeProducts $products;
    private FakeImageRows $rows;
    private FakeStorage $storage;
    private FakeBrands $brands;
    private ProductService $service;
    private ProductImageService $images;
    private User $actor;
    private string $jpeg;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->skip('extensão gd ausente');
        }
        $img = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($img);
        $this->jpeg = (string) ob_get_clean();

        $this->products = new FakeProducts();
        $this->rows = new FakeImageRows();
        $this->storage = new FakeStorage();
        $this->brands = new FakeBrands(new Brand(id: 1, name: 'Ferrari', isActive: true), new Brand(id: 2, name: 'Velha', isActive: false));
        $categories = new FakeCategories(new ProductCategory(1, 'Perfumaria'));
        $tx = new FakeTransactions($this->products, $this->rows);
        $store = new ProductImageStore($this->rows, $this->storage, new GdImageProcessor(), new NullLogger());
        $this->service = new ProductService($categories, $this->brands, $this->products, $store, $tx, new FakeClock());
        $this->images = new ProductImageService($this->products, $this->rows, $this->storage, $store, $tx, new NullLogger());
        $this->actor = new User(id: 5);
    }

    private function upload(string $name = 'a.jpg'): ImageUpload
    {
        return new ImageUpload($name, $this->jpeg);
    }

    private function newProduct(string $name = 'Perfume', int $brand = 1): Product
    {
        return new Product(name: $name, categoryId: 1, brandId: $brand, gender: Gender::Unisex, stock: 3, sku: 'IGNORED');
    }

    public function testCreateProductStoresImagesAndGeneratesSku(): void
    {
        $p = $this->newProduct();
        $this->service->create($this->actor, $p, new ProductImages($this->upload(), [$this->upload('b.jpg'), $this->upload('c.jpg')]));
        $this->assertSame('PER-FER-2411261602', $p->sku, 'SKU ignora o valor digitado');
        $this->assertSame(5, $p->createdBy);
        $this->assertTrue($p->isActive);
        $folder = 'PER-FER-2411261602';
        $this->assertSame(
            ["$folder/principal.jpg", "$folder/imagem-01.jpg", "$folder/imagem-02.jpg"],
            array_keys($this->storage->files),
        );
        $this->assertSame(
            ['principal.jpg', 'imagem-01.jpg', 'imagem-02.jpg'],
            array_map(fn ($r) => $r->fileName, $this->images->list($p->id)),
        );
        $this->assertSame("images/products/$folder/principal.jpg", $this->images->list($p->id)[0]->path);
    }

    public function testCreateProductRequiresValidMainImage(): void
    {
        $e = $this->assertThrows(ValidationException::class, fn () => $this->service->create($this->actor, $this->newProduct(), new ProductImages()));
        $this->assertTrue($e->has('main_image'));
        $e = $this->assertThrows(ValidationException::class, fn () => $this->service->create($this->actor, $this->newProduct(), new ProductImages(new ImageUpload('x.jpg', 'junk'))));
        $this->assertContains('x.jpg', $e->fields()['main_image']);
        $this->assertSame([], $this->products->items);
        $this->assertSame([], $this->storage->files);
    }

    public function testCreateProductReportsBadExtraImages(): void
    {
        $e = $this->assertThrows(ValidationException::class, fn () => $this->service->create($this->actor, $this->newProduct(), new ProductImages($this->upload(), [new ImageUpload('bad.gif', 'GIF89a')])));
        $this->assertContains('bad.gif', $e->fields()['images']);
        $e = $this->assertThrows(ValidationException::class, fn () => $this->service->create($this->actor, $this->newProduct(), new ProductImages($this->upload(), array_fill(0, 9, $this->upload()))));
        $this->assertContains('8', $e->fields()['images']);
        $this->assertSame([], $this->products->items);
    }

    public function testCreateProductRollsBackWhenDiskFails(): void
    {
        $this->storage->failAfter = 1; // the second file fails
        $this->assertThrows(\RuntimeException::class, fn () => $this->service->create($this->actor, $this->newProduct(), new ProductImages($this->upload(), [$this->upload()])));
        $this->assertSame([], $this->products->items, 'produto desfeito');
        $this->assertSame([], $this->rows->rows, 'linhas desfeitas');
        $this->assertSame([], $this->storage->files, 'arquivos órfãos removidos');
    }

    public function testDuplicateSkuWritesNothing(): void
    {
        $this->service->create($this->actor, $this->newProduct(), new ProductImages($this->upload()));
        $filesBefore = $this->storage->files;
        $e = $this->assertThrows(ValidationException::class, fn () => $this->service->create($this->actor, $this->newProduct('Outro'), new ProductImages($this->upload(), [$this->upload()])));
        $this->assertTrue($e->has('sku'));
        $this->assertSame($filesBefore, $this->storage->files, 'a segunda tentativa não escreveu nada');
        $this->assertSame(1, count($this->products->items));
    }

    public function testCreateProductValidatesBrandAndCategory(): void
    {
        $cases = [
            'no brand' => [$this->newProduct('P', 0), 'brand_id'],
            'unknown brand' => [$this->newProduct('P', 99), 'brand_id'],
            'inactive brand' => [$this->newProduct('P', 2), 'brand_id'],
            'unknown category' => [new Product(name: 'P', categoryId: 99, brandId: 1), 'product_category_id'],
            'no category' => [new Product(name: 'P', brandId: 1), 'product_category_id'],
        ];
        foreach ($cases as $name => [$product, $field]) {
            $e = $this->assertThrows(ValidationException::class, fn () => $this->service->create($this->actor, $product, new ProductImages($this->upload())), $name);
            $this->assertTrue($e->has($field), "$name: erro em $field");
        }
        $this->assertSame([], $this->storage->files);
    }

    public function testAddAndDeleteImages(): void
    {
        $p = $this->newProduct();
        $this->service->create($this->actor, $p, new ProductImages($this->upload(), [$this->upload()]));

        $this->images->add($this->actor, $p->id, new ProductImages(null, [$this->upload(), $this->upload()]));
        $this->assertSame(['principal.jpg', 'imagem-01.jpg', 'imagem-02.jpg', 'imagem-03.jpg'], array_map(fn ($r) => $r->fileName, $this->images->list($p->id)));

        // Replacing the main image only overwrites the file.
        $rowsBefore = count($this->rows->rows);
        $this->images->add($this->actor, $p->id, new ProductImages($this->upload()));
        $this->assertSame($rowsBefore, count($this->rows->rows));

        // Numbering continues after a removal.
        $second = $this->images->list($p->id)[1];
        $this->images->delete($p->id, $second->id);
        $this->images->add($this->actor, $p->id, new ProductImages(null, [$this->upload()]));
        $this->assertContains('imagem-04.jpg', implode(',', array_map(fn ($r) => $r->fileName, $this->images->list($p->id))));

        $main = $this->images->list($p->id)[0];
        $this->assertThrows(ValidationException::class, fn () => $this->images->delete($p->id, $main->id), 'a principal não pode ser removida');
        $this->assertThrows(NotFoundException::class, fn () => $this->images->delete(999, $second->id + 1), 'imagem de outro produto');
        $this->assertThrows(ValidationException::class, fn () => $this->images->add($this->actor, $p->id, new ProductImages()), 'nada enviado');
        $this->assertThrows(ValidationException::class, fn () => $this->images->add($this->actor, $p->id, new ProductImages(null, array_fill(0, 8, $this->upload()))), 'acima do limite');
    }

    public function testMainImageIsRequiredBeforeExtras(): void
    {
        $this->products->items[1] = new Product(id: 1, name: 'Legado', sku: 'LEG-1', isActive: true);
        $e = $this->assertThrows(ValidationException::class, fn () => $this->images->add($this->actor, 1, new ProductImages(null, [$this->upload()])));
        $this->assertTrue($e->has('main_image'));
    }

    public function testSkuCannotBeEditedAndInactiveBrandIsRefused(): void
    {
        $p = $this->newProduct();
        $this->service->create($this->actor, $p, new ProductImages($this->upload()));
        $sku = $p->sku;

        $edit = new Product(id: $p->id, name: 'Novo nome', sku: 'HACK-1', categoryId: 1, brandId: 1, gender: Gender::Female, stock: 9);
        $this->service->update($edit);
        $this->assertSame($sku, $this->products->items[$p->id]->sku);
        $this->assertSame('Novo nome', $this->products->items[$p->id]->name);
        $this->assertSame(Gender::Female, $this->products->items[$p->id]->gender, 'o gênero é editável');

        $e = $this->assertThrows(ValidationException::class, fn () => $this->service->update(new Product(id: $p->id, name: 'X', categoryId: 1, brandId: 1)));
        $this->assertTrue($e->has('gender'), 'gênero é obrigatório');

        $e = $this->assertThrows(ValidationException::class, fn () => $this->service->update(new Product(id: $p->id, name: 'X', categoryId: 1, brandId: 2, gender: Gender::Male)));
        $this->assertTrue($e->has('brand_id'));
        // Keeping the current (even if later deactivated) brand is fine.
        $this->brands->items[1]->isActive = false;
        $this->service->update(new Product(id: $p->id, name: 'Y', categoryId: 1, brandId: 1, gender: Gender::Male));
        $this->assertSame('Y', $this->products->items[$p->id]->name);
    }

    public function testAddPriceStoresMarkupDerivedFromFinalPrice(): void
    {
        $products = new FakeProducts(new Product(id: 1, name: 'P'));
        $prices = new FakePrices();
        $svc = new PricebookService($prices, $products);
        $entry = new Pricebook(
            productId: 1,
            productCost: new Money(5000),
            packagingCost: new Money(500),
            logisticsCost: new Money(300),
            otherCosts: new Money(200),
            finalPrice: new Money(9000),
            validFrom: new DateTimeImmutable('2026-01-01 13:45:00'),
            validTo: new DateTimeImmutable('2026-12-31 08:00:00'),
        );
        $svc->add($this->actor, $entry);
        $stored = $prices->created[0];
        $this->assertSame(5000, $stored->markupPercent->hundredths);
        $this->assertSame(5, $stored->createdBy);
        $this->assertSame('2026-01-01 00:00:00', $stored->validFrom?->format('Y-m-d H:i:s'), 'só a data conta');

        $below = new Pricebook(productId: 1, productCost: new Money(5000), finalPrice: new Money(4000), validFrom: new DateTimeImmutable('2026-01-01'));
        $this->assertThrows(ValidationException::class, fn () => $svc->add($this->actor, $below));
        $unknown = new Pricebook(productId: 99, productCost: new Money(100), finalPrice: new Money(200), validFrom: new DateTimeImmutable('2026-01-01'));
        $this->assertThrows(NotFoundException::class, fn () => $svc->add($this->actor, $unknown));
        $this->assertSame(1, count($prices->created), 'nada mais foi gravado');
    }

    public function testBrandSave(): void
    {
        $brands = new FakeBrands();
        $svc = new BrandService($brands);
        $b = new Brand(name: '  Natura ');
        $svc->save($this->actor, $b);
        $this->assertSame('Natura', $brands->items[$b->id]->name);
        $this->assertTrue($brands->items[$b->id]->isActive);
        $this->assertSame(5, $brands->items[$b->id]->createdBy);

        $svc->save(new User(id: 77), new Brand(id: $b->id, name: 'Natura 2'));
        $this->assertSame(5, $brands->items[$b->id]->createdBy, 'editar mantém quem cadastrou');

        $this->assertThrows(ValidationException::class, fn () => $svc->save($this->actor, new Brand(name: '   ')));
        $e = $this->assertThrows(ValidationException::class, fn () => $svc->save($this->actor, new Brand(name: 'Natura 2')));
        $this->assertSame('Já existe uma marca com esse nome', $e->fields()['name']);
    }
}
