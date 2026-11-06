<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guarantors', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
            $table->renameColumn('mobile', 'mobile_number');
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->string('email')->nullable()->after('alternate_mobile');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('pincode', 6)->nullable()->after('state');
            $table->string('relationship', 30)->nullable()->after('pincode');
            $table->string('custom_relationship', 100)->nullable()->after('relationship');
            $table->string('occupation', 100)->nullable()->after('custom_relationship');
            $table->string('identity_document_type', 30)->nullable()->after('occupation');
            $table->text('identity_document_number')->nullable()->after('identity_document_type');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guarantors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'email',
                'city',
                'state',
                'pincode',
                'relationship',
                'custom_relationship',
                'occupation',
                'identity_document_type',
                'identity_document_number',
            ]);
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->renameColumn('full_name', 'name');
            $table->renameColumn('mobile_number', 'mobile');
        });
    }
};
