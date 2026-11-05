<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Induk Controller bawaan Laravel 11
    //
    // Trait AuthorizesRequests ditambahkan agar $this->authorize(...) dapat
    // dipakai di controller (dipakai oleh ComplaintController untuk RBAC).
    // Bersifat ADDITIF: tidak mengubah perilaku controller lain yang sudah ada.
    use AuthorizesRequests;
}
