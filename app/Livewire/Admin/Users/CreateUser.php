<?php

namespace App\Livewire\Admin\Users;

use App\Actions\CreateUser as CreateUserAction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CreateUser extends Component
{
    public string $name = '';

    public string $email = '';

    public bool $isAdmin = false;

    public bool $enabled = true;

    public function mount(): void
    {
        $this->authorize('create', User::class);
    }

    public function save(CreateUserAction $action): void
    {
        try {
            $action->handle($this->currentUser(), $this->name, $this->email, $this->isAdmin, $this->enabled);
            session()->flash('success', 'User created.');
            $this->redirectRoute('admin.users.index', navigate: true);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
    }

    public function render(): View
    {
        return view('livewire.admin.users.create-user');
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
