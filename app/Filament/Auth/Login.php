<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Override;

final class Login extends \Filament\Auth\Pages\Login
{
    #[Override]
    public function mount(): void
    {
        if (app()->isLocal()) {
            $this->form->fill([
                'email' => 'admin@example.com',
                'password' => 'password',
            ]);
        }
    }
}
