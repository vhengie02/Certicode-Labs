<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * handle_new_auth_user (the on_auth_user_created trigger on auth.users) created the Laravel
 * user with the role from raw_user_meta_data, which whoever signs up through Supabase Auth
 * chooses. Anyone with the project URL and anon key could sign up as an admin. New users from
 * this path are always students; staff roles are granted in the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION public.handle_new_auth_user()
            RETURNS trigger
            LANGUAGE plpgsql
            SECURITY DEFINER
            AS \$function\$
            BEGIN
                -- If triggered from handle_public_user_auth_sync in the same transaction, exit early
                IF current_setting('app.syncing_user', true) = 'true' THEN
                    RETURN NEW;
                END IF;

                UPDATE public.users
                SET auth_user_id = NEW.id
                WHERE email = NEW.email AND (auth_user_id IS NULL OR auth_user_id != NEW.id);

                IF NOT FOUND THEN
                    INSERT INTO public.users (
                        name,
                        email,
                        password,
                        role,
                        auth_user_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        COALESCE(NEW.raw_user_meta_data->>'name', split_part(NEW.email, '@', 1)),
                        NEW.email,
                        COALESCE(NEW.encrypted_password, ''),
                        -- Never from signup metadata: the person signing up controls it
                        'student',
                        NEW.id,
                        NEW.created_at,
                        NEW.updated_at
                    )
                    ON CONFLICT (email) DO UPDATE
                    SET auth_user_id = EXCLUDED.auth_user_id;
                END IF;
                RETURN NEW;
            END;
            \$function\$;
        ");
    }

    public function down(): void
    {
        // No rollback: the previous version let signups choose their own role
    }
};
