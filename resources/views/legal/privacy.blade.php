@extends('layouts.public')

@section('title', 'Privacy Policy')
@section('robots', 'index, follow')
@section('headerLabel', 'Legal')

@include('legal._styles')

@section('content')
<article class="legal">
    <h1>Privacy Policy</h1>
    <p class="legal-meta">Effective {{ \Illuminate\Support\Carbon::parse(config('legal.effective_date'))->format('F j, Y') }}</p>

    <p>Certicode Labs is a platform for programming labs. Students code in VS Code, and instructors see how the work was done. That means we handle more about students than most apps do, including camera checks and typing activity. This policy explains what we collect, why, who sees it, and the choices you have. It follows the rights and duties in the Philippine Data Privacy Act of 2012 (Republic Act No. 10173).</p>

    <h2 id="who">1. Who is responsible for your data</h2>
    <p>Certicode Labs is run by <strong>{{ config('legal.operator_name') }}</strong>, who is the personal information controller and acts as the data protection officer. Contact: <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>@if (config('legal.contact_address')), {{ config('legal.contact_address') }}@endif.</p>
    <p>When a school or instructor uses Certicode for a class, they decide how the class runs and what they do with its results. They may have their own duties to you under their own privacy notice.</p>

    <h2 id="collect">2. What we collect</h2>
    <div class="legal-table">
        <table>
            <thead><tr><th>Type</th><th>What it includes</th></tr></thead>
            <tbody>
                <tr><td>Account</td><td>Name, username, email, password (stored only as a hash), role, gender if you add it, timezone and notification settings, and your linked Gmail or GitHub username.</td></tr>
                <tr><td>Class records</td><td>Classes you teach or joined, teams, lab attempts, scores, competencies, instructor feedback and certificates.</td></tr>
                <tr><td>Your work</td><td>Code you submit, code files you add or change in your lab folder during a lab, code run results, and any GitHub repository link you provide.</td></tr>
                <tr><td>Lab activity</td><td>Typing pace, editor focus changes, idle time, connection status, edit counts, pasted text and large blocks of code that weren't typed, and changes made to lab files outside the editor.</td></tr>
                <tr><td>Camera</td><td>Whether a face is present, how many faces, low-light results, and the snapshots described in section 3.</td></tr>
                <tr><td>Messages</td><td>Your messages in team lab chat.</td></tr>
                <tr><td>Technical</td><td>IP address and browser details for your signed-in sessions, plus the cookies listed in our <a href="{{ route('legal.cookies') }}">Cookie Notice</a>.</td></tr>
            </tbody>
        </table>
    </div>
    <p>Grades and other education records are treated as sensitive information in this policy, and we protect them accordingly. We don't sell personal data, show ads, or use advertising trackers.</p>

    <h2 id="camera">3. Camera and activity monitoring</h2>
    <p>Before a lab starts, we ask for your consent to monitoring. Then your browser runs a camera check, and it keeps checking while the lab is open.</p>
    <ul>
        <li><strong>Face detection runs on your device.</strong> It looks for whether a face is present and how many. It does not identify you, and we don't build or store a face template. It doesn't estimate your age, gender or emotions.</li>
        <li><strong>The camera feed isn't recorded.</strong> We don't store video or audio.</li>
        <li><strong>A passing check isn't kept.</strong> A frame is sent to our server to confirm the check, and it isn't stored.</li>
        <li><strong>Snapshots are kept when the camera raises a flag.</strong> If no face, several faces, or too little light is detected, a still picture may be saved so your instructor can review it. Pictures from a dim room can include a brightened copy. An attempt keeps up to 60 pictures of each flag type.</li>
    </ul>
    <p>Flags don't change your grade by themselves. They point an instructor to something to review, and instructors can clear them. The camera can make mistakes, so a flag isn't proof of cheating.</p>
    <p>Monitoring also covers the activity listed in section 2. It runs through the Certicode VS Code extension during a lab, and only for your lab session. The extension doesn't watch your computer outside the lab.</p>

    <h2 id="purposes">4. Why we use your data, and our legal basis</h2>
    <div class="legal-table">
        <table>
            <thead><tr><th>Purpose</th><th>Basis</th></tr></thead>
            <tbody>
                <tr><td>Create your account, run classes and labs, send notifications</td><td>Providing the service you signed up for</td></tr>
                <tr><td>Grade work, give feedback, issue and verify certificates</td><td>Providing the service; your consent for education records</td></tr>
                <tr><td>Camera checks and lab activity monitoring for academic integrity</td><td>Your consent, given before your first lab</td></tr>
                <tr><td>Compare submissions in a lab to detect copied code</td><td>Legitimate interest in fair assessment; your consent for education records</td></tr>
                <tr><td>Keep the service secure, prevent abuse, fix problems</td><td>Legitimate interest</td></tr>
                <tr><td>Meet legal duties</td><td>Legal obligation</td></tr>
            </tbody>
        </table>
    </div>

    <h2 id="ai">5. AI grading</h2>
    <p>When a lab is graded, your code is sent to an AI model along with the lab's tasks and rubric. It produces a score, feedback and a summary for your instructor. We send your code, file names and editor diagnostics. We don't send your name or email, but anything you type into your code goes with it, so don't put personal information in your code.</p>
    <p>AI grades can be wrong. Your instructor can see the reasoning and override the grade. If you think a grade is wrong, ask your instructor to review it. We don't use your work to train AI models, and our provider's commercial terms cover how it handles what we send.</p>

    <h2 id="sharing">6. Who can see your data</h2>
    <ul>
        <li><strong>Your instructor</strong> sees your work, grades, lab activity, flags and snapshots for their classes. Certicode administrators can open these too.</li>
        <li><strong>Teammates</strong> in a group lab see the shared lab and team chat. Instructors' monitoring and grading views don't show chat.</li>
        <li><strong>Certicode administrators</strong> can access records to run and support the service.</li>
        <li><strong>Anyone with a certificate's verification code</strong> can see the name on it, the class and the issue date.</li>
    </ul>
    <p>These service providers process data for us:</p>
    <div class="legal-table">
        <table>
            <thead><tr><th>Provider</th><th>What for</th><th>Location</th></tr></thead>
            <tbody>
                <tr><td>Vercel</td><td>Hosting the application</td><td>South Korea (Seoul region); Vercel is a US company</td></tr>
                <tr><td>Supabase</td><td>Database and file storage</td><td>South Korea (Seoul region); Supabase is a US company</td></tr>
                <tr><td>Anthropic</td><td>AI grading (code only, see section 5)</td><td>United States</td></tr>
                <tr><td>Pusher</td><td>Live updates on open pages</td><td>Depends on the configured cluster</td></tr>
                <tr><td>Google</td><td>Email delivery, Google sign-in if you use it, and web fonts (your browser sends Google its IP address when it loads them)</td><td>United States and other countries</td></tr>
                <tr><td>GitHub</td><td>GitHub sign-in if you use it, and contribution stats for a repository you link</td><td>United States</td></tr>
                <tr><td>Judge0 code runner, when enabled</td><td>Compiling and running submitted code</td><td>Depends on the server used</td></tr>
            </tbody>
        </table>
    </div>
    <p>We may also disclose data when the law requires it, such as a lawful order from a court or regulator.</p>

    <h2 id="transfers">7. Data stored outside the Philippines</h2>
    <p>Our servers and providers are outside the Philippines, as listed above. By using Certicode, your data will be processed in those countries. We stay accountable for it under Philippine law wherever it is processed.</p>

    <h2 id="retention">8. How long we keep it</h2>
    <div class="legal-table">
        <table>
            <thead><tr><th>Data</th><th>Kept for</th></tr></thead>
            <tbody>
                <tr><td>Connection heartbeats and typing-speed samples</td><td>{{ config('app.telemetry_retention_days') }} days</td></tr>
                <tr><td>Camera snapshots</td><td>{{ config('app.snapshot_retention_days') }} days, whether or not the flag was reviewed</td></tr>
                <tr><td>Resolved integrity flags</td><td>{{ config('app.anomaly_retention_days') }} days. Open flags stay until reviewed, without their snapshot after the period above.</td></tr>
                <tr><td>Account, class records, submitted work, other lab activity and chat</td><td>Until you delete your account or an admin removes it</td></tr>
                <tr><td>Certificates</td><td>Kept with your name and grades after account deletion so they keep verifying (section 9)</td></tr>
            </tbody>
        </table>
    </div>

    <h2 id="rights">9. Your rights</h2>
    <p>Under the Data Privacy Act you have the right to:</p>
    <ul>
        <li>be informed about how your data is processed</li>
        <li>access your data, and get a copy in a portable format</li>
        <li>correct data that is wrong</li>
        <li>object to processing, and withdraw consent</li>
        <li>have your data blocked or erased, within the limits in the law</li>
        <li>claim damages for harm caused by misuse of your data</li>
        <li>complain to the <a href="https://privacy.gov.ph" rel="noopener">National Privacy Commission</a></li>
    </ul>
    <p>You can do much of this yourself in <strong>Settings</strong>:</p>
    <ul>
        <li><strong>Download my data</strong> gives you a JSON file. Camera snapshots are listed but not included. Email us for a copy of those.</li>
        <li><strong>Delete account</strong> erases your account, lab work and monitoring data. If you hold a certificate or took a group lab, your sign-in, contact details and monitoring data are erased, and your name and grades stay so certificates keep verifying and teammates keep their group lab.</li>
        <li><strong>Withdraw monitoring consent</strong> applies from your next lab.</li>
    </ul>
    <p>For anything else, email <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>. We may need to confirm who you are first.</p>

    <h2 id="refusing">10. If you don't agree to monitoring</h2>
    <p>Labs can't start in Certicode without camera and activity monitoring. Saying no won't affect your account, but you won't be able to start labs. If you can't or don't want to use a camera, talk to your instructor or school before the lab about another way to be assessed. That decision is theirs.</p>

    <h2 id="minors">11. Students under 18</h2>
    <p>You must be 18 or older to sign up on your own. A student under 18 may use Certicode only if their school enrolled them and has consent from their parent or guardian. Schools and instructors are responsible for getting that consent. If you are a parent or guardian and believe a minor is using Certicode without it, email us and we will act on it.</p>

    <h2 id="security">12. Security</h2>
    <p>We encrypt traffic in transit, store passwords as hashes, keep camera snapshots out of public storage, and show them only to the class's instructor and Certicode administrators. No system is perfectly secure. If a breach puts your data at real risk, we will notify you and the National Privacy Commission as the law requires.</p>

    <h2 id="changes">13. Changes to this policy</h2>
    <p>If we make a change that affects what you agreed to, we will ask you to accept the new version when you next sign in. The date at the top shows when this version took effect.</p>
</article>
@endsection
