<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Frota ESP32 (posições ao vivo) — qualquer usuário autenticado.
Broadcast::channel('esp32-fleet', fn($user) => $user !== null);

// Eventos de rastreamento / SOS ao vivo — qualquer usuário autenticado.
Broadcast::channel('rastreamento', fn($user) => $user !== null);
