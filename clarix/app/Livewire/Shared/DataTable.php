<?php

namespace App\Livewire\Shared;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

abstract class DataTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortColumn = 'id';
    public string $sortDirection = 'asc';
    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
    }

    abstract protected function query(): Builder;

    public function render()
    {
        $rows = $this->query()
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($this->perPage);

        return view($this->viewName(), compact('rows'));
    }

    abstract protected function viewName(): string;
}
