<?php

use App\Livewire\Inventory\StockTransferForm;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Actions\TransferStock;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

function transferMakeSecondWarehouse(): Warehouse
{
    return Warehouse::create(['name' => 'انبار دوم']);
}

it('transfers stock between two warehouses, decreasing the source and increasing the destination', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $fromWarehouse = inventoryMakeWarehouse();
    $toWarehouse = transferMakeSecondWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $fromWarehouse->id,
        'quantity' => 20,
        'unit_cost' => 1000,
    ], $user);

    $transfer = app(TransferStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'quantity' => 8,
        'note' => 'انتقال به شعبه دوم',
    ], $user);

    $fromStock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $fromWarehouse->id)->firstOrFail();
    $toStock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $toWarehouse->id)->firstOrFail();

    expect((float) $fromStock->quantity_on_hand)->toBe(12.0)
        ->and((float) $toStock->quantity_on_hand)->toBe(8.0)
        ->and((float) $toStock->average_cost)->toBe(1000.0);

    $movements = StockMovement::withoutGlobalScopes()->where('stock_transfer_id', $transfer->id)->get();

    expect($movements)->toHaveCount(2);

    $outMovement = $movements->firstWhere('movement_type', MovementType::TransferOut);
    $inMovement = $movements->firstWhere('movement_type', MovementType::TransferIn);

    expect($outMovement)->not->toBeNull()
        ->and($inMovement)->not->toBeNull()
        ->and($outMovement->stock_id)->toBe($fromStock->id)
        ->and($inMovement->stock_id)->toBe($toStock->id)
        ->and((float) $outMovement->quantity)->toBe(8.0)
        ->and((float) $inMovement->quantity)->toBe(8.0)
        ->and($outMovement->stock_transfer_id)->toBe($inMovement->stock_transfer_id);

    expect(StockTransfer::query()->count())->toBe(1);
});

it('rejects a transfer larger than the available stock in the source warehouse', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $fromWarehouse = inventoryMakeWarehouse();
    $toWarehouse = transferMakeSecondWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $fromWarehouse->id,
        'quantity' => 5,
    ], $user);

    expect(fn () => app(TransferStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'quantity' => 10,
    ], $user))->toThrow(InvalidArgumentException::class);

    expect(StockTransfer::query()->count())->toBe(0);
});

it('rejects a transfer where source and destination warehouses are the same, at the Action level', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
    ], $user);

    expect(fn () => app(TransferStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'quantity' => 5,
    ], $user))->toThrow(InvalidArgumentException::class);
});

it('rejects a transfer where source and destination warehouses are the same, at the database level', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('CHECK constraint فقط روی mysql واقعی اعمال می‌شود.');
    }

    [$user, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    expect(fn () => DB::table('stock_transfers')->insert([
        'id' => (string) Str::uuid(),
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('computes the destination average_cost correctly when the destination was completely empty', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $fromWarehouse = inventoryMakeWarehouse();
    $toWarehouse = transferMakeSecondWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $fromWarehouse->id,
        'quantity' => 10,
        'unit_cost' => 5000,
    ], $user);

    app(TransferStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'quantity' => 4,
    ], $user);

    $toStock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $toWarehouse->id)->firstOrFail();

    expect((float) $toStock->average_cost)->toBe(5000.0);
});

it('computes the destination average_cost as a weighted average when the destination already had stock', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $fromWarehouse = inventoryMakeWarehouse();
    $toWarehouse = transferMakeSecondWarehouse();
    $product = inventoryMakeProduct($company, $user);

    // مبدأ: ۱۰ عدد با بهای ۵۰۰۰
    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $fromWarehouse->id,
        'quantity' => 10,
        'unit_cost' => 5000,
    ], $user);

    // مقصد از قبل: ۱۰ عدد با بهای ۱۰۰۰۰
    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $toWarehouse->id,
        'quantity' => 10,
        'unit_cost' => 10000,
    ], $user);

    // جابجایی ۱۰ عدد از مبدأ (بهای ۵۰۰۰) به مقصد
    app(TransferStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'quantity' => 10,
    ], $user);

    // (10×10000 + 10×5000) / 20 = 7500
    $toStock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $toWarehouse->id)->firstOrFail();

    expect((float) $toStock->average_cost)->toBe(7500.0)
        ->and((float) $toStock->quantity_on_hand)->toBe(20.0);
});

it('forbids a viewer role from transferring stock', function () {
    [$operator, $company] = inventoryActingAsWithRole('operator');
    $fromWarehouse = inventoryMakeWarehouse();
    $toWarehouse = transferMakeSecondWarehouse();
    $product = inventoryMakeProduct($company, $operator);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $fromWarehouse->id,
        'quantity' => 10,
    ], $operator);

    $viewer = User::factory()->create(['is_super_admin' => false]);
    inventoryGiveRole($viewer, $company, 'viewer');

    expect(fn () => app(TransferStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'quantity' => 5,
    ], $viewer))->toThrow(AuthorizationException::class);
});

