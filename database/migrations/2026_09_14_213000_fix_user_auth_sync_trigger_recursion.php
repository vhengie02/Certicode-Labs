<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                CREATE OR REPLACE FUNCTION public.handle_public_user_auth_sync()
                RETURNS trigger
                LANGUAGE plpgsql
                SECURITY DEFINER
                AS \$function\$
                DECLARE
                    matching_auth_id uuid;
                BEGIN
                    IF NEW.auth_user_id IS NOT NULL THEN
                        RETURN NEW;
                    END IF;

                    SELECT id INTO matching_auth_id FROM auth.users WHERE email = NEW.email LIMIT 1;

                    IF matching_auth_id IS NOT NULL THEN
                        NEW.auth_user_id := matching_auth_id;
                    ELSE
                        matching_auth_id := gen_random_uuid();
                        
                        -- Set local transaction flag to prevent handle_new_auth_user from recurring
                        PERFORM set_config('app.syncing_user', 'true', true);

                        INSERT INTO auth.users (
                            id,
                            instance_id,
                            aud,
                            role,
                            email,
                            encrypted_password,
                            email_confirmed_at,
                            raw_app_meta_data,
                            raw_user_meta_data,
                            created_at,
                            updated_at
                        ) VALUES (
                            matching_auth_id,
                            '00000000-0000-0000-0000-000000000000',
                            'authenticated',
                            'authenticated',
                            NEW.email,
                            COALESCE(NEW.password, ''),
                            COALESCE(NEW.email_verified_at, now()),
                            '{\"provider\":\"email\",\"providers\":[\"email\"]}'::jsonb,
                            jsonb_build_object('name', NEW.name, 'role', NEW.role),
                            COALESCE(NEW.created_at, now()),
                            COALESCE(NEW.updated_at, now())
                        );

                        INSERT INTO auth.identities (
                            id,
                            user_id,
                            identity_data,
                            provider,
                            provider_id,
                            last_sign_in_at,
                            created_at,
                            updated_at
                        ) VALUES (
                            gen_random_uuid(),
                            matching_auth_id,
                            jsonb_build_object('sub', matching_auth_id::text, 'email', NEW.email),
                            'email',
                            matching_auth_id::text,
                            now(),
                            COALESCE(NEW.created_at, now()),
                            COALESCE(NEW.updated_at, now())
                        );

                        NEW.auth_user_id := matching_auth_id;
                    END IF;

                    RETURN NEW;
                END;
                \$function\$;
            ");

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
                            COALESCE(NEW.raw_user_meta_data->>'role', 'student'),
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback
    }
};
