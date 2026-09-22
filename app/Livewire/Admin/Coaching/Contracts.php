<?php

namespace App\Livewire\Admin\Coaching;

use App\Models\{CoachingContract, CoachingOutbox};
use App\Services\Coaching\Access;
use Illuminate\Support\Facades\{Artisan, Gate};
use Livewire\Component;
use Livewire\WithPagination;

class Contracts extends Component
{
    use WithPagination;
    public string $search = '';

    private function authorizePage(): void { Gate::authorize('coaching.manage'); abort_unless(Access::available(), 404); }
    public function mount(): void { $this->authorizePage(); }
    public function updatedSearch(): void { $this->resetPage(); }

    private function scoped()
    {
        $query = CoachingContract::query();
        if (!auth()->user()->isAdmin()) $query->whereIn('institut_id', auth()->user()->persons()->pluck('institut_id'));
        return $query;
    }

    public function synchronize(): void
    {
        $this->authorizePage();
        $code = Artisan::call('coaching:sync');
        session()->flash('coaching_status', $code === 0 ? trim(Artisan::output()) : 'Abgleich fehlgeschlagen. API-Verbindung und Berechtigungen prüfen.');
    }

    public function retry(int $id): void
    {
        $this->authorizePage();
        $contract = $this->scoped()->findOrFail($id);
        CoachingOutbox::where('coaching_contract_id', $contract->id)->whereNull('sent_at')->update(['available_at' => now()]);
        $this->synchronize();
    }

    public function render()
    {
        $this->authorizePage();
        $contracts = $this->scoped()->with(['participant', 'tutor', 'latestPlan', 'course'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$this->search.'%')->orWhere('uvs_person_id', 'like', '%'.$this->search.'%')->orWhere('beratung_id', 'like', '%'.$this->search.'%')))
            ->orderByDesc('id')->paginate(25);
        return view('livewire.admin.coaching.contracts', [
            'contracts' => $contracts,
            'pending' => CoachingOutbox::whereIn('coaching_contract_id', $contracts->pluck('id'))->whereNull('sent_at')->get()->keyBy('coaching_contract_id'),
        ])->layout('layouts.master');
    }
}
