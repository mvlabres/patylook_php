<?php

declare(strict_types=1);

namespace Patylook\Tests\Fakes;

use DateTimeImmutable;
use Patylook\Contracts\BrandRepositoryInterface;
use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\CustomerRepositoryInterface;
use Patylook\Contracts\ImageStorageInterface;
use Patylook\Contracts\OrderRepositoryInterface;
use Patylook\Contracts\PasswordHasherInterface;
use Patylook\Contracts\PaymentTypeRepositoryInterface;
use Patylook\Contracts\PricebookRepositoryInterface;
use Patylook\Contracts\ProductCategoryRepositoryInterface;
use Patylook\Contracts\ProductImageRepositoryInterface;
use Patylook\Contracts\ProductRepositoryInterface;
use Patylook\Contracts\SessionStoreInterface;
use Patylook\Contracts\TransactionManagerInterface;
use Patylook\Contracts\UserRepositoryInterface;
use Patylook\Dto\ListParams;
use Patylook\Dto\ListResult;
use Patylook\Dto\OrderFilter;
use Patylook\Dto\OrderSummary;
use Patylook\Dto\StoredFile;
use Patylook\Exceptions\ConflictException;
use Patylook\Exceptions\InsufficientStockException;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\Brand;
use Patylook\Models\Customer;
use Patylook\Models\Order;
use Patylook\Models\OrderStatus;
use Patylook\Models\PaymentType;
use Patylook\Models\Pricebook;
use Patylook\Models\Product;
use Patylook\Models\ProductCategory;
use Patylook\Models\ProductImage;
use Patylook\Models\Session;
use Patylook\Models\User;
use Patylook\Models\UserCategory;
use RuntimeException;

/** A clock the tests can move. */
final class FakeClock implements ClockInterface
{
    public function __construct(public DateTimeImmutable $time = new DateTimeImmutable('2026-11-24 16:02:00'))
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }

    public function advance(string $modifier): void
    {
        $this->time = $this->time->modify($modifier);
    }
}

/** "Hashes" by prefixing, so tests can read the stored value. */
final class FakeHasher implements PasswordHasherInterface
{
    public function hash(string $password): string
    {
        return 'hash:' . $password;
    }

    public function verify(string $hash, string $password): bool
    {
        return $hash === 'hash:' . $password;
    }
}

/**
 * Runs the work, then restores the snapshots of the registered stores when it throws, emulating a
 * database rollback.
 */
final class FakeTransactions implements TransactionManagerInterface
{
    /** @var list<Snapshottable> */
    private array $stores;

    public function __construct(Snapshottable ...$stores)
    {
        $this->stores = $stores;
    }

    public function run(callable $work): mixed
    {
        $snapshots = array_map(fn (Snapshottable $s): mixed => $s->snapshot(), $this->stores);
        try {
            return $work();
        } catch (\Throwable $e) {
            foreach ($this->stores as $i => $store) {
                $store->restore($snapshots[$i]);
            }
            throw $e;
        }
    }
}

interface Snapshottable
{
    public function snapshot(): mixed;

    public function restore(mixed $snapshot): void;
}

final class FakeUsers implements UserRepositoryInterface
{
    /** @var array<int, User> */
    public array $byId = [];

    public function __construct(User ...$users)
    {
        foreach ($users as $u) {
            $this->byId[$u->id] = $u;
        }
    }

    public function count(): int
    {
        return count($this->byId);
    }

    public function findById(int $id): User
    {
        return isset($this->byId[$id]) ? clone $this->byId[$id] : throw new NotFoundException();
    }

    public function findByUsername(string $username): User
    {
        foreach ($this->byId as $u) {
            if ($u->username === $username) {
                return clone $u;
            }
        }
        throw new NotFoundException();
    }

    public function list(ListParams $params): ListResult
    {
        return new ListResult(array_values($this->byId), count($this->byId));
    }

    public function create(User $user): void
    {
        foreach ($this->byId as $u) {
            if ($u->username === $user->username) {
                throw new ConflictException('username');
            }
        }
        $user->id = max([0, ...array_keys($this->byId)]) + 1;
        $this->byId[$user->id] = clone $user;
    }

    public function update(User $user): void
    {
        $this->byId[$user->id] = clone $user;
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->byId[$id]->passwordHash = $hash;
    }

    public function setActive(int $id, bool $active): void
    {
        $this->byId[$id]->isActive = $active;
    }

    public function listCategories(): array
    {
        return [new UserCategory(1, 'admin')];
    }

    public function ensureCategory(string $name): int
    {
        return 1;
    }
}

final class FakeBrands implements BrandRepositoryInterface
{
    /** @var array<int, Brand> */
    public array $items = [];

    public function __construct(Brand ...$brands)
    {
        foreach ($brands as $b) {
            $this->items[$b->id] = $b;
        }
    }

