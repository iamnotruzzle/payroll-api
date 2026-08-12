<x-layouts.app :title="'IPCR · '.$employee->full_name">
    <livewire:performance.ipcr-employee-sheet :emp-id="$empId" :period-id="$periodId" />
</x-layouts.app>
