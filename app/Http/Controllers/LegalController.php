<?php

namespace App\Http\Controllers;

use App\Models\LabSession;
use Illuminate\Http\Request;

class LegalController extends Controller
{
    public function privacy()
    {
        return view('legal.privacy');
    }

    public function terms()
    {
        return view('legal.terms');
    }

    public function cookies()
    {
        return view('legal.cookies');
    }

    /**
     * The page that asks a signed-in user to accept the current Terms and Privacy Policy.
     */
    public function showAcceptance(Request $request)
    {
        if ($request->user()->hasAcceptedTerms()) {
            return redirect()->intended('/dashboard');
        }

        return view('legal.accept');
    }

    public function accept(Request $request)
    {
        $request->validate(['terms' => 'accepted'], [
            'terms.accepted' => 'Please confirm you agree before continuing.',
        ]);

        $request->user()->acceptTerms();

        return redirect()->intended('/dashboard');
    }

    /**
     * Agree to camera and activity monitoring. The lab page asks before it opens the camera.
     */
    public function grantMonitoring(Request $request)
    {
        $request->user()->grantMonitoringConsent();

        return response()->json(['status' => 'success']);
    }

    /**
     * Withdraw that agreement. It applies from the next lab, so a lab that is still open has to
     * end first: the camera and activity checks are part of that attempt.
     */
    public function withdrawMonitoring(Request $request)
    {
        $user = $request->user();

        if (LabSession::where('user_id', $user->id)->where('status', 'in_progress')->exists()) {
            return back()->with('error', 'You have a lab in progress. Submit it or let it end, then withdraw your consent.');
        }

        $user->withdrawMonitoringConsent();

        return back()->with('success', "Consent withdrawn. You'll be asked again before your next lab, and labs can't start without it.");
    }
}
