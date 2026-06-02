<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create("products", function (Blueprint $table) {
            $table->id();
            $table->string("sku")->unique();
            $table->string("barcode")->nullable()->unique();
            $table->string("name");
            $table->string("category");
            $table->decimal("purchase_price", 15, 2);
            $table->decimal("selling_price", 15, 2);
            $table->integer("min_stock")->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists("products");
    }
};
