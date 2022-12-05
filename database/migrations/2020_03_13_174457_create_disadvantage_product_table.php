<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDisadvantageProductTable extends Migration
{
    public function up()
    {
        Schema::create('disadvantage_product', function (Blueprint $table) {
            $table->unsignedInteger('disadvantage_id');
            $table->unsignedBigInteger('product_id');

            $table
                ->foreign('disadvantage_id')
                ->references('id')
                ->on('disadvantages');

            $table
                ->foreign('product_id')
                ->references('id')
                ->on('products');
        });
    }

    public function down()
    {
        Schema::dropIfExists('disadvantage_product');
    }
}
