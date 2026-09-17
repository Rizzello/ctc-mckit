<?php

namespace App\Livewire\Admin\Users;

use App\Actions\DisableUser;
use App\Actions\UpdateUser;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class EditUser extends Component
{
    public User $user;

    public string $name = '';

    public string $email = '';

    public bool $isAdmin = false;

    public bool $enabled = true;

    public function mount(User $user): void
    {
        $this->authorize('update', $user);
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->isAdmin = $user->is_admin;
        $this->enabled = $user->enabled;
    }

    public function save(UpdateUser $action): void
    {
        try {
            $action->handle($this->currentUser(), $this->user, $this->name, $this->email, $this->isAdmin, $this->enabled);
            $this->redirectRoute('admin.users.index', navigate: true);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
    }

    public function disable(DisableUser $action): void
    {
        try {
            $action->handle($this->currentUser(), $this->user);
            $this->enabled = false;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
    }

    public function render(): View
    {
        return view('livewire.admin.users.edit-user');
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
