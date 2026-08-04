<?php

use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public $search = '';
    public $cart = [];
    public $notFoundMessage = '';

    public function mount()
    {
        if (auth()->user()->role !== 'owner') {
            redirect('/pos');
        }
    }
}; ?>

<div>
    <h1>Dashboard</h1>
</div>