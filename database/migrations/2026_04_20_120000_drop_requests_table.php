<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('requests');
    }

    public function down(): void
    {
        // Восстановление таблицы не выполняется: журнал заявок по макету удалён из продукта.
    }
};
