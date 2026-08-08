<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect('/login');
});


Volt::route('/login', 'auth.login')->name('login');
Volt::route('/products', 'products.index')->name('products.index');
Volt::route('/products/create', 'products.create');
Volt::route('/products/{product}/edit', 'products.edit')->middleware('auth')->name('products.edit');

Volt::route('/pos', 'pos.index')->name('pos');
Volt::route('/pos/checkout', 'pos.checkout')->middleware('auth')->name('pos.checkout');
Volt::route('/pos/receipt/{transaction}', 'pos.receipt')->middleware('auth')->name('pos.receipt');

Volt::route('/categories-brands', 'categories-brands.index')->name('categories-brands.index');

Volt::route('/products/{product}/add-stock', 'products.add-stock')->name('products.add-stock');

Volt::route('/dashboard', 'dashboard.index')->name('dashboard')->middleware('auth');

Volt::route('/staff', 'staff.index')->name('staff.index')->middleware('auth');

Volt::route('/profile', 'profile.index')->middleware('auth')->name('profile');