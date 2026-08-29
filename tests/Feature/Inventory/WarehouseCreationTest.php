<?php

use App\Livewire\Inventory\WarehouseForm;
use App\Livewire\Inventory\WarehouseIndex;
use App\Modules\Inventory\Actions\CreateWarehouse;
use App\Modules\Inventory\Actions\UpdateWarehouse;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

it('lets a holding_admin create a new warehouse from the panel', function () {
    [$user, $company] = inventoryActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(WarehouseForm::class)
        ->set('name', 'انبار جدید تهران')
        ->set('address', 'تهران، خیابان آزادی')
        ->call('save')
        ->assertRedirect(route('inventory.warehouses.index'));

    $warehouse = Warehouse::query()->where('name', 'انبار جدید تهران')->firstOrFail();
    expect($warehouse->address)->toBe('تهران، خیابان آزادی')
        ->and($warehouse->created_by_user_id)->toBe($user->id)
        ->and($warehouse->is_active)->toBeTrue();
});

it('shows the create-warehouse button only to a holding_admin', function () {
    [$adminUser, $adminCompany] = inventoryActingAsWithRole('holding_admin');
    $this->actingAs($adminUser);
    session(['active_company_id' => $adminCompany->id]);

    Livewire::test(WarehouseIndex::class)
        ->assertSee('انبار جدید');

    [$operatorUser, $operatorCompany] = inventoryActingAsWithRole('operator');
    $this->actingAs($operatorUser);
    session(['active_company_id' => $operatorCompany->id]);

    Livewire::test(WarehouseIndex::class)
        ->assertDontSee('انبار جدید');
});

it('rejects warehouse creation for a non holding_admin at the Action level', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    app(CreateWarehouse::class)->handle($user, ['name' => 'انبار غیرمجاز']);
})->throws(AuthorizationException::class);

it('denies mounting WarehouseForm for a non holding_admin', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(WarehouseForm::class)->assertForbidden();
});

it('does not show the is_active checkbox on the create form (always active by default)', function () {
    [$user, $company] = inventoryActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(WarehouseForm::class)
        ->assertDontSee('فعال')
        ->set('name', 'انبار بدون تیک فعال')
        ->call('save');

    $warehouse = Warehouse::query()->where('name', 'انبار بدون تیک فعال')->firstOrFail();
    expect($warehouse->is_active)->toBeTrue();
});

it('lets a holding_admin edit an existing warehouse, including toggling is_active', function () {
    [$user, $company] = inventoryActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $warehouse = Warehouse::create(['name' => 'انبار قدیمی', 'address' => 'آدرس قدیمی']);

    Livewire::test(WarehouseForm::class, ['warehouse' => $warehouse->id])
        ->assertSet('name', 'انبار قدیمی')
        ->assertSee('فعال')
        ->set('name', 'انبار جدید')
        ->set('address', 'آدرس جدید')
        ->set('is_active', false)
        ->call('save')
        ->assertRedirect(route('inventory.warehouses.index'));

    $warehouse->refresh();
    expect($warehouse->name)->toBe('انبار جدید')
        ->and($warehouse->address)->toBe('آدرس جدید')
        ->and($warehouse->is_active)->toBeFalse()
        ->and($warehouse->updated_by_user_id)->toBe($user->id);
});

it('lets a holding_admin toggle a warehouse active state from the index panel', function () {
    [$user, $company] = inventoryActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $warehouse = Warehouse::create(['name' => 'انبار فعال'])->fresh();
    expect($warehouse->is_active)->toBeTrue();

    Livewire::test(WarehouseIndex::class)
        ->call('toggleActive', $warehouse->id);

    expect($warehouse->fresh()->is_active)->toBeFalse();
});

it('rejects warehouse update for a non holding_admin at the Action level', function () {
    [$adminUser, $adminCompany] = inventoryActingAsWithRole('holding_admin');
    $this->actingAs($adminUser);
    session(['active_company_id' => $adminCompany->id]);

    $warehouse = Warehouse::create(['name' => 'انبار مشترک']);

    [$operatorUser, $operatorCompany] = inventoryActingAsWithRole('operator');

    expect(fn () => app(UpdateWarehouse::class)->handle(
        $warehouse,
        $operatorUser,
        ['name' => 'تغییر غیرمجاز']
    ))->toThrow(AuthorizationException::class);
});
