<?php

namespace App\Livewire\Schedule;

use App\Models\Schedule\SchedulePrintLogo;
use App\Models\Schedule\SchedulePrintSetting;
use App\Models\Schedule\ScheduleSignatory;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class PrintSettings extends Component
{
    use WithFileUploads;

    public string $organization_name = 'MARIANO MARCOS MEMORIAL HOSPITAL AND MEDICAL CENTER';
    public string $schedule_heading = 'MONTHLY SCHEDULE OF DUTIES';
    public string $area_label = 'AREA';
    public array $logos = [];

    public ?int $editingSignatoryId = null;
    public string $purpose = '';
    public string $person_name = '';
    public ?string $designation = null;
    public int $display_order = 1;
    public bool $is_active = true;

    public function mount(): void
    {
        $setting = $this->settingsRecord();

        if ($setting) {
            $this->organization_name = $setting->organization_name;
            $this->schedule_heading = $setting->schedule_heading;
            $this->area_label = $setting->area_label;
        }
    }

    public function render()
    {
        return view('livewire.schedule.print-settings', [
            'department' => auth()->user()?->employee?->department,
            'printLogos' => $this->printLogos(),
            'signatories' => ScheduleSignatory::where('department_id', $this->departmentId())
                ->orderBy('display_order')
                ->orderBy('purpose')
                ->get(),
        ]);
    }

    public function saveSettings(): void
    {
        $data = $this->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'schedule_heading' => ['required', 'string', 'max:255'],
            'area_label' => ['required', 'string', 'max:80'],
        ]);

        $setting = $this->settingsRecord() ?? new SchedulePrintSetting([
            'department_id' => $this->departmentId(),
        ]);

        $setting->fill([
            'organization_name' => $data['organization_name'],
            'schedule_heading' => $data['schedule_heading'],
            'area_label' => $data['area_label'],
        ])->save();

        session()->flash('status', 'Print settings saved.');
    }

    public function uploadLogos(): void
    {
        $data = $this->validate([
            'logos' => ['required', 'array', 'min:1'],
            'logos.*' => ['image', 'max:4096'],
        ]);

        $nextOrder = ((int) SchedulePrintLogo::where('department_id', $this->departmentId())->max('display_order')) + 1;

        foreach ($data['logos'] as $index => $logo) {
            SchedulePrintLogo::create([
                'department_id' => $this->departmentId(),
                'label' => 'Logo '.($nextOrder + $index),
                'path' => $logo->store('schedule-logos', 'public'),
                'x_position' => min(90, 2 + ($index * 12)),
                'y_position' => 2,
                'width' => 72,
                'display_order' => $nextOrder + $index,
                'is_active' => true,
            ]);
        }

        $this->logos = [];
        $this->dispatchLogosUpdated();
        session()->flash('status', 'Logo uploaded.');
    }

    public function updateLogoPlacement(int $id, float $xPosition, float $yPosition): void
    {
        $logo = $this->logoRecord($id);

        $logo->update([
            'x_position' => max(0, min(100, $xPosition)),
            'y_position' => max(0, min(100, $yPosition)),
        ]);
    }

    public function updateLogo(int $id, string $field, mixed $value): void
    {
        abort_unless(in_array($field, ['label', 'width', 'display_order', 'is_active'], true), 422);

        $logo = $this->logoRecord($id);

        $logo->update(match ($field) {
            'label' => ['label' => trim((string) $value) ?: 'Logo'],
            'width' => ['width' => max(24, min(240, (int) $value))],
            'display_order' => ['display_order' => max(1, min(99, (int) $value))],
            'is_active' => ['is_active' => (bool) $value],
        });

        $this->dispatchLogosUpdated();
    }

    public function deleteLogo(int $id): void
    {
        $logo = $this->logoRecord($id);

        Storage::disk('public')->delete($logo->path);
        $logo->delete();
        $this->dispatchLogosUpdated();
        session()->flash('status', 'Logo deleted.');
    }

    public function editSignatory(int $id): void
    {
        $signatory = ScheduleSignatory::where('department_id', $this->departmentId())->findOrFail($id);

        $this->editingSignatoryId = $signatory->id;
        $this->purpose = $signatory->purpose;
        $this->person_name = $signatory->person_name;
        $this->designation = $signatory->designation;
        $this->display_order = $signatory->display_order;
        $this->is_active = $signatory->is_active;
    }

    public function saveSignatory(): void
    {
        $data = $this->validate([
            'purpose' => ['required', 'string', 'max:80'],
            'person_name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'display_order' => ['required', 'integer', 'min:1', 'max:99'],
            'is_active' => ['boolean'],
        ]);

        $signatory = $this->editingSignatoryId
            ? ScheduleSignatory::where('department_id', $this->departmentId())->findOrFail($this->editingSignatoryId)
            : new ScheduleSignatory(['department_id' => $this->departmentId()]);

        $signatory->fill($data)->save();

        session()->flash('status', 'Signatory saved.');
        $this->resetSignatoryForm();
    }

    public function resetSignatoryForm(): void
    {
        $this->editingSignatoryId = null;
        $this->purpose = '';
        $this->person_name = '';
        $this->designation = null;
        $this->display_order = 1;
        $this->is_active = true;
    }

    private function settingsRecord(): ?SchedulePrintSetting
    {
        return SchedulePrintSetting::where('department_id', $this->departmentId())->first();
    }

    private function logoRecord(int $id): SchedulePrintLogo
    {
        return SchedulePrintLogo::where('department_id', $this->departmentId())->findOrFail($id);
    }

    private function printLogos()
    {
        return SchedulePrintLogo::where('department_id', $this->departmentId())
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    private function dispatchLogosUpdated(): void
    {
        $this->dispatch('print-logos-updated', logos: $this->printLogos()
            ->map(fn (SchedulePrintLogo $logo) => [
                'id' => $logo->id,
                'label' => $logo->label,
                'url' => asset('storage/'.$logo->path),
                'x' => (float) $logo->x_position,
                'y' => (float) $logo->y_position,
                'width' => $logo->width,
                'active' => $logo->is_active,
            ])
            ->values()
            ->all());
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }
}
