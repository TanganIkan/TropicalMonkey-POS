<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('nota_number');
        });
    }

    public function down()
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('nota_number')->nullable();
        });
    }
};