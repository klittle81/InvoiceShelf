<?php

use App\Http\Controllers\V1\Admin\Users\UsersController;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

use function Pest\Faker\fake;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::where('role', 'super admin')->first();

    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);

    Sanctum::actingAs(
        $user,
        ['*']
    );
});

getJson('/api/v1/users')->assertOk();

test('store user using a form request', function () {
    $this->assertActionUsesFormRequest(
        UsersController::class,
        'store',
        UserRequest::class
    );
});

// test('store user', function () {
//     $data = [
//         'name' => fake()->name,
//         'email' => fake()->unique()->safeEmail,
//         'phone' => fake()->phoneNumber,
//         'password' => fake()->password
//     ];

//     postJson('/api/v1/users', $data)->assertOk();

//     $this->assertDatabaseHas('users', [
//         'name' => $data['name'],
//         'email' => $data['email'],
//         'phone' => $data['phone'],
//     ]);
// });

test('get user', function () {
    $user = User::factory()->create();

    getJson("/api/v1/users/{$user->id}")->assertOk();
});

test('update user using a form request', function () {
    $this->assertActionUsesFormRequest(
        UsersController::class,
        'update',
        UserRequest::class
    );
});

test('company setup roles includes company owner role', function () {
    $company = User::where('role', 'super admin')->first()->companies()->first();

    $company->setupRoles();

    $this->assertDatabaseHas('roles', [
        'name' => 'company owner',
        'scope' => $company->id,
    ]);
});

test('user assigned company owner role is treated as owner', function () {
    $company = User::where('role', 'super admin')->first()->companies()->first();

    $company->setupRoles();

    $user = User::factory()->create();
    $user->companies()->attach($company->id);

    $role = Role::where('name', 'company owner')
        ->where('scope', $company->id)
        ->first();

    BouncerFacade::scope()->to($company->id);
    BouncerFacade::sync($user)->roles([$role]);

    request()->headers->set('company', $company->id);

    $user = $user->fresh();

    $this->assertTrue($user->isOwner());
});

test('company owner can update company notification settings', function () {
    $company = User::where('role', 'super admin')->first()->companies()->first();

    $company->setupRoles();

    $user = User::factory()->create();
    $user->companies()->attach($company->id);

    $role = Role::where('name', 'company owner')
        ->where('scope', $company->id)
        ->first();

    BouncerFacade::scope()->to($company->id);
    BouncerFacade::sync($user)->roles([$role]);

    Sanctum::actingAs($user, ['*']);

    $this->withHeaders(['company' => $company->id])
        ->postJson('/api/v1/company/settings', [
            'settings' => [
                'notification_email' => 'owner@example.com',
            ],
        ])
        ->assertOk();

    $this->assertDatabaseHas('company_settings', [
        'company_id' => $company->id,
        'option' => 'notification_email',
        'value' => 'owner@example.com',
    ]);
});

// test('update user', function () {
//     $user = User::factory()->create();

//     $data = [
//         'name' => fake()->name,
//         'email' => fake()->unique()->safeEmail,
//         'phone' => fake()->phoneNumber,
//         'password' => fake()->password
//     ];

//     putJson("/api/v1/users/{$user->id}", $data)->assertOk();

//     $this->assertDatabaseHas('users', [
//         'name' => $data['name'],
//         'email' => $data['email'],
//         'phone' => $data['phone'],
//     ]);
// });

// test('delete users', function () {
//     $user = User::factory()->create();
//     $data['users'] = [$user->id];

//     postJson("/api/v1/users/delete", $data)
//         ->assertOk();

//     $this->assertModelMissing($user);
// });
