<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Opaque reference to zone.areas.id — NO FK (Constitution Law 1:
            // engine boundaries are sacred, no cross-schema FKs). Existence is
            // validated at write time via Zone\Domain\GeographyReader.
            // Nullable because seeded Super Admin + pre-claim shadow users
            // (WP-20260530) legitimately have no area; self-signup requires
            // it at the API layer.
            $table->uuid('area_id')->nullable()->after('phone_e164_last4');

            // Either a local-disk URL (LocalAvatarDriver) or a Cloudinary
            // delivery URL (CloudinaryAvatarDriver). Driver chosen by config
            // users.avatar.driver. See Identity Engine doc §8.
            $table->text('avatar_url')->nullable()->after('area_id');

            // Supports admin "list users by area" queries (Filament + future
            // hub-rollup) per engineering-standards §7.
            $table->index('area_id', 'users_area_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_area_id_index');
            $table->dropColumn(['area_id', 'avatar_url']);
        });
    }
};
