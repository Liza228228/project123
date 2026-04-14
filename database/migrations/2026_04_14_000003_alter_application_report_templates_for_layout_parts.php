<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_report_templates', function (Blueprint $table) {
            $table->foreignId('report_header_id')->nullable()->after('id')->constrained('application_report_headers')->nullOnDelete();
            $table->foreignId('report_footer_id')->nullable()->after('report_header_id')->constrained('application_report_footers')->nullOnDelete();
            $table->string('main_font_family', 120)->default('Times New Roman, Times, serif')->after('main_body_text');
            $table->string('table_font_family', 120)->default('Times New Roman, Times, serif')->after('main_font_family');
        });

        Schema::table('application_report_templates', function (Blueprint $table) {
            $table->dropColumn(['header_text']);
        });
    }

    public function down(): void
    {
        Schema::table('application_report_templates', function (Blueprint $table) {
            $table->text('header_text')->nullable();
        });

        Schema::table('application_report_templates', function (Blueprint $table) {
            $table->dropForeign(['report_header_id']);
            $table->dropForeign(['report_footer_id']);
            $table->dropColumn(['report_header_id', 'report_footer_id', 'main_font_family', 'table_font_family']);
        });
    }
};