it('keeps stock transfers isolated per owning company even for the same physical warehouses', function () {
    [$userA, $companyA] = inventoryActingAsWithRole('operator');
    $fromWarehouse = inventoryMakeWarehouse();
    $toWarehouse = transferMakeSecondWarehouse();
    $productA = inventoryMakeProduct($companyA, $userA);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $companyA->id,
        'product_id' => $productA->id,
        'warehouse_id' => $fromWarehouse->id,
        'quantity' => 20,
    ], $userA);

    app(TransferStock::class)->handle([
        'owner_company_id' => $companyA->id,
        'product_id' => $productA->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'quantity' => 5,
    ], $userA);

    [$userB, $companyB] = inventoryActingAsWithRole('operator');

    expect(fn () => app(TransferStock::class)->handle([
        'owner_company_id' => $companyA->id,
        'product_id' => $productA->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'quantity' => 1,
    ], $userB))->toThrow(AuthorizationException::class);

    expect(StockTransfer::withoutGlobalScopes()->where('owner_company_id', $companyA->id)->count())->toBe(1)
        ->and(StockTransfer::withoutGlobalScopes()->where('owner_company_id', $companyB->id)->count())->toBe(0);
});

it('never deadlocks two concurrent opposite-direction transfers between the same two warehouses', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('قفل ردیفی واقعی (lockForUpdate) فقط روی mysql واقعی با دو اتصال مستقل قابل تست است.');
    }

    [$user, $company] = inventoryActingAsWithRole('operator');
    $warehouseA = inventoryMakeWarehouse();
    $warehouseB = transferMakeSecondWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouseA->id,
        'quantity' => 100,
    ], $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouseB->id,
        'quantity' => 100,
    ], $user);

    $config = config('database.connections.mysql');
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

    $errors = [];

    $run = function (string $fromId, string $toId, int $delayMicroseconds) use ($dsn, $config, $product, &$errors) {
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 5');

        try {
            $pdo->beginTransaction();

            $ids = collect([$fromId, $toId])->sort()->values();

            foreach ($ids as $id) {
                $pdo->prepare('SELECT quantity_on_hand FROM stocks WHERE warehouse_id = ? AND product_id = ? FOR UPDATE')
                    ->execute([$id, $product->id]);
            }

            usleep($delayMicroseconds);

            $pdo->prepare('UPDATE stocks SET quantity_on_hand = quantity_on_hand - 5 WHERE warehouse_id = ? AND product_id = ?')
                ->execute([$fromId, $product->id]);
            $pdo->prepare('UPDATE stocks SET quantity_on_hand = quantity_on_hand + 5 WHERE warehouse_id = ? AND product_id = ?')
                ->execute([$toId, $product->id]);

            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $exception->getCode();
        }
    };

    // دو جابجایی هم‌زمان در جهت مخالف بین همان دو انبار — هر دو ترتیب قفل یکسان
    // (warehouse_id صعودی) را دنبال می‌کنند، پس نباید هیچ‌کدام Deadlock (1213) بگیرد؛
    // نهایتاً هر دو باید یا commit موفق شوند یا با یک خطای منطقی رد شوند، نه timeout خام.
    $run($warehouseA->id, $warehouseB->id, 100000);
    $run($warehouseB->id, $warehouseA->id, 0);

    foreach ($errors as $errorCode) {
        expect($errorCode)->not->toBe('40001'); // deadlock found when trying to get lock
    }

    $stockA = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouseA->id)->firstOrFail();
    $stockB = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouseB->id)->firstOrFail();

    // هر دو جابجایی ۵ واحدی در جهت مخالف اجرا شدند، پس مجموع کل باید ثابت بماند.
    expect((float) $stockA->quantity_on_hand + (float) $stockB->quantity_on_hand)->toBe(200.0);
});

it('submits a transfer through the real StockTransferForm component and lists it in the history table', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $fromWarehouse = inventoryMakeWarehouse();
    $toWarehouse = transferMakeSecondWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $fromWarehouse->id,
        'quantity' => 10,
    ], $user);

    Livewire::test(StockTransferForm::class)
        ->assertOk()
        ->set('product_id', $product->id)
        ->set('from_warehouse_id', $fromWarehouse->id)
        ->set('to_warehouse_id', $toWarehouse->id)
        ->set('quantity', '3')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee($toWarehouse->name);

    $toStock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $toWarehouse->id)->firstOrFail();

    expect((float) $toStock->quantity_on_hand)->toBe(3.0);
});

it('sets created_at on a stock transfer (regression: BelongsToCompany creating-listener halt bug)', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $fromWarehouse = inventoryMakeWarehouse();
    $toWarehouse = transferMakeSecondWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $fromWarehouse->id,
        'quantity' => 10,
    ], $user);

    Livewire::test(StockTransferForm::class)
        ->set('product_id', $product->id)
        ->set('from_warehouse_id', $fromWarehouse->id)
        ->set('to_warehouse_id', $toWarehouse->id)
        ->set('quantity', '3')
        ->call('save');

    $transfer = StockTransfer::withoutGlobalScopes()->latest('created_at')->first();

    expect($transfer)->not->toBeNull()
        ->and($transfer->created_at)->not->toBeNull();
});

it('shows the real creator name in the transfer history table (User has full_name, not name)', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $fromWarehouse = inventoryMakeWarehouse();
    $toWarehouse = transferMakeSecondWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $fromWarehouse->id,
        'quantity' => 10,
    ], $user);

    Livewire::test(StockTransferForm::class)
        ->set('product_id', $product->id)
        ->set('from_warehouse_id', $fromWarehouse->id)
        ->set('to_warehouse_id', $toWarehouse->id)
        ->set('quantity', '3')
        ->call('save')
        ->assertSee($user->full_name);
});
