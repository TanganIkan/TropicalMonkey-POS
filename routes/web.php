<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect('/login');
});

// Guest only — kalau sudah login, gak boleh akses ini lagi
Volt::route('/login', 'auth.login')->middleware('guest')->name('login');

Route::middleware('auth')->group(function () {
    // Bisa diakses staff maupun owner
    Volt::route('/pos', 'pos.index')->name('pos');
    Volt::route('/pos/checkout', 'pos.checkout')->name('pos.checkout');
    Volt::route('/pos/receipt/{transaction}', 'pos.receipt')->name('pos.receipt');

    Volt::route('/products', 'products.index')->name('products.index');
    Volt::route('/products/create', 'products.create')->name('products.create');
    Volt::route('/products/{product}/edit', 'products.edit')->name('products.edit');
    Volt::route('/products/{product}/add-stock', 'products.add-stock')->name('products.add-stock');

    Volt::route('/categories-brands', 'categories-brands.index')->name('categories-brands.index');

    Volt::route('/profile', 'profile.index')->name('profile');

    // Khusus owner
    Route::middleware('role:owner')->group(function () {
        Volt::route('/dashboard', 'dashboard.index')->name('dashboard');
        Volt::route('/staff', 'staff.index')->name('staff.index');
        Volt::route('/products/top', 'products.top')->name('products.top');
        Volt::route('/transactions', 'transactions.index')->name('transactions.index');
    });
});