    public function findById(int $id): Brand
    {
        return isset($this->items[$id]) ? clone $this->items[$id] : throw new NotFoundException();
    }

    public function list(ListParams $params): ListResult
    {
        return new ListResult(array_values($this->items), count($this->items));
    }

    public function listActive(): array
    {
        return array_values(array_filter($this->items, fn (Brand $b): bool => $b->isActive));
    }

    public function create(Brand $brand): void
    {
        foreach ($this->items as $b) {
            if ($b->name === $brand->name) {
                throw new ConflictException('name');
            }
        }
        $brand->id = max([0, ...array_keys($this->items)]) + 1;
        $this->items[$brand->id] = clone $brand;
    }

    public function update(Brand $brand): void
    {
        $this->items[$brand->id]->name = $brand->name;
    }

    public function setActive(int $id, bool $active): void
    {
        $this->items[$id]->isActive = $active;
    }
}

final class FakeCategories implements ProductCategoryRepositoryInterface
{
    /** @var array<int, ProductCategory> */
    public array $items = [];

    public function __construct(ProductCategory ...$categories)
    {
        foreach ($categories as $c) {
            $this->items[$c->id] = $c;
        }
    }

    public function list(): array
    {
        return array_values($this->items);
    }

    public function findById(int $id): ProductCategory
    {
        return $this->items[$id] ?? throw new NotFoundException();
    }

    public function create(ProductCategory $category): void
    {
    }

    public function update(ProductCategory $category): void
    {
    }

    public function delete(int $id): void
    {
    }
}

final class FakeProducts implements ProductRepositoryInterface, Snapshottable
{
    /** @var array<int, Product> */
    public array $items = [];

    public function __construct(Product ...$products)
    {
        foreach ($products as $p) {
            $this->items[$p->id] = $p;
        }
    }

    public function snapshot(): mixed
    {
        return array_map(fn (Product $p): Product => clone $p, $this->items);
    }

    public function restore(mixed $snapshot): void
    {
        $this->items = $snapshot;
    }

    public function findById(int $id): Product
    {
        return isset($this->items[$id]) ? clone $this->items[$id] : throw new NotFoundException();
    }

    public function list(ListParams $params, DateTimeImmutable $on): ListResult
    {
        return new ListResult(array_values($this->items), count($this->items));
    }

    public function listSellable(DateTimeImmutable $on): array
    {
        return array_values($this->items);
    }

    public function create(Product $product): void
    {
        foreach ($this->items as $p) {
            if ($p->sku === $product->sku) {
                throw new ConflictException('sku');
            }
        }
        $product->id = max([0, ...array_keys($this->items)]) + 1;
        $this->items[$product->id] = clone $product;
    }

    public function update(Product $product): void
    {
        $this->items[$product->id] = clone $product;
    }

    public function setActive(int $id, bool $active): void
    {
        $this->items[$id]->isActive = $active;
    }

    public function decrementStock(int $id, int $qty): void
    {
        if ($this->items[$id]->stock < $qty) {
            throw new InsufficientStockException();
        }
        $this->items[$id]->stock -= $qty;
    }

    public function incrementStock(int $id, int $qty): void
    {
        $this->items[$id]->stock += $qty;
    }

    public function countLowStock(int $threshold): int
    {
        return 0;
    }
}

final class FakePrices implements PricebookRepositoryInterface
{
    /** @var array<int, Pricebook> by product id */
    public array $byProduct = [];

    /** @var list<Pricebook> */
    public array $created = [];

    public function listByProduct(int $productId): array
    {
        return [];
    }

    public function findCurrent(int $productId, DateTimeImmutable $on): Pricebook
    {
        return $this->byProduct[$productId] ?? throw new NotFoundException();
    }

    public function create(Pricebook $entry): void
    {
        $this->created[] = clone $entry;
    }
}

final class FakeCustomers implements CustomerRepositoryInterface
{
    /** @var array<int, Customer> */
    public array $items = [];

    public function findById(int $id): Customer
    {
        return $this->items[$id] ?? throw new NotFoundException();
    }

    public function list(ListParams $params): ListResult
    {
        return new ListResult(array_values($this->items), count($this->items));
    }

    public function listActive(): array
    {
        return array_values($this->items);
    }

    public function create(Customer $customer): void
    {
    }

    public function update(Customer $customer): void
    {
    }

    public function setActive(int $id, bool $active): void
    {
    }
}

final class FakePayments implements PaymentTypeRepositoryInterface
{
    /** @var array<int, PaymentType> */
    public array $items = [];

    public function list(): array
    {
        return array_values($this->items);
    }

    public function findById(int $id): PaymentType
    {
        return $this->items[$id] ?? throw new NotFoundException();
    }

    public function create(PaymentType $type): void
    {
    }

    public function update(PaymentType $type): void
    {
    }

