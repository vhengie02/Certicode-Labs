@extends('layouts.app')

@section('title', 'System')

@php
    $switches = [
        'registration_open' => ['New sign-ups', 'Let new people create accounts. Existing users can always sign in.'],
        'instructor_requests_open' => ['Instructor requests', 'Show the Instructor option at sign-up. When off, everyone signs up as a student.'],
    ];
@endphp

@section('content')
<div class="space-y-6">
    <x-page-header title="System" subtitle="Platform-wide settings. Changes apply immediately for everyone." />

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <form method="POST" action="{{ route('admin.system.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="ui-card" aria-labelledby="access-heading">
                <div class="ui-card-header">
                    <h2 id="access-heading" class="ui-card-title">Access</h2>
                    <p class="ui-card-subtitle">Who can join Certicode.</p>
                </div>
                <div class="ui-card-body divide-y divide-[#232323]">
                    @foreach ($switches as $key => [$label, $hint])
                        <label class="flex cursor-pointer items-center justify-between gap-4 py-3.5 first:pt-0 last:pb-0">
                            <span>
                                <span class="block text-sm font-medium text-[#ededed]">{{ $label }}</span>
                                <span class="mt-0.5 block text-[13px] text-[#888888]">{{ $hint }}</span>
                            </span>
                            <span class="relative shrink-0">
                                <input type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1" @checked($settings[$key]) class="sr-only peer">
                                <span class="block h-6 w-11 rounded-full border border-[#2e2e2e] bg-[#232323] transition-colors peer-checked:border-[#3ecf8e] peer-checked:bg-[#3ecf8e] peer-focus-visible:ring-2 peer-focus-visible:ring-[#3ecf8e]/50"></span>
                                <span class="absolute left-[3px] top-[3px] h-[18px] w-[18px] rounded-full bg-[#a3a3a3] transition-transform peer-checked:translate-x-5 peer-checked:bg-[#06150e]" aria-hidden="true"></span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="ui-card" aria-labelledby="defaults-heading">
                <div class="ui-card-header">
                    <h2 id="defaults-heading" class="ui-card-title">Defaults for new classes and labs</h2>
                    <p class="ui-card-subtitle">Instructors can change these per class or lab.</p>
                </div>
                <div class="ui-card-body grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="default_pass_mark" class="ui-label">Pass mark</label>
                        <div class="relative">
                            <input type="number" id="default_pass_mark" name="default_pass_mark" min="1" max="100" required value="{{ old('default_pass_mark', $settings['default_pass_mark']) }}" class="ui-input pr-9" @error('default_pass_mark') aria-invalid="true" @enderror>
                            <span class="absolute inset-y-0 right-3 flex items-center text-sm text-[#888888] pointer-events-none" aria-hidden="true">%</span>
                        </div>
                        <p class="ui-hint">Share of a class's labs needed for a certificate.</p>
                        @error('default_pass_mark') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="default_lab_minutes" class="ui-label">Time per student</label>
                        <div class="relative">
                            <input type="number" id="default_lab_minutes" name="default_lab_minutes" min="5" max="300" required value="{{ old('default_lab_minutes', $settings['default_lab_minutes']) }}" class="ui-input pr-14" @error('default_lab_minutes') aria-invalid="true" @enderror>
                            <span class="absolute inset-y-0 right-3 flex items-center text-sm text-[#888888] pointer-events-none" aria-hidden="true">min</span>
                        </div>
                        <p class="ui-hint">How long each student's lab session can run.</p>
                        @error('default_lab_minutes') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="ui-card" aria-labelledby="announce-heading">
                <div class="ui-card-header">
                    <h2 id="announce-heading" class="ui-card-title">Announcement</h2>
                    <p class="ui-card-subtitle">Shown as a banner at the top of every page for signed-in users. Leave empty to hide it.</p>
                </div>
                <div class="ui-card-body">
                    <label for="announcement" class="sr-only">Announcement</label>
                    <textarea id="announcement" name="announcement" rows="2" maxlength="300" placeholder="e.g. Certicode will be down for maintenance on Saturday 8–9 AM." class="ui-input">{{ old('announcement', $settings['announcement']) }}</textarea>
                    @error('announcement') <p class="ui-error">{{ $message }}</p> @enderror
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="ui-btn ui-btn-primary">Save settings</button>
            </div>
        </form>

        <aside class="space-y-6 lg:sticky lg:top-2">
            <section class="ui-card" aria-labelledby="services-heading">
                <div class="ui-card-header">
                    <h2 id="services-heading" class="ui-card-title">Services</h2>
                    <p class="ui-card-subtitle">Set through environment variables on the server. Secrets are never shown.</p>
                </div>
                <ul class="ui-card-body space-y-3" role="list">
                    @foreach ($services as [$name, $ok, $detail])
                        <li class="flex items-start gap-3">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $ok ? 'bg-[#3ecf8e]' : 'bg-amber-400' }}" aria-hidden="true"></span>
                            <span class="min-w-0">
                                <span class="block text-sm text-[#ededed]">{{ $name }} <span class="sr-only">{{ $ok ? 'configured' : 'not configured' }}</span></span>
                                <span class="block text-xs text-[#888888]">{{ $detail }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
            <section class="ui-card ui-card-body" aria-labelledby="env-heading">
                <h2 id="env-heading" class="ui-card-title">Environment</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach ($environment as $label => $value)
                        <div class="flex justify-between gap-4">
                            <dt class="text-[#888888]">{{ $label }}</dt>
                            <dd class="font-mono text-[13px] text-[#ededed] {{ $label === 'Debug mode' && $value === 'On' && app()->environment('production') ? '!text-red-400' : '' }}">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </aside>
    </div>
</div>
@endsection
