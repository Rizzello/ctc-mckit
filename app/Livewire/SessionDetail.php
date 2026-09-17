<?php

namespace App\Livewire;

use App\Actions\AddSessionNote;
use App\Actions\AssignMcToConferenceSession;
use App\Actions\UnassignMcFromConferenceSession;
use App\Actions\UpdateConferenceSessionMcContent;
use App\Models\ConferenceSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SessionDetail extends Component
{
    public ConferenceSession $conferenceSession;

    public ?string $mcDescription = null;

    public ?string $mcScript = null;

    public string $noteBody = '';

    public ?int $assignUserId = null;

    public ?string $successMessage = null;

    public function mount(ConferenceSession $conferenceSession): void
    {
        $this->authorize('view', $conferenceSession);
        $this->conferenceSession = $conferenceSession;
        $this->mcDescription = $conferenceSession->mc_description;
        $this->mcScript = $conferenceSession->mc_script;
    }

    public function saveMcContent(UpdateConferenceSessionMcContent $action): void
    {
        try {
            $action->handle($this->currentUser(), $this->conferenceSession, $this->mcDescription, $this->mcScript);
            $this->successMessage = 'MC content saved.';
        } catch (ValidationException $exception) {
            $this->setValidationErrors($exception);
        }
    }

    public function addNote(AddSessionNote $action): void
    {
        try {
            $action->handle($this->currentUser(), $this->conferenceSession, $this->noteBody);
            $this->noteBody = '';
            $this->successMessage = 'Note added.';
            $this->dispatch('note-added');
        } catch (ValidationException $exception) {
            $this->setValidationErrors($exception);
        }
    }

    public function assignMc(AssignMcToConferenceSession $action): void
    {
        if ($this->assignUserId === null) {
            $this->addError('assignUserId', 'Choose a user to assign.');

            return;
        }

        try {
            $mc = User::query()->where('enabled', true)->findOrFail($this->assignUserId);
            $action->handle($this->currentUser(), $this->conferenceSession, $mc);
            $this->assignUserId = null;
            $this->successMessage = 'MC assigned.';
        } catch (ValidationException $exception) {
            $this->setValidationErrors($exception);
        }
    }

    public function unassignMc(int $userId, UnassignMcFromConferenceSession $action): void
    {
        $mc = User::query()->findOrFail($userId);
        $action->handle($this->currentUser(), $this->conferenceSession, $mc);
        $this->successMessage = 'MC removed.';
    }

    public function render(): View
    {
        $conferenceSession = $this->conferenceSession->fresh(['room', 'speakers', 'mcs', 'notes']);

        abort_unless($conferenceSession instanceof ConferenceSession, 404);

        return view('livewire.session-detail', [
            'conferenceSession' => $conferenceSession,
            'assignableUsers' => User::query()->where('enabled', true)->orderBy('name')->get(),
        ]);
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function setValidationErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $this->addError($field, $messages[0]);
        }
    }
}