    public function delete(int $id): void
    {
    }
}

final class FakeOrders implements OrderRepositoryInterface, Snapshottable
{
    /** @var array<int, Order> */
    public array $items = [];
    public int $nextId = 0;

    public function snapshot(): mixed
    {
        return [array_map(fn (Order $o): Order => clone $o, $this->items), $this->nextId];
    }

    public function restore(mixed $snapshot): void
    {
        [$this->items, $this->nextId] = $snapshot;
    }

    public function create(Order $order): void
    {
        $order->id = ++$this->nextId;
        $this->items[$order->id] = clone $order;
    }

    public function findById(int $id): Order
    {
        return isset($this->items[$id]) ? clone $this->items[$id] : throw new NotFoundException();
    }

    public function list(OrderFilter $filter): ListResult
    {
        return new ListResult(array_values($this->items), count($this->items));
    }

    public function updateStatus(int $id, OrderStatus $from, OrderStatus $to): void
    {
        if ($this->items[$id]->status !== $from) {
            throw new ConflictException();
        }
        $this->items[$id]->status = $to;
    }

    public function summary(DateTimeImmutable $since): OrderSummary
    {
        return new OrderSummary();
    }

    public function recent(int $limit): array
    {
        return [];
    }
}

final class FakeImageRows implements ProductImageRepositoryInterface, Snapshottable
{
    /** @var array<int, ProductImage> */
    public array $rows = [];

    public function snapshot(): mixed
    {
        return array_map(fn (ProductImage $i): ProductImage => clone $i, $this->rows);
    }

    public function restore(mixed $snapshot): void
    {
        $this->rows = $snapshot;
    }

    public function listByProduct(int $productId): array
    {
        $list = array_values(array_filter($this->rows, fn (ProductImage $i): bool => $i->productId === $productId));
        usort($list, fn (ProductImage $a, ProductImage $b): int => [!$a->isMain(), $a->sortOrder, $a->id] <=> [!$b->isMain(), $b->sortOrder, $b->id]);
        return $list;
    }

    public function listByProducts(array $productIds): array
    {
        $out = [];
        foreach ($productIds as $id) {
            $out[$id] = $this->listByProduct($id);
        }
        return $out;
    }

    public function findById(int $id): ProductImage
    {
        return isset($this->rows[$id]) ? clone $this->rows[$id] : throw new NotFoundException();
    }

    public function create(ProductImage $image): void
    {
        foreach ($this->rows as $r) {
            if ($r->path === $image->path) {
                throw new ConflictException('path');
            }
        }
        $image->id = max([0, ...array_keys($this->rows)]) + 1;
        $this->rows[$image->id] = clone $image;
    }

    public function delete(int $id): void
    {
        unset($this->rows[$id]);
    }
}

/** Keeps "files" in an array and can be told to fail after N writes. */
final class FakeStorage implements ImageStorageInterface
{
    /** @var array<string, string> "folder/name" => data */
    public array $files = [];
    public ?int $failAfter = null;
    private int $writes = 0;

    public function save(string $folder, string $name, string $data): void
    {
        if ($this->failAfter !== null && $this->writes >= $this->failAfter) {
            throw new RuntimeException('disco cheio');
        }
        $this->writes++;
        $this->files[$folder . '/' . $name] = $data;
    }

    public function remove(string $folder, string $name): void
    {
        unset($this->files[$folder . '/' . $name]);
    }

    public function open(string $folder, string $name): StoredFile
    {
        throw new NotFoundException();
    }
}

final class FakeSessions implements SessionStoreInterface
{
    /** @var array<string, Session> */
    public array $items = [];

    public function create(Session $session): void
    {
        $this->items[$session->tokenHash] = clone $session;
    }

    public function find(string $tokenHash): Session
    {
        return isset($this->items[$tokenHash]) ? clone $this->items[$tokenHash] : throw new NotFoundException();
    }

    public function update(Session $session): void
    {
        $this->items[$session->tokenHash] = clone $session;
    }

    public function delete(string $tokenHash): void
    {
        unset($this->items[$tokenHash]);
    }

    public function deleteByUser(int $userId): void
    {
        $this->items = array_filter($this->items, fn (Session $s): bool => $s->userId !== $userId);
    }

    public function deleteExpired(DateTimeImmutable $now): int
    {
        $before = count($this->items);
        $this->items = array_filter($this->items, fn (Session $s): bool => $s->expiresAt > $now);
        return $before - count($this->items);
    }
}

/** Discards log lines. */
final class NullLogger implements \Patylook\Contracts\LoggerInterface
{
    public function debug(string $message, array $context = []): void
    {
    }

    public function info(string $message, array $context = []): void
    {
    }

    public function warning(string $message, array $context = []): void
    {
    }

    public function error(string $message, array $context = []): void
    {
    }
}
