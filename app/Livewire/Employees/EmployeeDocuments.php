<?php

namespace App\Livewire\Employees;

use App\Models\HrisV2\Employee as V2Employee;
use App\Models\HrisV2\EmployeeDocument;
use App\Support\Hris\EmployeeDirectoryQuery;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class EmployeeDocuments extends Component
{
    use WithFileUploads;

    public string $empId;

    public $upload;

    public string $title = '';

    public string $category = 'general';

    public function mount(string $empId): void
    {
        abort_unless($this->canView(), 403);
        $this->empId = $empId;
    }

    public function save(): void
    {
        abort_unless($this->canManage(), 403);
        abort_unless(EmployeeDirectoryQuery::usesV2(), 422);

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:64'],
            'upload' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $employee = V2Employee::query()->where('emp_id', $this->empId)->firstOrFail();
        $path = $this->upload->store("employee-documents/{$employee->emp_id}", 'local');

        EmployeeDocument::query()->create([
            'employee_id' => $employee->id,
            'emp_id' => $employee->emp_id,
            'category' => $data['category'],
            'title' => $data['title'],
            'original_name' => $this->upload->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $this->upload->getMimeType(),
            'size_bytes' => $this->upload->getSize() ?: 0,
            'uploaded_by_emp_id' => auth()->user()?->emp_id,
        ]);

        $this->reset(['upload', 'title']);
        $this->category = 'general';
        session()->flash('docs_status', 'Document uploaded.');
    }

    public function deleteDocument(int $documentId): void
    {
        abort_unless($this->canManage(), 403);
        abort_unless(EmployeeDirectoryQuery::usesV2(), 422);

        $document = EmployeeDocument::query()
            ->where('emp_id', $this->empId)
            ->whereKey($documentId)
            ->firstOrFail();

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        session()->flash('docs_status', 'Document deleted.');
    }

    public function render()
    {
        abort_unless($this->canView(), 403);

        $documents = EmployeeDirectoryQuery::usesV2()
            ? EmployeeDocument::query()
                ->where('emp_id', $this->empId)
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('livewire.employees.employee-documents', [
            'documents' => $documents,
            'usesV2' => EmployeeDirectoryQuery::usesV2(),
            'canManage' => $this->canManage(),
        ]);
    }

    private function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('employees.view') || $user?->can('employees.manage'));
    }

    private function canManage(): bool
    {
        return (bool) auth()->user()?->can('employees.manage');
    }
}
