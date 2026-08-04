<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect('/login');
});


Volt::route('/login', 'auth.login')->name('login');
Volt::route('/products', 'products.index')->name('products.index');
Volt::route('/products/create', 'products.create');

Volt::route('/pos', 'pos.index')->name('pos');

Volt::route('dashboard', 'dashboard.index')->name('dashboard');