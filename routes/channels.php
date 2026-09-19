<?php

use App\Models\LabSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Lab session channel for diffs, leaderboard, and session state
Broadcast::channel('lab-session.{sessionId}', function ($user, $sessionId) {
    $session = LabSession::find($sessionId);
    if (!$session) {
        return false;
    }
    // Allow student owner, instructor/admin, or group teammates
    if ((int) $user->id === (int) $session->user_id || in_array($user->role, ['instructor', 'admin'])) {
        return true;
    }
    if ($session->group_id && LabSession::where('group_id', $session->group_id)->where('user_id', $user->id)->exists()) {
        return true;
    }
    return false;
});

// Ephemeral team chat channel (students only - instructor strictly excluded per specification)
Broadcast::channel('lab-session.{sessionId}.chat', function ($user, $sessionId) {
    $session = LabSession::find($sessionId);
    if (!$session) {
        return false;
    }
    if ((int) $user->id === (int) $session->user_id) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    if ($session->group_id && LabSession::where('group_id', $session->group_id)->where('user_id', $user->id)->exists()) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
});

// Instructor live monitoring channel
Broadcast::channel('instructor.monitoring.{sessionId}', function ($user, $sessionId) {
    return in_array($user->role, ['instructor', 'admin']);
});
