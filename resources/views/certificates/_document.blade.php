{{--
    The certificate face: an A4-landscape sheet that scales with its container (sizes in cqw),
    always printed on white. Expects $certificate and $record (Certificate::record()).
--}}
@php
    $class = $record['class'];
    $avg = $record['average_grade'];
@endphp
<div class="cert-sheet-wrap">
    <article class="cert-sheet" aria-label="Certificate of completion">
        <div class="cert-frame">
            <header class="cert-head">
                <div class="cert-brand">
                    <x-logo-mark class="cert-logo" />
                    <span>Certicode <span class="cert-brand-dim">Labs</span></span>
                </div>
                <span class="cert-code">No. {{ $certificate->verification_code }}</span>
            </header>

            <div class="cert-body">
                <p class="cert-eyebrow">Certificate of Completion</p>
                <p class="cert-lead">This certifies that</p>
                <h1 class="cert-name">{{ $certificate->user->name }}</h1>
                <p class="cert-lead">has completed the course</p>
                <h2 class="cert-class">{{ $class->name }}</h2>
                <p class="cert-statement">
                    completing {{ $record['labs_completed'] }} of {{ $record['labs_total'] }} graded coding labs in proctored, camera-verified sessions,
                    meeting the course pass mark of {{ $record['pass_mark'] }}%.
                </p>

                <dl class="cert-stats">
                    <div><dt>Average grade</dt><dd>{{ $avg === null ? '—' : rtrim(rtrim(number_format($avg, 1), '0'), '.') . '%' }}</dd></div>
                    <div><dt>Labs completed</dt><dd>{{ $record['labs_completed'] }}/{{ $record['labs_total'] }}</dd></div>
                    <div><dt>Competencies</dt><dd>{{ count($record['competencies']) }}</dd></div>
                    <div><dt>Time in labs</dt><dd>{{ $record['minutes'] >= 60 ? intdiv($record['minutes'], 60) . 'h ' . ($record['minutes'] % 60) . 'm' : $record['minutes'] . 'm' }}</dd></div>
                </dl>
            </div>

            <footer class="cert-foot">
                <div class="cert-sign">
                    <span class="cert-sign-name">{{ $class->instructor->name ?? 'Course instructor' }}</span>
                    <span class="cert-sign-line"></span>
                    <span class="cert-sign-role">Course instructor</span>
                </div>
                <div class="cert-sign">
                    <span class="cert-sign-name">{{ $certificate->issued_at->format('F j, Y') }}</span>
                    <span class="cert-sign-line"></span>
                    <span class="cert-sign-role">Date issued</span>
                </div>
                <div class="cert-qr">
                    <div class="cert-qr-code" aria-hidden="true">{!! $certificate->qrSvg(240) !!}</div>
                    <div class="cert-qr-text">
                        <span class="cert-qr-title">Scan to verify</span>
                        <span class="cert-qr-url">{{ preg_replace('#^https?://#', '', $certificate->verificationUrl()) }}</span>
                    </div>
                </div>
            </footer>
        </div>
    </article>
</div>
