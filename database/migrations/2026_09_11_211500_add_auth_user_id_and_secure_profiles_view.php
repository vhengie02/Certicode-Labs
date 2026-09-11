<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'auth_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('auth_user_id')->nullable()->unique()->after('gender');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            // 1. Create or replace public.profiles view with security_invoker = true
            DB::statement("
                CREATE OR REPLACE VIEW public.profiles
                WITH (security_invoker = true)
                AS
                SELECT 
                    id,
                    auth_user_id,
                    name,
                    first_name,
                    last_name,
                    username,
                    email,
                    email_verified_at,
                    role,
                    github_username,
                    gmail,
                    gmail_verified_at,
                    gender,
                    notify_class,
                    notify_module,
                    notify_lab,
                    notify_certificate,
                    notify_email_channel,
                    created_at,
                    updated_at
                FROM public.users;
            ");

            // 2. Grant permissions on public.profiles to authenticated and anon
            DB::statement("GRANT SELECT ON public.profiles TO authenticated, anon;");
            DB::statement("
                GRANT UPDATE (name, first_name, last_name, username, gender, github_username, notify_class, notify_module, notify_lab, notify_certificate, notify_email_channel) 
                ON public.profiles TO authenticated;
            ");

            // 3. Ensure sensitive columns on public.users are restricted from frontend roles
            DB::statement("REVOKE ALL ON TABLE public.users FROM anon, authenticated;");
            DB::statement("
                GRANT SELECT (
                    id, auth_user_id, name, first_name, last_name, username, email, email_verified_at, role,
                    github_username, gmail, gmail_verified_at, gender, notify_class, notify_module, notify_lab,
                    notify_certificate, notify_email_channel, created_at, updated_at
                ) ON TABLE public.users TO authenticated;
            ");
            DB::statement("
                GRANT UPDATE (
                    name, first_name, last_name, username, gender, github_username,
                    notify_class, notify_module, notify_lab, notify_certificate, notify_email_channel
                ) ON TABLE public.users TO authenticated;
            ");

            // 4. Backfill any existing users without auth_user_id into auth.users
            DB::statement("
                DO \$\$
                DECLARE
                    r RECORD;
                    new_auth_id uuid;
                    existing_auth_id uuid;
                BEGIN
                    FOR r IN SELECT * FROM public.users WHERE auth_user_id IS NULL LOOP
                        SELECT id INTO existing_auth_id FROM auth.users WHERE email = r.email LIMIT 1;

                        IF existing_auth_id IS NOT NULL THEN
                            UPDATE public.users SET auth_user_id = existing_auth_id WHERE id = r.id;
                        ELSE
                            new_auth_id := gen_random_uuid();
                            
                            INSERT INTO auth.users (
                                id, instance_id, aud, role, email, encrypted_password,
                                email_confirmed_at, raw_app_meta_data, raw_user_meta_data, created_at, updated_at
                            ) VALUES (
                                new_auth_id,
                                '00000000-0000-0000-0000-000000000000',
                                'authenticated',
                                'authenticated',
                                r.email,
                                COALESCE(r.password, ''),
                                COALESCE(r.email_verified_at, now()),
                                '{\"provider\":\"email\",\"providers\":[\"email\"]}'::jsonb,
                                jsonb_build_object('name', r.name, 'role', r.role),
                                COALESCE(r.created_at, now()),
                                COALESCE(r.updated_at, now())
                            );

                            INSERT INTO auth.identities (
                                id, user_id, identity_data, provider, provider_id, last_sign_in_at, created_at, updated_at
                            ) VALUES (
                                gen_random_uuid(),
                                new_auth_id,
                                jsonb_build_object('sub', new_auth_id::text, 'email', r.email),
                                'email',
                                new_auth_id::text,
                                now(),
                                COALESCE(r.created_at, now()),
                                COALESCE(r.updated_at, now())
                            );

                            UPDATE public.users SET auth_user_id = new_auth_id WHERE id = r.id;
                        END IF;
                    END LOOP;
                END \$\$;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP VIEW IF EXISTS public.profiles;");
        }

        if (Schema::hasColumn('users', 'auth_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('auth_user_id');
            });
        }
    }
};
