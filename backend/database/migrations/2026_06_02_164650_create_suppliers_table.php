<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create("suppliers", function (Blueprint $table) {
            $table->id();
            $table->string("code")->unique();
            $table->string("name");
            $table->string("email")->nullable();
            $table->string("phone", 20);
            $table->text("address")->nullable();
            $table->string("tax_number")->nullable();
            $table->boolean("is_active")->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists("suppliers");
    }
};
