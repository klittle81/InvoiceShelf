<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Silber\Bouncer\Database\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            $company->setupRoles();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::where('name', 'company owner')->get()->each->delete();
    }
};
