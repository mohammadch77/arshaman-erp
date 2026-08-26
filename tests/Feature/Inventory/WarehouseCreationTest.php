<?php

use App\Livewire\Inventory\WarehouseForm;
use App\Livewire\Inventory\WarehouseIndex;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Actions\CreateWarehouse;
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
