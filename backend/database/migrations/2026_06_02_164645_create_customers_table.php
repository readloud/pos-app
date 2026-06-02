<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create("customers", function (Blueprint $table) {
            $table->id();
            $table->string("code")->unique();
            $table->string("name");
            $table->string("email")->nullable();
            $table->string("phone", 20);
            $table->text("address")->nullable();
            $table->decimal("credit_limit", 15, 2)->default(0);
            $table->integer("credit_days")->default(0);
            $table->boolean("is_active")->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists("customers");
    }
};
