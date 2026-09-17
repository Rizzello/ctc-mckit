<?php

namespace App\Livewire;

use App\Actions\AddSessionNote;
use App\Actions\AssignMcToConferenceSession;
use App\Actions\DeleteSessionNote;
use App\Actions\UnassignMcFromConferenceSession;
use App\Actions\UpdateConferenceSessionMcContent;
use App\Actions\UpdateSessionNote;
use App\Models\ConferenceSession;
use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SessionDetail extends Component
{
    public ConferenceSession $conferenceSession;

    public ?string $mcDescription = null;

    public ?string $mcScript = null;

    public string $noteBody = '';

    public ?int $editingNoteId = null;

    public string $editingNoteBody = '';

    public ?int $assignUserId = null;

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
            $this->dispatch('toast', type: 'success', message: 'Session preparation saved.');
        } catch (ValidationException $exception) {
            $this->setValidationErrors($exception);
        }
    }

    public function addNote(AddSessionNote $action): void
    {
        try {
            $action->handle($this->currentUser(), $this->conferenceSession, $this->noteBody);
            $this->noteBody = '';
            $this->dispatch('note-added');
            $this->dispatch('toast', type: 'success', message: 'Note added.');
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
            $this->dispatch('mc-assigned');
            $this->dispatch('toast', type: 'success', message: 'MC assigned.');
        } catch (ValidationException $exception) {
            $this->setValidationErrors($exception);
        }
    }

    public function unassignMc(int $userId, UnassignMcFromConferenceSession $action): void
    {
        $mc = User::query()->findOrFail($userId);
        $action->handle($this->currentUser(), $this->conferenceSession, $mc);
        $this->dispatch('mc-unassigned');
        $this->dispatch('toast', type: 'success', message: 'MC assignment removed.');
    }

    public function editNote(int $noteId): void
    {
        $note = $this->findNote($noteId);
        Gate::forUser($this->currentUser())->authorize('update', $note);

        $this->editingNoteId = $note->id;
        $this->editingNoteBody = $note->body;
        $this->resetValidation('editingNoteBody');
    }

    public function updateNote(UpdateSessionNote $action): void
    {
        if ($this->editingNoteId === null) {
            return;
        }

        try {
            $action->handle($this->currentUser(), $this->findNote($this->editingNoteId), $this->editingNoteBody);
            $this->editingNoteId = null;
            $this->editingNoteBody = '';
            $this->resetValidation('editingNoteBody');
            $this->dispatch('note-updated');
            $this->dispatch('toast', type: 'success', message: 'Note updated.');
        } catch (ValidationException $exception) {
            $this->setValidationErrors($exception, 'editingNoteBody');
        }
    }

    public function deleteNote(int $noteId, DeleteSessionNote $action): void
    {
        $action->handle($this->currentUser(), $this->findNote($noteId));
        $this->dispatch('toast', type: 'success', message: 'Note deleted.');
    }

    public function render(): View
    {
        $currentUser = $this->currentUser();
        $conferenceSession = $this->conferenceSession->fresh(['room', 'speakers', 'mcs', 'notes']);

        abort_unless($conferenceSession instanceof ConferenceSession, 404);

        return view('livewire.session-detail', [
            'conferenceSession' => $conferenceSession,
            'assignableUsers' => $currentUser->is_admin
                ? User::query()
                    ->where('enabled', true)
                    ->whereNotIn('id', $conferenceSession->mcs->modelKeys())
                    ->orderBy('name')
                    ->get()
                : collect(),
        ]);
    }

    private function findNote(int $noteId): SessionNote
    {
        return SessionNote::query()
            ->where('conference_session_id', $this->conferenceSession->getKey())
            ->findOrFail($noteId);
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function setValidationErrors(ValidationException $exception, string $field = 'body'): void
    {
        foreach ($exception->errors() as $errorField => $messages) {
            $this->addError($field === 'body' ? $errorField : $field, $messages[0]);
        }
    }
}
