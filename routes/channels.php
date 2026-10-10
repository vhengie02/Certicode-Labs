<?php

use App\Models\LabSession;
use App\Models\Laboratory;
use App\Models\Module;
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
    // The student, group teammates, an admin, or the instructor who owns the class
    return $session->isAccessibleBy($user);
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

// Instructor live monitoring channel: one per lab (anomalies, diffs and leaderboard for every student)
Broadcast::channel('instructor.lab.{labId}', function ($user, $labId) {
    $classId = Module::whereKey(Laboratory::whereKey($labId)->value('module_id'))->value('class_id');
    return $user->canManageClass($classId);
});
