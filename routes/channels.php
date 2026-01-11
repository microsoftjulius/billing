<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channels for real-time updates
Broadcast::channel('mikrotik-status', function () {
    return true; // Public channel - all authenticated users can listen
});

Broadcast::channel('payments', function () {
    return true; // Public channel - all authenticated users can listen
});

Broadcast::channel('vouchers', function () {
    return true; // Public channel - all authenticated users can listen
});

Broadcast::channel('system-notifications', function () {
    return true; // Public channel - all authenticated users can listen
});
