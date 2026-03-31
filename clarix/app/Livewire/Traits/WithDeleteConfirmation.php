<?php

namespace App\Livewire\Traits;

trait WithDeleteConfirmation
{
    public bool $showDeleteModal = false;
    public ?int $deletingId = null;
    public string $deletingName = '';

    public function openDeleteModal(int $id, string $name = ''): void
    {
        $this->deletingId = $id;
        $this->deletingName = $name;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }
}
